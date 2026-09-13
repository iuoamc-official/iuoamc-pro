from __future__ import annotations

import os
import uuid

import boto3
import psycopg
from botocore.client import Config
from fastapi import Depends, FastAPI, HTTPException
from pydantic import BaseModel, Field

from packages.auth.security import require_permission
from packages.events.nats_client import publish

app = FastAPI(title="IUOAMC Media Library", version="0.1.0")


def db():
    dsn = os.environ["DATABASE_URL"].replace("postgresql+psycopg://", "postgresql://")
    with psycopg.connect(dsn) as conn:
        yield conn


def s3():
    return boto3.client(
        "s3",
        endpoint_url=os.environ["S3_ENDPOINT"],
        aws_access_key_id=os.environ["S3_ACCESS_KEY"],
        aws_secret_access_key=os.environ["S3_SECRET_KEY"],
        config=Config(signature_version="s3v4"),
        region_name="us-east-1",
    )


class AssetRegister(BaseModel):
    title: str
    media_type: str = Field(pattern=r"^(video|audio|image|subtitle|document)$")
    mime_type: str | None = None
    original_filename: str


@app.get("/health")
def health():
    return {"service": "media-library", "status": "ok", "phase": 1}


@app.get("/v1/assets")
def list_assets(
    _: dict = Depends(require_permission("media.read")),
    conn=Depends(db),
):
    rows = conn.execute(
        """SELECT id,asset_key,title,media_type,mime_type,size_bytes,duration_ms,status,
                  checksum_sha256,storage_bucket,storage_object,metadata,created_at
           FROM media_assets ORDER BY created_at DESC LIMIT 200"""
    ).fetchall()
    return [
        {
            "id": str(r[0]),
            "asset_key": r[1],
            "title": r[2],
            "media_type": r[3],
            "mime_type": r[4],
            "size_bytes": r[5],
            "duration_ms": r[6],
            "status": r[7],
            "checksum_sha256": r[8],
            "storage_bucket": r[9],
            "storage_object": r[10],
            "metadata": r[11],
            "created_at": r[12],
        }
        for r in rows
    ]


@app.post("/v1/assets/register", status_code=201)
async def register(
    item: AssetRegister,
    actor: dict = Depends(require_permission("media.write")),
    conn=Depends(db),
):
    asset_id = uuid.uuid4()
    asset_key = "AST-" + asset_id.hex[:16].upper()
    safe_name = "".join(
        c if c.isalnum() or c in "._-" else "_" for c in item.original_filename
    )[:180]
    storage_object = f"assets/{asset_id}/{safe_name}"
    bucket = os.environ["S3_BUCKET"]

    conn.execute(
        """INSERT INTO media_assets(id,asset_key,title,media_type,mime_type,storage_bucket,storage_object)
           VALUES(%s,%s,%s,%s,%s,%s,%s)""",
        (
            asset_id,
            asset_key,
            item.title,
            item.media_type,
            item.mime_type,
            bucket,
            storage_object,
        ),
    )
    conn.execute(
        """INSERT INTO audit_events(actor_label,action,resource_type,resource_id,source_service,payload)
           VALUES(%s,'media.register','media_asset',%s,'media-library','{}'::jsonb)""",
        (actor.get("sub"), str(asset_id)),
    )
    conn.commit()

    await publish(
        "media.asset.created",
        {"asset_id": str(asset_id), "asset_key": asset_key},
    )
    return {
        "id": str(asset_id),
        "asset_key": asset_key,
        "storage_object": storage_object,
    }


@app.post("/v1/assets/{asset_id}/upload-url")
def upload_url(
    asset_id: uuid.UUID,
    _: dict = Depends(require_permission("media.upload")),
    conn=Depends(db),
):
    row = conn.execute(
        "SELECT storage_bucket,storage_object,status FROM media_assets WHERE id=%s",
        (asset_id,),
    ).fetchone()
    if not row:
        raise HTTPException(404, "Asset not found")

    url = s3().generate_presigned_url(
        "put_object",
        Params={"Bucket": row[0], "Key": row[1]},
        ExpiresIn=900,
    )
    return {"method": "PUT", "url": url, "expires_in": 900}

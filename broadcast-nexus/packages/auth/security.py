from __future__ import annotations

import os

import jwt
from fastapi import Depends, HTTPException, status
from fastapi.security import HTTPAuthorizationCredentials, HTTPBearer

bearer = HTTPBearer(auto_error=False)


def decode_token(credentials: HTTPAuthorizationCredentials = Depends(bearer)) -> dict:
    if not credentials:
        raise HTTPException(status_code=status.HTTP_401_UNAUTHORIZED, detail="Missing bearer token")

    try:
        payload = jwt.decode(
            credentials.credentials,
            os.environ["JWT_SECRET"],
            algorithms=["HS256"],
            issuer=os.environ["JWT_ISSUER"],
            audience=os.environ["JWT_AUDIENCE"],
            options={"require": ["exp", "iat", "sub", "iss", "aud"]},
        )
    except jwt.PyJWTError:
        raise HTTPException(status_code=status.HTTP_401_UNAUTHORIZED, detail="Invalid token")

    return payload


def require_permission(permission: str):
    def dependency(payload: dict = Depends(decode_token)) -> dict:
        permissions = set(payload.get("permissions", []))
        if permission not in permissions and "*" not in permissions:
            raise HTTPException(status_code=status.HTTP_403_FORBIDDEN, detail="Permission denied")
        return payload

    return dependency

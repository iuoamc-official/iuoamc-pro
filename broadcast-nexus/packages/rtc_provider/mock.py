from __future__ import annotations
import secrets
from typing import Any
from .base import JoinDescriptor, RoomDescriptor


class MockRTCProvider:
    name = "mock"

    async def provision_room(self, room_key: str, region: str | None = None) -> RoomDescriptor:
        return RoomDescriptor(
            provider_room_id="MOCK-ROOM-" + secrets.token_hex(6).upper(),
            region=region,
            metadata={"network": "disabled", "room_key": room_key},
        )

    async def close_room(self, provider_room_id: str) -> None:
        return None

    async def issue_join(self, provider_room_id: str, participant_key: str, permissions: dict[str, bool]) -> JoinDescriptor:
        return JoinDescriptor(
            endpoint=None,
            access_token=None,
            ice_servers=[],
            metadata={
                "network": "disabled",
                "provider_room_id": provider_room_id,
                "participant_key": participant_key,
                "permissions": permissions,
            },
        )

    async def revoke_participant(self, provider_room_id: str, participant_key: str) -> None:
        return None

    async def request_egress(self, provider_room_id: str, target: dict[str, Any]) -> str:
        raise RuntimeError("Mock provider cannot create media egress")

    async def stop_egress(self, egress_id: str) -> None:
        return None

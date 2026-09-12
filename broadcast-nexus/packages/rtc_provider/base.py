from __future__ import annotations
from dataclasses import dataclass
from typing import Protocol, Any


@dataclass(frozen=True)
class RoomDescriptor:
    provider_room_id: str
    region: str | None
    metadata: dict[str, Any]


@dataclass(frozen=True)
class JoinDescriptor:
    endpoint: str | None
    access_token: str | None
    ice_servers: list[dict[str, Any]]
    metadata: dict[str, Any]


class RTCProvider(Protocol):
    name: str

    async def provision_room(self, room_key: str, region: str | None = None) -> RoomDescriptor:
        ...

    async def close_room(self, provider_room_id: str) -> None:
        ...

    async def issue_join(self, provider_room_id: str, participant_key: str, permissions: dict[str, bool]) -> JoinDescriptor:
        ...

    async def revoke_participant(self, provider_room_id: str, participant_key: str) -> None:
        ...

    async def request_egress(self, provider_room_id: str, target: dict[str, Any]) -> str:
        ...

    async def stop_egress(self, egress_id: str) -> None:
        ...

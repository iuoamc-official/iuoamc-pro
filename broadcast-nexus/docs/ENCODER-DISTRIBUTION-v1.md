# Encoder / Distribution / IPTV v1

## Scope
This phase adds control-plane models and local APIs for encoder orchestration and multi-output distribution. It does not start FFmpeg, RTMP, SRT, YouTube, or production HLS.

## Encoder Cluster
- Encoder node registry
- Node capability metadata
- Heartbeats
- Encoder job lifecycle
- House profiles such as `house-1080p25`
- Requested outputs stored as job intent
- No production media process is spawned in this phase

## Distribution Fabric
Each output is an independent destination. A failure in one destination must not terminate other outputs.

Supported destination kinds in the control model:
- YouTube
- IPTV HLS
- Internal HLS
- SRT
- RTMP
- Recording

All destinations default to disabled and use independent isolation mode.

## Health Probes
The health data model supports:
- latency
- bitrate
- FPS
- audio presence
- video presence
- frozen-frame flag
- black-frame flag
- provider-specific diagnostic payloads

No external probe is enabled automatically.

## IPTV / HLS
- HLS manifest registry
- Variant model
- Playlist window and target duration
- M3U preview generation
- No public HTTP origin is configured
- No production CDN is configured

## Ports
All development ports bind to localhost only:
- 58108 Encoder
- 58109 Distribution
- 58110 IPTV

## Safety
- No stream keys in Git
- No production endpoint URLs in code
- No FFmpeg execution in the encoder API
- No automatic network publishing
- No effect on current IUOAMC TV

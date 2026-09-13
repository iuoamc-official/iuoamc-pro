#!/usr/bin/env bash
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT"

MANIFEST="release/release-manifest.json"
[[ -f "$MANIFEST" ]] || { echo "Missing $MANIFEST"; exit 1; }

python3 - <<'PY'
import json
from pathlib import Path
m = json.loads(Path('release/release-manifest.json').read_text())
assert m['release_channel'] == 'staging'
assert m['production_switching'] is False
assert m['production_outputs_enabled'] is False
assert m['public_publishing_enabled'] is False
for value in m['deployment_policy'].values():
    assert value is False
for group in ('required_compose_files', 'required_scripts', 'required_docs'):
    for p in m[group]:
        assert Path(p).exists(), f'Missing required release artifact: {p}'
print(f"Release manifest OK: {m['release_version']}")
PY

if [[ -f .env ]]; then
  grep -Eq '^PRODUCTION_SWITCHING=false$' .env || { echo 'Unsafe PRODUCTION_SWITCHING'; exit 1; }
  grep -Eq '^PRODUCTION_OUTPUTS_ENABLED=false$' .env || { echo 'Unsafe PRODUCTION_OUTPUTS_ENABLED'; exit 1; }
  grep -Eq '^PUBLIC_PUBLISHING_ENABLED=false$' .env || { echo 'Unsafe PUBLIC_PUBLISHING_ENABLED'; exit 1; }
fi

echo 'Release verification passed. Staging-only safeguards are intact.'

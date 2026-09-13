#!/usr/bin/env bash
set -euo pipefail
cd "$(dirname "$0")/.."

echo "IUOAMC Broadcast Nexus security preflight"
echo "Mode: repository/static checks only"

FAIL=0

forbidden_exec_patterns=(
  'a.rtmp.youtube.com'
  'rtmp://'
  'rtmps://'
  'platform-iuoamc.uk'
)

scan_targets=(services scripts compose.yaml compose.noc.yaml compose.shadow.yaml compose.supervisor.yaml)

for pattern in "${forbidden_exec_patterns[@]}"; do
  if grep -RIn --exclude='security-preflight.sh' -- "$pattern" "${scan_targets[@]}" 2>/dev/null; then
    echo "FAIL: forbidden production/broadcast pattern found: $pattern"
    FAIL=1
  fi
done

if find . -type f \( -name '*.pem' -o -name '*.key' -o -name '*.p12' -o -name '*.pfx' \) -print | grep -q .; then
  echo "FAIL: private key/certificate container file detected in broadcast-nexus"
  FAIL=1
fi

if [ -f .env ]; then
  echo "NOTICE: local .env exists; verifying it is not expected to be committed"
fi

if grep -RIl --exclude='.env.example' --exclude='security-preflight.sh' 'BEGIN .*PRIVATE KEY' . 2>/dev/null | grep -q .; then
  echo "FAIL: private key material detected"
  FAIL=1
fi

for compose in compose.yaml compose.noc.yaml compose.shadow.yaml compose.supervisor.yaml; do
  if [ -f "$compose" ]; then
    if grep -E '(^|[[:space:]-])"?0\.0\.0\.0:' "$compose" >/dev/null 2>&1; then
      echo "FAIL: public host binding found in $compose"
      FAIL=1
    fi
  fi
done

if [ "$FAIL" -ne 0 ]; then
  exit 20
fi

echo "PASS: security preflight found no production connectivity or committed private key material"

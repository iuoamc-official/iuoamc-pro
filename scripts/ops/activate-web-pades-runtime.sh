#!/usr/bin/env bash
(
set -Eeuo pipefail
umask 077

PROJECT="/home/iuoamcnext/web/next.maadaran.com/private/iuoamc-next"
APP_USER="iuoamcnext"
SOURCE_DIR="/etc/iuoamc-secrets"
RUNTIME_DIR="$PROJECT/storage/app/private/pro-certificates/pades-secrets"
SOURCE_PKCS12="$SOURCE_DIR/certificate-signing.p12"
SOURCE_PASSPHRASE="$SOURCE_DIR/certificate-signing.pass"
SOURCE_TRUST_ROOT="$SOURCE_DIR/icga-institutional-root-ca.crt"
RUNTIME_PKCS12="$RUNTIME_DIR/certificate-signing.p12"
RUNTIME_PASSPHRASE="$RUNTIME_DIR/certificate-signing.pass"
RUNTIME_TRUST_ROOT="$RUNTIME_DIR/icga-institutional-root-ca.crt"
ENV_FILE="$PROJECT/.env"
TMP_DIR="$(mktemp -d /run/iuoamc-pades-web.XXXXXX)"

cleanup() {
    if [[ "${TMP_DIR:-}" == /run/iuoamc-pades-web.* ]] && [[ -d "$TMP_DIR" ]]; then
        rm -rf -- "$TMP_DIR"
    fi
}
trap cleanup EXIT

test "$(id -u)" -eq 0 || { echo "STOP: ROOT_REQUIRED"; exit 1; }
cd "$PROJECT"
chown "$APP_USER:$APP_USER" "$TMP_DIR"
chmod 0700 "$TMP_DIR"

for required in "$SOURCE_PKCS12" "$SOURCE_PASSPHRASE" "$SOURCE_TRUST_ROOT" "$ENV_FILE"; do
    test -f "$required" || { echo "STOP: REQUIRED_FILE_MISSING=$required"; exit 1; }
done
test -x /opt/iuoamc-pades/bin/pyhanko || { echo "STOP: PYHANKO_MISSING"; exit 1; }

install -d -o "$APP_USER" -g "$APP_USER" -m 0700 "$RUNTIME_DIR"
install -o "$APP_USER" -g "$APP_USER" -m 0600 "$SOURCE_PKCS12" "$RUNTIME_PKCS12"
install -o "$APP_USER" -g "$APP_USER" -m 0600 "$SOURCE_PASSPHRASE" "$RUNTIME_PASSPHRASE"
install -o "$APP_USER" -g "$APP_USER" -m 0600 "$SOURCE_TRUST_ROOT" "$RUNTIME_TRUST_ROOT"

for pair in \
    "$SOURCE_PKCS12:$RUNTIME_PKCS12" \
    "$SOURCE_PASSPHRASE:$RUNTIME_PASSPHRASE" \
    "$SOURCE_TRUST_ROOT:$RUNTIME_TRUST_ROOT"; do
    source_file="${pair%%:*}"
    runtime_file="${pair#*:}"
    test "$(sha256sum "$source_file" | awk '{print $1}')" = "$(sha256sum "$runtime_file" | awk '{print $1}')" || {
        echo "STOP: RUNTIME_COPY_MISMATCH=$runtime_file"
        exit 1
    }
done

runuser -u "$APP_USER" -- openssl pkcs12 \
    -in "$RUNTIME_PKCS12" \
    -passin "file:$RUNTIME_PASSPHRASE" \
    -clcerts \
    -nokeys \
    -out "$TMP_DIR/signing-certificate.crt"
runuser -u "$APP_USER" -- openssl verify \
    -CAfile "$RUNTIME_TRUST_ROOT" \
    "$TMP_DIR/signing-certificate.crt"

BACKUP_DIR="$PROJECT/storage/app/private/config-backups"
install -d -o "$APP_USER" -g "$APP_USER" -m 0700 "$BACKUP_DIR"
ENV_BACKUP="$BACKUP_DIR/env-before-web-pades-$(date -u +%Y%m%d-%H%M%S)"
runuser -u "$APP_USER" -- cp -p "$ENV_FILE" "$ENV_BACKUP"
chmod 0600 "$ENV_BACKUP"

set_env() {
    local key="$1"
    local value="$2"

    if runuser -u "$APP_USER" -- grep -q "^${key}=" "$ENV_FILE"; then
        runuser -u "$APP_USER" -- sed -i "s|^${key}=.*|${key}=${value}|" "$ENV_FILE"
    else
        runuser -u "$APP_USER" -- sh -c 'printf "%s=%s\n" "$1" "$2" >> "$3"' sh "$key" "$value" "$ENV_FILE"
    fi
}

set_env "IUOAMC_PADES_ENABLED" "true"
set_env "IUOAMC_PADES_BINARY" "/opt/iuoamc-pades/bin/pyhanko"
set_env "IUOAMC_PADES_PKCS12_PATH" "$RUNTIME_PKCS12"
set_env "IUOAMC_PADES_PASSPHRASE_FILE" "$RUNTIME_PASSPHRASE"
set_env "IUOAMC_PADES_TRUST_ROOT_PATH" "$RUNTIME_TRUST_ROOT"
set_env "IUOAMC_PADES_TIMESTAMP_URL" ""
set_env "IUOAMC_PADES_PROFILE" "PAdES-B-B"
set_env "IUOAMC_PADES_TIMEOUT" "90"

runuser -u "$APP_USER" -- php artisan optimize:clear
runuser -u "$APP_USER" -- php artisan optimize

echo "RUNTIME_PADES_SIGNING_TEST:"
runuser -u "$APP_USER" -- php artisan tinker --execute='
$signer = app(\App\Services\ProCertificatePadesSigner::class);
dump($signer->readiness());
$input = file_get_contents(resource_path("certificates/master-a4-v1.3.1.pdf"));
if (! is_string($input)) { throw new \RuntimeException("SMOKE_TEST_PDF_MISSING"); }
$result = $signer->sign($input);
dump([
    "profile" => $result["profile"],
    "status" => $result["status"],
    "certificate_sha256" => $result["certificate_sha256"],
]);
'

mapfile -t fpm_services < <(
    systemctl list-units --type=service --state=running --no-legend |
        awk '$1 ~ /^php[0-9.]+-fpm\.service$/ {print $1}'
)
test "${#fpm_services[@]}" -gt 0 || { echo "STOP: PHP_FPM_SERVICE_NOT_FOUND"; exit 1; }

for service in "${fpm_services[@]}"; do
    systemctl reload "$service"
done

echo "RUNTIME_DIR=$RUNTIME_DIR"
echo "ENV_BACKUP=$ENV_BACKUP"
echo "WEB_PADES_RUNTIME_READY"
)

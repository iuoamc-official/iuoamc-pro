#!/usr/bin/env bash
(
set -Eeuo pipefail
umask 077

PROJECT="/home/iuoamcnext/web/next.maadaran.com/private/iuoamc-next"
APP_USER="iuoamcnext"
SECRET_DIR="/etc/iuoamc-secrets"
SIGNING_KEY="$SECRET_DIR/certificate-signing.key"
ROOT_CERT="$SECRET_DIR/icga-institutional-root-ca.crt"
LEAF_CERT="$SECRET_DIR/certificate-signing.crt"
PKCS12="$SECRET_DIR/certificate-signing.p12"
PASS_FILE="$SECRET_DIR/certificate-signing.pass"
ENV_FILE="$PROJECT/.env"
TMP_DIR="$(mktemp -d /run/iuoamc-pades-resume.XXXXXX)"

cleanup() {
    if [[ "${TMP_DIR:-}" == /run/iuoamc-pades-resume.* ]] && [[ -d "$TMP_DIR" ]]; then
        rm -rf -- "$TMP_DIR"
    fi
}
trap cleanup EXIT

cd "$PROJECT"

for required in "$SIGNING_KEY" "$ROOT_CERT" "$LEAF_CERT" "$ENV_FILE"; do
    test -f "$required" || { echo "STOP: REQUIRED_FILE_MISSING=$required"; exit 1; }
done
test -x /opt/iuoamc-pades/bin/pyhanko || { echo "STOP: PYHANKO_MISSING"; exit 1; }
test ! -e "$PASS_FILE" || { echo "STOP: PASSPHRASE_FILE_ALREADY_EXISTS"; exit 1; }

if [[ -e "$PKCS12" ]]; then
    FAILED_P12="$SECRET_DIR/certificate-signing.p12.failed-$(date -u +%Y%m%d-%H%M%S)"
    mv -- "$PKCS12" "$FAILED_P12"
    chmod 0600 "$FAILED_P12"
    echo "PARTIAL_PKCS12_ARCHIVED=$FAILED_P12"
fi

read -rsp "كلمة مرور certificate-signing.key الحالية: " LEAF_PASSWORD
echo
printf '%s' "$LEAF_PASSWORD" > "$TMP_DIR/leaf.pass"
unset LEAF_PASSWORD

openssl pkey -in "$SIGNING_KEY" -passin file:"$TMP_DIR/leaf.pass" -check -noout
openssl verify -CAfile "$ROOT_CERT" "$LEAF_CERT"

KEY_SHA="$(openssl pkey -in "$SIGNING_KEY" -passin file:"$TMP_DIR/leaf.pass" -pubout -outform DER | sha256sum | awk '{print $1}')"
CERT_SHA="$(openssl x509 -in "$LEAF_CERT" -pubkey -noout | openssl pkey -pubin -outform DER | sha256sum | awk '{print $1}')"
test "$KEY_SHA" = "$CERT_SHA" || { echo "STOP: CERTIFICATE_KEY_MISMATCH"; exit 1; }

read -rsp "كلمة مرور جديدة لملف PKCS12، 16 حرفًا على الأقل: " P12_PASSWORD_1
echo
read -rsp "أعد كلمة مرور ملف PKCS12: " P12_PASSWORD_2
echo
test "${#P12_PASSWORD_1}" -ge 16 || { echo "STOP: PKCS12_PASSWORD_TOO_SHORT"; exit 1; }
test "$P12_PASSWORD_1" = "$P12_PASSWORD_2" || { echo "STOP: PKCS12_PASSWORDS_DO_NOT_MATCH"; exit 1; }
printf '%s\n' "$P12_PASSWORD_1" > "$TMP_DIR/p12.pass"
unset P12_PASSWORD_1 P12_PASSWORD_2

openssl pkcs12 \
    -export \
    -inkey "$SIGNING_KEY" \
    -passin file:"$TMP_DIR/leaf.pass" \
    -in "$LEAF_CERT" \
    -certfile "$ROOT_CERT" \
    -name "ICGA Authorized Document Signing" \
    -out "$PKCS12" \
    -passout file:"$TMP_DIR/p12.pass"

install -o "$APP_USER" -g "$APP_USER" -m 0600 "$TMP_DIR/p12.pass" "$PASS_FILE"
chown "$APP_USER:$APP_USER" "$PKCS12"
chmod 0600 "$PKCS12"
chown root:"$APP_USER" "$ROOT_CERT" "$LEAF_CERT"
chmod 0640 "$ROOT_CERT" "$LEAF_CERT"

openssl pkcs12 -in "$PKCS12" -passin file:"$PASS_FILE" -clcerts -nokeys -out "$TMP_DIR/exported-leaf.crt"
openssl verify -CAfile "$ROOT_CERT" "$TMP_DIR/exported-leaf.crt"

echo "ISOLATED_PADES_TEST:"
runuser -u "$APP_USER" -- php artisan tinker --execute='
config()->set("certificates.pades.enabled", true);
config()->set("certificates.pades.binary", "/opt/iuoamc-pades/bin/pyhanko");
config()->set("certificates.pades.pkcs12_path", "/etc/iuoamc-secrets/certificate-signing.p12");
config()->set("certificates.pades.passphrase_file", "/etc/iuoamc-secrets/certificate-signing.pass");
config()->set("certificates.pades.trust_root_path", "/etc/iuoamc-secrets/icga-institutional-root-ca.crt");
config()->set("certificates.pades.timestamp_url", null);
config()->set("certificates.pades.profile", "PAdES-B-B");
$signer = app(\App\Services\ProCertificatePadesSigner::class);
dump($signer->readiness());
$input = file_get_contents(resource_path("certificates/master-a4-v1.3.1.pdf"));
if (!is_string($input)) { throw new \RuntimeException("SMOKE_TEST_PDF_MISSING"); }
$result = $signer->sign($input);
dump([
    "profile" => $result["profile"],
    "status" => $result["status"],
    "field" => $result["field"],
    "certificate_sha256" => $result["certificate_sha256"],
]);
'

BACKUP_DIR="$PROJECT/storage/app/private/config-backups"
install -d -o "$APP_USER" -g "$APP_USER" -m 0700 "$BACKUP_DIR"
ENV_BACKUP="$BACKUP_DIR/env-before-internal-pades-$(date -u +%Y%m%d-%H%M%S)"
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
set_env "IUOAMC_PADES_PKCS12_PATH" "$PKCS12"
set_env "IUOAMC_PADES_PASSPHRASE_FILE" "$PASS_FILE"
set_env "IUOAMC_PADES_TRUST_ROOT_PATH" "$ROOT_CERT"
set_env "IUOAMC_PADES_TIMESTAMP_URL" ""
set_env "IUOAMC_PADES_PROFILE" "PAdES-B-B"
set_env "IUOAMC_PADES_TIMEOUT" "90"

runuser -u "$APP_USER" -- php artisan optimize:clear
runuser -u "$APP_USER" -- php artisan optimize

echo "FINAL_PADES_READINESS:"
runuser -u "$APP_USER" -- php artisan tinker --execute='dump(app(\App\Services\ProCertificatePadesSigner::class)->readiness());'

echo "ROOT_CA_FINGERPRINT:"
openssl x509 -in "$ROOT_CERT" -noout -fingerprint -sha256
echo "DOCUMENT_SIGNING_CERTIFICATE:"
openssl x509 -in "$LEAF_CERT" -noout -subject -issuer -dates -fingerprint -sha256
echo "ENV_BACKUP=$ENV_BACKUP"
echo "INSTITUTIONAL_X509_PADES_READY"
)

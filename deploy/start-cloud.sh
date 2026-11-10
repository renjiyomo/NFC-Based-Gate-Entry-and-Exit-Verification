#!/bin/sh
set -eu
umask 077
cd /app
if [ "${NFCHECK_CLOUD:-false}" != "true" ]; then
    echo 'Cloud runtime requires the reviewed cloud configuration.' >&2
    exit 1
fi
if [ -z "${APP_URL:-}" ] && [ "${VERCEL_ENV:-}" = "production" ]; then
    APP_URL="$(php -r '$host = getenv("VERCEL_PROJECT_PRODUCTION_URL") ?: ""; if (!preg_match("/^[a-z0-9][a-z0-9.-]*\\.vercel\\.app$/D", $host)) { fwrite(STDERR, "Set APP_URL or enable Vercel system environment variables.\n"); exit(1); } echo "https://", $host;')"
    export APP_URL
fi
php -r '$ca = base64_decode(getenv("NFCHECK_DB_CA_BASE64") ?: "", true); $path = "/app/storage/framework/database-ca.crt"; if (!$ca || getenv("DB_SSLROOTCERT") !== $path || !openssl_x509_read($ca)) { fwrite(STDERR, "A valid database CA certificate is required.\n"); exit(1); } if (file_put_contents($path, $ca) === false) { exit(1); } chmod($path, 0600);'
php artisan config:cache --no-ansi --quiet
php artisan nfcheck:check-deployment --no-ansi
exec frankenphp run --config /etc/caddy/Caddyfile --adapter caddyfile

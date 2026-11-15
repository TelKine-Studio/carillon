#!/bin/sh
# Carillon container entrypoint: prepares persistent storage, generates the
# application key on first boot, runs migrations, then starts the services.
set -eu

DATA_DIR="${CARILLON_DATA_DIR:-/data}"
STORAGE="${CARILLON_STORAGE_PATH:-$DATA_DIR/storage}"

artisan() {
    su-exec www-data php /var/www/html/artisan "$@"
}

mkdir -p "$DATA_DIR" \
    "$STORAGE/app/public" \
    "$STORAGE/framework/cache/data" \
    "$STORAGE/framework/sessions" \
    "$STORAGE/framework/views" \
    "$STORAGE/logs"

# The application key encrypts sessions and stored secrets. It is generated
# once and kept in the data volume, unless provided through APP_KEY.
if [ -z "${APP_KEY:-}" ]; then
    if [ ! -s "$DATA_DIR/app.key" ]; then
        echo "base64:$(head -c 32 /dev/urandom | base64)" > "$DATA_DIR/app.key"
        echo "[carillon] Generated a new application key in $DATA_DIR/app.key"
    fi
    APP_KEY="$(cat "$DATA_DIR/app.key")"
    export APP_KEY
fi

if [ "${DB_CONNECTION:-sqlite}" = "sqlite" ] && [ ! -f "${DB_DATABASE}" ]; then
    touch "${DB_DATABASE}"
fi

chown -R www-data:www-data "$DATA_DIR"
chmod 600 "$DATA_DIR/app.key" 2>/dev/null || true

# The database may start after Carillon (PostgreSQL in the same compose).
attempt=1
until artisan migrate --force --no-interaction; do
    if [ "$attempt" -ge 30 ]; then
        echo "[carillon] The database is still unreachable, giving up." >&2
        exit 1
    fi
    echo "[carillon] Waiting for the database ($attempt/30)..."
    attempt=$((attempt + 1))
    sleep 2
done
artisan optimize --no-interaction

echo "[carillon] Ready — open the public URL in your browser to finish the setup."

exec "$@"

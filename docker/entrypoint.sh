#!/usr/bin/env bash
#
# WemX container entrypoint.
# Prepares persistent volumes, the .env file and the database, then hands over to Supervisor.
set -euo pipefail

APP_DIR=/var/www/html
CONFIG_DIR=/config
ENV_FILE="$CONFIG_DIR/.env"

log() { echo "[wemx] $*"; }
as_www() { su-exec www-data "$@"; }

cd "$APP_DIR"

# ── 1. Persistent .env ───────────────────────────────────────────────────────
# The web installer and the license page write to .env, so it lives on the /config volume.
# Variables passed to the container (docker-compose `environment:`) always take precedence
# over values in this file.
mkdir -p "$CONFIG_DIR"
if [ ! -f "$ENV_FILE" ]; then
    log "Creating $ENV_FILE from .env.example"
    cp "$APP_DIR/.env.example" "$ENV_FILE"
fi
ln -sfn "$ENV_FILE" "$APP_DIR/.env"
chown www-data:www-data "$CONFIG_DIR" "$ENV_FILE"

# Generate an encryption key once, unless one is provided through the environment.
if [ -z "${APP_KEY:-}" ] && ! grep -qE '^APP_KEY=.+' "$ENV_FILE"; then
    log "Generating APP_KEY (back up $ENV_FILE: it encrypts gateway and server credentials)"
    as_www php artisan key:generate --force --no-interaction >/dev/null
fi

# ── 2. Volumes ───────────────────────────────────────────────────────────────
# Storage: recreate the folder structure (needed when an empty bind mount is used).
mkdir -p storage/app/public storage/framework/cache/data storage/framework/sessions \
         storage/framework/views storage/framework/testing storage/logs bootstrap/cache

# Extensions: refresh the extensions bundled with this image, keep marketplace installs.
mkdir -p extensions
cp -a /opt/wemx/extensions/. extensions/

# Public assets: refresh theme assets from the image, keep uploaded images.
mkdir -p public/assets/common/img
rsync -a --delete /opt/wemx/public-assets/adminarea/ public/assets/adminarea/
rsync -a --delete /opt/wemx/public-assets/clientarea/ public/assets/clientarea/
cp -an /opt/wemx/public-assets/common/. public/assets/common/ 2>/dev/null || true

# Public storage link (equivalent of `php artisan storage:link`).
ln -sfn ../storage/app/public public/storage

chown -R www-data:www-data storage bootstrap/cache extensions public/assets

# ── 3. Database ──────────────────────────────────────────────────────────────
is_installed() {
    [ "${APP_INSTALLED:-}" = "true" ] || grep -qE '^APP_INSTALLED=true' "$ENV_FILE"
}

if is_installed; then
    if [ "${WEMX_AUTO_MIGRATE:-true}" = "true" ]; then
        log "Waiting for the database and running migrations"
        attempts=0
        until as_www php artisan migrate --force --no-interaction; do
            attempts=$((attempts + 1))
            if [ "$attempts" -ge "${WEMX_DB_WAIT_ATTEMPTS:-30}" ]; then
                log "Database not reachable after $attempts attempts, starting anyway"
                break
            fi
            sleep 3
        done
    fi
else
    log "WemX is not installed yet: open ${APP_URL:-the site} in your browser to run the web installer"
fi

# Drop compiled views and cached config/routes left by a previous image.
as_www php artisan view:clear --no-interaction >/dev/null 2>&1 || true
as_www php artisan config:clear --no-interaction >/dev/null 2>&1 || true
as_www php artisan route:clear --no-interaction >/dev/null 2>&1 || true

log "Starting services"
exec "$@"

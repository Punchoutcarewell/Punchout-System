#!/bin/bash
#
# Azure App Service (Linux, PHP built-in/"blessed" image) startup command.
# Must be registered as the Startup Command in the portal
# (Configuration -> General settings -> Startup Command: startup.sh)
# so it re-runs on every container boot/restart/scale event. Running it
# once by hand over SSH/Kudu does NOT persist, the container filesystem
# resets on the next restart.
#
# Safe to re-run: every step here is idempotent.

APP_ROOT="/home/site/wwwroot"
NGINX_CONF="/etc/nginx/sites-available/default"
LOG_FILE="/home/LogFiles/startup.log"

mkdir -p "$(dirname "$LOG_FILE")"
log() { echo "[$(date '+%Y-%m-%d %H:%M:%S')] $*" | tee -a "$LOG_FILE"; }

log "startup.sh: begin"

# --- 1. Point nginx's document root at Laravel's public/ dir -----------
# Azure's default site config for the PHP image already has a `location /`
# block with its own `index` and `try_files` lines that route everything
# through index.php, exactly what Laravel needs. The only thing wrong
# with it out of the box is the document root (it points at the app root,
# not public/), which is what serves Oryx's own placeholder / a directory
# listing instead of this app.
if [ -f "$NGINX_CONF" ]; then
    if grep -qE "root[[:space:]]+${APP_ROOT}/public[[:space:]]*;" "$NGINX_CONF"; then
        log "nginx root already points at ${APP_ROOT}/public, nothing to do"
    elif grep -qE "root[[:space:]]+${APP_ROOT}[[:space:]]*;" "$NGINX_CONF"; then
        sed -i -E "s#root[[:space:]]+${APP_ROOT}[[:space:]]*;#root ${APP_ROOT}/public;#" "$NGINX_CONF"
        log "rewrote nginx root to ${APP_ROOT}/public"
    else
        log "WARNING: could not find the expected 'root ${APP_ROOT};' line in $NGINX_CONF - the base image's default config may have changed. Current root line(s):"
        grep -nE "^\s*root\s" "$NGINX_CONF" | tee -a "$LOG_FILE"
    fi

    if nginx -t 2>>"$LOG_FILE"; then
        service nginx reload 2>>"$LOG_FILE" || nginx -s reload 2>>"$LOG_FILE"
        log "nginx config valid, reloaded"
    else
        log "ERROR: nginx config test failed, not reloading (see $LOG_FILE for nginx -t output)"
    fi
else
    log "WARNING: $NGINX_CONF not found, skipping nginx fix-up (base image may differ from expected)"
fi

# --- 2. Writable dirs Laravel needs at runtime --------------------------
chown -R www-data:www-data "$APP_ROOT/storage" "$APP_ROOT/bootstrap/cache" 2>>"$LOG_FILE" || true
chmod -R ug+rwX "$APP_ROOT/storage" "$APP_ROOT/bootstrap/cache" 2>>"$LOG_FILE" || true

# --- 3. App bootstrap ----------------------------------------------------
# Each step is independent: one failing (e.g. DB not reachable yet) must
# not stop the others, or nginx would come up pointed at a dead app with
# no clue in the logs why.
cd "$APP_ROOT" || exit 1

run_step() {
    local desc="$1"; shift
    if "$@" >>"$LOG_FILE" 2>&1; then
        log "OK: $desc"
    else
        log "WARNING: $desc failed, see $LOG_FILE"
    fi
}

run_step "storage:link"      php artisan storage:link --force
run_step "config:cache"      php artisan config:cache
run_step "route:cache"       php artisan route:cache
run_step "view:cache"        php artisan view:cache
run_step "migrate --force"   php artisan migrate --force
run_step "punchout:doctor"   php artisan punchout:doctor

log "startup.sh: done"

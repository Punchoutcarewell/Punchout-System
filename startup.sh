#!/bin/bash
set -e

NGINX_CONF="/etc/nginx/sites-available/default"

if [ -f "$NGINX_CONF" ]; then
    # Azure's default site config for the PHP blessed image already has a
    # `location /` block with its own `index` and `try_files` lines that
    # route everything through index.php, exactly what Laravel needs. The
    # only thing wrong with it is the document root, so that's the only
    # line this touches; this sed is a no-op (and therefore safe to rerun)
    # once the root is already pointed at public/.
    sed -i 's#root /home/site/wwwroot;#root /home/site/wwwroot/public;#' "$NGINX_CONF"
    nginx -t && (service nginx reload || nginx -s reload)
fi

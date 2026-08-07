#!/bin/bash
set -e

NGINX_CONF="/etc/nginx/sites-available/default"

if [ -f "$NGINX_CONF" ]; then
    sed -i 's#root /home/site/wwwroot;#root /home/site/wwwroot/public;#' "$NGINX_CONF"
    sed -i '/index  index.php index.html index.htm hostingstart.html;/a\        try_files $uri $uri/ /index.php?$query_string;' "$NGINX_CONF"
    service nginx reload || nginx -s reload
fi

#!/bin/bash
# Run this by hand in cPanel's Terminal after every `git pull` to redeploy
# on the GoDaddy shared-hosting account. Not wired to any CI trigger -
# there's no inbound SSH here for GitHub Actions to reach, so this is a
# manual step, not an automated one.
set -e

cd "$(dirname "$0")"

echo "==> git pull"
git pull

echo "==> composer install"
composer install --no-dev --optimize-autoloader --no-interaction

echo "==> npm build"
npm ci
npm run build

echo "==> permissions"
chmod -R 775 storage bootstrap/cache

echo "==> laravel bootstrap"
php artisan storage:link
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan migrate --force
php artisan punchout:doctor

echo "==> done"

#!/usr/bin/env bash
# Deploy sekali jalan: bash deploy.sh
# Aman diulang; hentikan bila ada langkah gagal.
set -euo pipefail

cd "$(dirname "$0")"

echo "==> git pull"
git pull

echo "==> composer install (production)"
composer install --no-dev --optimize-autoloader --no-interaction

echo "==> migrate"
php artisan migrate --force

echo "==> build frontend"
npm ci
npm run build

echo "==> storage symlink (aman bila sudah ada)"
php artisan storage:link || true

echo "==> buang cache lama lalu cache ulang (config/route/view/event)"
php artisan optimize:clear
php artisan optimize

echo "==> selesai."

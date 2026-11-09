#!/usr/bin/env bash
# Deploy de GestiónEventos (geventos.migestion.com.ar) desde la rama main.
# Uso en el VPS:  cd /var/www/geventos && bash deploy.sh
set -euo pipefail

APP_DIR="/var/www/geventos"
cd "$APP_DIR"

echo "==> Modo mantenimiento"
php artisan down --retry=15 || true

echo "==> Código"
git pull origin main

echo "==> Dependencias"
composer install --no-dev --optimize-autoloader --no-interaction

echo "==> Base de datos"
php artisan migrate --force

echo "==> Cachés"
php artisan storage:link 2>/dev/null || true
php artisan optimize:clear
php artisan optimize

echo "==> Permisos"
sudo chown -R saul:www-data storage bootstrap/cache
sudo chmod -R ug+rwX storage bootstrap/cache

echo "==> Reinicio de procesos"
php artisan queue:restart
sudo systemctl reload php8.4-fpm

php artisan up
echo "==> Listo: https://geventos.migestion.com.ar"

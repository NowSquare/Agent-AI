#!/usr/bin/env bash
set -euo pipefail

cd /var/www/html

# Ensure APP_KEY exists
if [ -z "${APP_KEY:-}" ] || [ "$APP_KEY" = "" ]; then
  # If .env is present and key missing, try to generate
  if [ -f ".env" ] && ! grep -qE '^APP_KEY=base64:' .env; then
    php artisan key:generate --force || true
  fi
fi

# Cache config/routes/views (won't hard-fail the container if something's off)
php artisan config:cache || true
php artisan route:cache || true
php artisan view:cache || true

# Run database migrations automatically in dev; in prod you may prefer manual --force
if [ "${APP_ENV:-local}" = "local" ]; then
  php artisan migrate --force || true
fi

# Start all processes
exec /usr/bin/supervisord -c /etc/supervisor/conf.d/app.conf

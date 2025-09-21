# ====== 1) COMPOSER DEPS ======
FROM composer:2 AS vendor
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --prefer-dist --no-interaction --no-progress --optimize-autoloader

# ====== 2) FRONTEND BUILD (Node 22) ======
FROM node:22-bookworm AS frontend
WORKDIR /app
COPY package.json package-lock.json* ./
RUN npm ci
COPY resources ./resources
COPY vite.config.* ./
COPY postcss.config.* ./
COPY tailwind.config.* ./
RUN npm run build

# ====== 3) APP (PHP-FPM 8.4 + Nginx + Supervisor) ======
FROM php:8.4-fpm-bookworm AS app

ENV DEBIAN_FRONTEND=noninteractive \
    PHP_OPCACHE_VALIDATE_TIMESTAMPS=0 \
    PHP_MEMORY_LIMIT=512M \
    PHP_MAX_EXECUTION_TIME=60

WORKDIR /var/www/html

# OS packages
RUN apt-get update && apt-get install -y --no-install-recommends \
    git unzip ca-certificates curl supervisor nginx-light \
    libpq-dev libzip-dev libicu-dev libxml2-dev zlib1g-dev \
    libjpeg62-turbo-dev libpng-dev libfreetype6-dev \
    libonig-dev libssl-dev \
    poppler-utils \
    clamav clamav-daemon clamav-freshclam \
    && rm -rf /var/lib/apt/lists/*

# PHP extensions
RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
 && docker-php-ext-install -j"$(nproc)" pdo pdo_pgsql intl gd zip bcmath pcntl opcache

# PECL extensions
RUN pecl install redis && docker-php-ext-enable redis

# PHP.ini
RUN { \
    echo "memory_limit=${PHP_MEMORY_LIMIT}"; \
    echo "max_execution_time=${PHP_MAX_EXECUTION_TIME}"; \
    echo "opcache.enable=1"; \
    echo "opcache.enable_cli=1"; \
    echo "opcache.jit=tracing"; \
    echo "opcache.jit_buffer_size=128M"; \
    echo "opcache.validate_timestamps=${PHP_OPCACHE_VALIDATE_TIMESTAMPS}"; \
} > /usr/local/etc/php/conf.d/zz-custom.ini

# Nginx config
RUN rm -f /etc/nginx/sites-enabled/default /etc/nginx/nginx.conf \
 && printf '%s\n' \
   'user www-data;' \
   'worker_processes auto;' \
   'events { worker_connections 1024; }' \
   'http {' \
   '  include       /etc/nginx/mime.types;' \
   '  default_type  application/octet-stream;' \
   '  sendfile on;' \
   '  server {' \
   '    listen 8080 default_server;' \
   '    server_name _;' \
   '    root /var/www/html/public;' \
   '    index index.php;' \
   '    location / {' \
   '      try_files $uri $uri/ /index.php?$query_string;' \
   '    }' \
   '    location ~ \.php$ {' \
   '      include fastcgi_params;' \
   '      fastcgi_pass 127.0.0.1:9000;' \
   '      fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;' \
   '      fastcgi_read_timeout 60s;' \
   '    }' \
   '    location ~* \.(?:jpg|jpeg|png|gif|ico|css|js|svg|woff2?)$ {' \
   '      expires 30d;' \
   '      access_log off;' \
   '    }' \
   '  }' \
   '}' \
   > /etc/nginx/nginx.conf

# Supervisor
RUN mkdir -p /etc/supervisor/conf.d
RUN printf '%s\n' \
  '[supervisord]' \
  'nodaemon=true' \
  '' \
  '[program:php-fpm]' \
  'command=/usr/local/sbin/php-fpm -F' \
  'autostart=true' \
  'autorestart=true' \
  'priority=10' \
  '' \
  '[program:nginx]' \
  'command=/usr/sbin/nginx -g "daemon off;"' \
  'autostart=true' \
  'autorestart=true' \
  'priority=20' \
  '' \
  '[program:horizon]' \
  'command=/usr/local/bin/php artisan horizon' \
  'directory=/var/www/html' \
  'autostart=true' \
  'autorestart=true' \
  'stdout_logfile=/dev/stdout' \
  'stdout_logfile_maxbytes=0' \
  'stderr_logfile=/dev/stderr' \
  'stderr_logfile_maxbytes=0' \
  'priority=30' \
  > /etc/supervisor/conf.d/app.conf
# NOTE: If you prefer queue:work instead of Horizon, replace the block above.
# Avoid running BOTH Horizon and queue:work at the same time.

# App files
COPY . /var/www/html

# Composer deps
COPY --from=vendor /app/vendor /var/www/html/vendor

# Frontend build
COPY --from=frontend /app/public /var/www/html/public

# Permissions
RUN chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache

# Entrypoint: generate APP_KEY if missing, warm caches, then start supervisord
COPY docker/entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh

EXPOSE 8080
CMD ["/entrypoint.sh"]

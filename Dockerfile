# ---------- Stage 1: Frontend build (Laravel Mix) ----------
FROM node:18-alpine AS frontend
WORKDIR /app

RUN apk add --no-cache bash git python3 make g++

COPY package.json package-lock.json* yarn.lock* ./
COPY webpack.mix.js webpack-login.mix.js ./
COPY public ./public
COPY resources ./resources

# اگر lock داری، سعی می‌کند ci برود؛ اگر نبود fallback به install
RUN npm ci || npm install

# اگر واقعاً لازم داری، نگه دار؛ وگرنه حذف کن
# RUN ln -s /app/public /public

RUN npx mix --production && npx mix --mix-config=webpack-login.mix.js --production

# ---------- Stage 2: PHP app ----------
FROM php:8.3-fpm

ENV TZ=UTC
RUN ln -snf /usr/share/zoneinfo/$TZ /etc/localtime && echo $TZ > /etc/timezone

RUN apt-get update && apt-get install -y \
    git unzip libzip-dev libpng-dev libjpeg62-turbo-dev libfreetype6-dev \
    libonig-dev libxml2-dev libicu-dev libpq-dev libssl-dev librdkafka-dev \
    gnupg curl ca-certificates apt-transport-https lsb-release \
 && rm -rf /var/lib/apt/lists/*

# PHP extensions + pecl
RUN set -eux; \
    pecl install redis rdkafka; \
    docker-php-ext-enable redis rdkafka
RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
 && docker-php-ext-install -j"$(nproc)" gd zip pdo_mysql intl bcmath exif pcntl sockets

# Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
ENV COMPOSER_ALLOW_SUPERUSER=1

# php.ini
RUN { \
  echo "memory_limit=512M"; \
  echo "upload_max_filesize=20M"; \
  echo "post_max_size=20M"; \
  echo "max_execution_time=300"; \
} > /usr/local/etc/php/conf.d/pm.ini

WORKDIR /var/www/html
RUN git config --system --add safe.directory /var/www/html

# کپی سورس (root مالک می‌ماند، بعداً chown می‌کنیم)
COPY . .

# کپی خروجی فرانت‌اند
COPY --from=frontend /app/public/ /var/www/html/public/

# نصب Composer فقط یک‌بار و بدون scripts تا artisan اجرا نشود
RUN composer install --no-dev --prefer-dist --no-progress --no-interaction --optimize-autoloader --no-scripts

# دسترسی‌ها
RUN mkdir -p /var/www/html/vendor \
 && chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache /var/www/html/vendor

# Entrypoint در زمان run artisan‌ها را انجام می‌دهد
COPY docker/entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh

USER www-data
ENTRYPOINT ["/entrypoint.sh"]
CMD ["php-fpm"]

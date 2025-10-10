FROM node:18-alpine AS assets
WORKDIR /app
COPY package.json package-lock.json ./
RUN npm ci
COPY resources ./resources
COPY webpack.mix.js postcss.config.js babel.config.json tailwind.config.js ./
RUN npm run prod || npm run build || true

FROM php:8.1-fpm

RUN apt-get update && apt-get install -y \
    git unzip libzip-dev libpng-dev libonig-dev libicu-dev libxml2-dev libjpeg-dev libfreetype6-dev libssl-dev \
    libmagickwand-dev libpq-dev libldap2-dev libc-client-dev libkrb5-dev libxslt1-dev cron procps \
 && docker-php-ext-configure gd --with-freetype --with-jpeg \
 && docker-php-ext-install pdo pdo_mysql bcmath zip intl gd exif opcache pcntl \
 && pecl install redis imagick \
 && docker-php-ext-enable redis imagick \
 && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html
COPY . /var/www/html

RUN composer install --no-dev --optimize-autoloader \
 && chown -R www-data:www-data storage bootstrap/cache \
 && chmod -R 775 storage bootstrap/cache

COPY --from=assets /app/public /var/www/html/public
COPY docker/entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh
ENTRYPOINT ["/entrypoint.sh"]



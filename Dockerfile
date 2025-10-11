# =======================
# STAGE 1: Fetch source + Composer install
# =======================
FROM php:8.2-cli-bookworm AS composer_stage

ARG PM_VERSION=4.14.0
ENV APP_DIR=/app

# System deps for PHP extensions
RUN apt-get update && apt-get install -y \
    git unzip curl libzip-dev libpng-dev libonig-dev libxml2-dev libicu-dev zlib1g-dev \
 && docker-php-ext-install pdo_mysql zip bcmath intl gd \
 && rm -rf /var/lib/apt/lists/*

# Composer binary
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Get source from GitHub ZIP
WORKDIR /tmp
RUN curl -L -o pm.zip "https://codeload.github.com/ProcessMaker/processmaker/zip/refs/tags/v${PM_VERSION}" \
 && unzip pm.zip \
 && mv processmaker-${PM_VERSION} ${APP_DIR} \
 && rm -f pm.zip

# Install PHP deps (no-dev, optimized)
WORKDIR ${APP_DIR}
RUN composer install --no-dev --prefer-dist --optimize-autoloader \
 && php artisan package:discover || true

# =======================
# STAGE 2: Node build
# =======================
FROM node:18-alpine AS node_stage
ENV APP_DIR=/app
WORKDIR ${APP_DIR}
COPY --from=composer_stage ${APP_DIR}/ ${APP_DIR}/

# اگر پروژه اسکریپت build ندارد، fall back به dev
RUN npm ci || npm install \
 && (npm run build || npm run prod || npm run dev)

# =======================
# STAGE 3: Runtime (nginx + php-fpm + supervisord)
# =======================
FROM debian:bookworm-slim

ENV APP_DIR=/var/www/html \
    PHP_FPM_LISTEN=/run/php/php-fpm.sock \
    PHP_FPM_USER=www-data \
    PHP_FPM_GROUP=www-data

# nginx + php-fpm + supervisor + php extensions
RUN apt-get update && apt-get install -y \
    nginx supervisor curl ca-certificates \
    php8.2-fpm php8.2-cli php8.2-mysql php8.2-xml php8.2-mbstring php8.2-zip \
    php8.2-curl php8.2-gd php8.2-intl php8.2-bcmath \
 && rm -rf /var/lib/apt/lists/*

# App code (built)
WORKDIR ${APP_DIR}
COPY --from=node_stage /app/ ${APP_DIR}/

# Configs
COPY build-files/nginx.conf /etc/nginx/nginx.conf
COPY build-files/site.conf  /etc/nginx/conf.d/default.conf
COPY build-files/supervisord.conf /etc/supervisor/conf.d/supervisord.conf
COPY build-files/laravel-echo-server.json ${APP_DIR}/laravel-echo-server.json
COPY build-files/init.sh /usr/local/bin/init.sh
RUN chmod +x /usr/local/bin/init.sh

# PHP-FPM sock tunings
RUN sed -i "s|listen = .*$|listen = ${PHP_FPM_LISTEN}|g" /etc/php/8.2/fpm/pool.d/www.conf \
 && sed -i "s|;listen.owner = www-data|listen.owner = ${PHP_FPM_USER}|g" /etc/php/8.2/fpm/pool.d/www.conf \
 && sed -i "s|;listen.group = www-data|listen.group = ${PHP_FPM_GROUP}|g" /etc/php/8.2/fpm/pool.d/www.conf \
 && sed -i "s|pm.max_children = 5|pm.max_children = 20|g" /etc/php/8.2/fpm/pool.d/www.conf

# Permissions
RUN chown -R www-data:www-data ${APP_DIR} \
 && mkdir -p ${APP_DIR}/storage ${APP_DIR}/bootstrap/cache \
 && chown -R www-data:www-data ${APP_DIR}/storage ${APP_DIR}/bootstrap/cache

EXPOSE 80 6001
STOPSIGNAL SIGTERM
CMD ["/usr/local/bin/init.sh"]

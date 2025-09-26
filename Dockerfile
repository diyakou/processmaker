# deploy/Dockerfile.app
FROM php:8.3-fpm

# زمان / لوکال
ENV TZ=UTC
RUN ln -snf /usr/share/zoneinfo/$TZ /etc/localtime && echo $TZ > /etc/timezone

# وابستگی‌های سیستمی
RUN apt-get update && apt-get install -y \
    git unzip libzip-dev libpng-dev libjpeg62-turbo-dev libfreetype6-dev \
    libonig-dev libxml2-dev libicu-dev libpq-dev libssl-dev librdkafka-dev \
    gnupg curl ca-certificates apt-transport-https lsb-release \
 && rm -rf /var/lib/apt/lists/*
RUN set -eux; \
    pecl install redis \
 && docker-php-ext-enable redis
RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
 && docker-php-ext-install -j"$(nproc)" gd zip pdo_mysql intl bcmath exif pcntl sockets

# PECL rdkafka
RUN pecl install rdkafka \
 && docker-php-ext-enable rdkafka

# composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Node.js 18
RUN curl -fsSL https://deb.nodesource.com/setup_18.x | bash - \
 && apt-get update && apt-get install -y nodejs \
 && rm -rf /var/lib/apt/lists/*

# docker-cli داخل کانتینر (برای استفاده از docker.sock)
RUN curl -fsSL https://download.docker.com/linux/debian/gpg | gpg --dearmor -o /usr/share/keyrings/docker.gpg \
 && echo "deb [arch=$(dpkg --print-architecture) signed-by=/usr/share/keyrings/docker.gpg] https://download.docker.com/linux/debian $(lsb_release -cs) stable" > /etc/apt/sources.list.d/docker.list \
 && apt-get update && apt-get install -y docker-ce-cli \
 && rm -rf /var/lib/apt/lists/*

# پوشه کاری
WORKDIR /var/www/html

# php.ini پایه
RUN { \
  echo "memory_limit=512M"; \
  echo "upload_max_filesize=20M"; \
  echo "post_max_size=20M"; \
  echo "max_execution_time=300"; \
} > /usr/local/etc/php/conf.d/pm.ini

# Git: رفع "dubious ownership" برای مسیر پروژه
RUN git config --system --add safe.directory /var/www/html

# یوزر www-data دسترسی داشته باشد
RUN chown -R www-data:www-data /var/www/html
USER www-data

# php-fpm اجرا می‌شود

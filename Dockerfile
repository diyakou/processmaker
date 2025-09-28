# ---------- Stage 1: Frontend build (Laravel Mix) ----------
FROM node:18-alpine AS frontend
WORKDIR /app

# ابزارهای لازم برای بیلد بعضی پکیج‌ها
RUN apk add --no-cache bash git python3 make g++

# فقط فایل‌های لازم برای نصب و بیلد را کپی کن (کش بهتر عمل کند)
COPY package.json package-lock.json* yarn.lock* ./
COPY webpack.mix.js webpack-login.mix.js ./
COPY public ./public

COPY resources ./resources

# نصب (devDependencies هم نصب شود چون mix در dev است)
RUN npm ci || npm install

# اجرای بیلد (اگر یکی از فایل‌های mix را نداری، آن بخش را حذف کن)
RUN npx mix --production && npx mix --mix-config=webpack-login.mix.js --production

# ---------- Stage 2: PHP app ----------
FROM php:8.3-fpm

# زمان / locale
ENV TZ=UTC
RUN ln -snf /usr/share/zoneinfo/$TZ /etc/localtime && echo $TZ > /etc/timezone

# وابستگی‌های سیستمی
RUN apt-get update && apt-get install -y \
    git unzip libzip-dev libpng-dev libjpeg62-turbo-dev libfreetype6-dev \
    libonig-dev libxml2-dev libicu-dev libpq-dev libssl-dev librdkafka-dev \
    gnupg curl ca-certificates apt-transport-https lsb-release \
 && rm -rf /var/lib/apt/lists/*

# اکستنشن‌های PHP (شامل phpredis)
RUN set -eux; \
    pecl install redis rdkafka; \
    docker-php-ext-enable redis rdkafka
RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
 && docker-php-ext-install -j"$(nproc)" gd zip pdo_mysql intl bcmath exif pcntl sockets

# composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# (اختیاریِ قبلی) docker-cli داخل کانتینر — اگر لازم داری نگه دار؛ وگرنه حذفش کن
# RUN curl -fsSL https://download.docker.com/linux/debian/gpg | gpg --dearmor -o /usr/share/keyrings/docker.gpg \
#  && echo "deb [arch=$(dpkg --print-architecture) signed-by=/usr/share/keyrings/docker.gpg] https://download.docker.com/linux/debian $(lsb_release -cs) stable" > /etc/apt/sources.list.d/docker.list \
#  && apt-get update && apt-get install -y docker-ce-cli \
#  && rm -rf /var/lib/apt/lists/*

# php.ini پایه
RUN { \
  echo "memory_limit=512M"; \
  echo "upload_max_filesize=20M"; \
  echo "post_max_size=20M"; \
  echo "max_execution_time=300"; \
} > /usr/local/etc/php/conf.d/pm.ini

# مسیر کاری
WORKDIR /var/www/html

# رفع "dubious ownership" برای git
RUN git config --system --add safe.directory /var/www/html

# کپی کل پروژه
COPY . .

# کپی خروجی فرانت‌اند از استیج frontend
COPY --from=frontend /app/public/ /var/www/html/public/

# نصب Composer (Production)
# اگر موقع build به .env نیاز داری، قبلش کپی کن؛ در غیر اینصورت --no-scripts را نگه دار
RUN composer install --no-dev --prefer-dist --no-progress --no-interaction --optimize-autoloader

# دسترسی‌ها
RUN chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache
USER www-data

# php-fpm به عنوان ENTRYPOINT پیش‌فرض ایمیج php:8.3-fpm

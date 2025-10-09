#!/usr/bin/env bash
set -e

# اگر APP_KEY تزریق شده و داخل .env وجود ندارد، اضافه‌اش کن
if [ -n "${APP_KEY}" ]; then
  if [ ! -f /var/www/html/.env ] || ! grep -q "^APP_KEY=" /var/www/html/.env; then
    echo "APP_KEY=${APP_KEY}" >> /var/www/html/.env
  fi
else
  echo "WARNING: APP_KEY is empty. Laravel may fail to boot."
fi

# منتظر دیتابیس (اختیاری ولی مفید)
if [ -n "${DB_HOSTNAME}" ] || [ -n "${DB_HOST}" ]; then
  PHP_WAIT_FOR_DB=$(php -r "
  \$h=getenv('DB_HOSTNAME')?:getenv('DB_HOST')?:'pm-db';
  \$p=getenv('DB_PORT')?:3306;
  \$u=getenv('DB_USERNAME')?:'root';
  \$pw=getenv('DB_PASSWORD')?:getenv('MYSQL_ROOT_PASSWORD')?:'';
  try { new PDO('mysql:host='.\$h.';port='.\$p, \$u, \$pw); echo 'ok'; } catch (Throwable \$e) { exit(1); }")
  until [ \"$PHP_WAIT_FOR_DB\" = \"ok\" ]; do
    echo \"Waiting for database...\"
    sleep 2
    PHP_WAIT_FOR_DB=$(php -r "
      \$h=getenv('DB_HOSTNAME')?:getenv('DB_HOST')?:'pm-db';
      \$p=getenv('DB_PORT')?:3306;
      \$u=getenv('DB_USERNAME')?:'root';
      \$pw=getenv('DB_PASSWORD')?:getenv('MYSQL_ROOT_PASSWORD')?:'';
      try { new PDO('mysql:host='.\$h.';port='.\$p, \$u, \$pw); echo 'ok'; } catch (Throwable \$e) { exit(1); }")
  done
fi

# پاکسازی و ساخت کش‌ها
php artisan config:clear || true
php artisan cache:clear || true
php artisan route:clear || true
php artisan view:clear || true

# در prod می‌توانی کش بسازی
php artisan config:cache || true
php artisan route:cache || true
php artisan view:cache || true

# مهاجرت و سید (کنترل با env)
if [ "${RUN_MIGRATIONS:-true}" = "true" ]; then
  php artisan migrate --force || { echo "migrate failed"; exit 1; }
fi
if [ "${RUN_SEED:-false}" = "true" ]; then
  php artisan db:seed --force || { echo "db:seed failed"; exit 1; }
fi

exec "$@"

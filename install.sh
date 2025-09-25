# 0) پیش‌نیاز: APP_KEY و نام پروژه را در .env ست کن
DOCKER_CONFIG=${DOCKER_CONFIG:-$HOME/.docker}
mkdir -p $DOCKER_CONFIG/cli-plugins
curl -SL https://github.com/docker/compose/releases/download/v2.39.4/docker-compose-linux-x86_64 -o $DOCKER_CONFIG/cli-plugins/docker-compose

chmod +x $DOCKER_CONFIG/cli-plugins/docker-compose
apt update
apt install docker.io -y
grep -q '^APP_KEY=' .env 2>/dev/null || echo 'APP_KEY=base64:9nL9ukI8noJGcG9zyP9O0O36O9xwFzi8FQBdP5o3a8A=' >> .env
grep -q '^COMPOSE_PROJECT_NAME=' .env 2>/dev/null || echo 'COMPOSE_PROJECT_NAME=processmaker' >> .env

# 1) حذف docker-compose قدیمی و نصب Compose v2 (پلاگین رسمی)

docker compose version   # باید چیزی شبیه v2.x نشان دهد

# 2) توقف و حذف همه‌ی سرویس‌های پروژه (اگر قبلاً بالا هستند)
docker compose down -v --remove-orphans || true

# 3) حذف کانتینر/ایمیج‌های قبلی که ممکنه خراب باشند
docker rm -f pm-app pm-horizon pm-scheduler pm-web pm-db pm-redis pm-echo 2>/dev/null || true
docker rmi -f pm-app:latest 2>/dev/null || true

# 4) پاک‌سازی اضافات (اختیاری ولی مفید)
docker system prune -af --volumes

# 5) بیلد تمیز سرویس app با Dockerfile جدیدت (که pcntl و sockets دارد)
docker compose build --no-cache app

# 6) بالا آوردن app (و بعد بقیه)
docker compose up -d app
docker compose up -d db redis
docker compose up -d horizon scheduler web


apt install jq -y
jq '.license="AGPL-3.0-or-later" |
    .require["aws/aws-sdk-php"]="^3.337" |
    .require["processmaker/docker-executor-node"]="^1.1" |
    .require["processmaker/docker-executor-php"]="^1.4" |
    .require["processmaker/nayra"]="^1.12" |
    .require["processmaker/pmql"]="^1.13" |
    .require["simplesoftwareio/simple-qrcode"]="^4.0"
' /opt/processmaker/composer.json > /tmp/composer-fixed.json
docker run --rm \
  -v /opt/processmaker:/app \
  -v /tmp/composer-fixed.json:/app/composer-fixed.json:ro \
  -w /app \
  -e COMPOSER=/app/composer-fixed.json \
  composer:2 \
  sh -lc 'composer validate --no-check-publish && COMPOSER_MEMORY_LIMIT=-1 composer update --no-dev --no-interaction'

# 7) نصب Composer داخل app (اگر vendor نداری)
docker exec -it pm-app sh -lc '
  git config --global --add safe.directory /var/www/html || true

  php artisan config:clear && php artisan cache:clear && php artisan route:clear && php artisan view:clear
  php artisan migrate --force || true
'

# 8) بررسی وضعیت
docker compose ps
docker compose logs -n 80 app
docker compose logs -n 80 horizon

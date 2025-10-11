#!/usr/bin/env bash
set -euo pipefail

cd /var/www/html

size_of() {
  # اگر فایل نیست، 0
  [[ -f "$1" ]] && wc -c < "$1" || echo 0
}

# اگر .env هست و کوچیکه، بکاپ بگیر
if [[ -f .env ]] && [[ "$(size_of .env)" -lt 500 ]]; then
  mv .env ".env.backup.$(date +%s)"
fi

# اگر .env نیست یا ناقصه، بساز
if [[ ! -f .env ]] || [[ "$(size_of .env)" -lt 500 ]]; then
  cat <<'EOF' > .env
########################################
#  --- App Core ---
########################################
APP_NAME="ProcessMaker"
APP_ENV=production
APP_DEBUG=true
APP_URL=https://bpms.clickapps.ir
APP_TIMEZONE=UTC
DATE_FORMAT="m/d/Y H:i"

LOG_CHANNEL=stack
FORCE_HTTPS=true
TRUSTED_PROXIES=*

########################################
#  --- Database ---
########################################
# توجه: اگر لاراول/پروسسمیکر استفاده می‌کنی، معمولا این‌ها باید استاندارد باشند:
DB_CONNECTION=mysql
DB_HOST=mysql
DB_PORT=3306
DB_DATABASE=processmaker
DB_USERNAME=processmaker
DB_PASSWORD=secret
DB_TIMEZONE=+00:00

# Tenant/Landlord (در صورت استفاده)
DATA_DB_DRIVER=mysql
DATA_DB_HOST=mysql
DATA_DB_PORT=3306
DATA_DB_DATABASE=processmaker
DATA_DB_USERNAME=processmaker
DATA_DB_PASSWORD=secret

########################################
#  --- Redis / Queue / Cache / Session ---
########################################
CACHE_DRIVER=redis
QUEUE_CONNECTION=redis
SESSION_DRIVER=file
REDIS_CLIENT=predis
REDIS_HOST=redis
REDIS_PORT=6379
REDIS_PREFIX=
HORIZON_PREFIX=horizon:

########################################
#  --- Broadcasting / WebSockets ---
########################################
BROADCAST_DRIVER=redis
BROADCASTER_HOST=http://echo-server:6001
BROADCASTER_KEY=21a795019957dde6bcd96142e05d4b10

# اگر از Echo Server داکری استفاده می‌کنی:
PUSHER_HOST=echo-server
PUSHER_PORT=6001
PUSHER_SCHEME=http
PUSHER_TLS=false

# گزینه‌های Pusher-style (در صورت نیاز)
PUSHER_APP_ID=
PUSHER_APP_KEY=
PUSHER_APP_SECRET=
PUSHER_CLUSTER=
PUSHER_DEBUG=false

# تنظیمات لاراول Echo
LARAVEL_ECHO_SERVER_AUTH_HOST=http://nginx
LARAVEL_ECHO_SERVER_PORT=6001
LARAVEL_ECHO_SERVER_DEBUG=false

########################################
#  --- ProcessMaker Internal ---
########################################
PROCESSMAKER_SCRIPTS_HOME=/var/www/html/storage/app
PROCESSMAKER_SCRIPTS_DOCKER=/usr/bin/docker
PROCESSMAKER_SCRIPTS_DOCKER_MODE=binding
PROCESSMAKER_SCRIPTS_DOCKER_HOST=
PROCESSMAKER_SCRIPTS_DOCKER_PARAMS=
PROCESSMAKER_SCRIPTS_TIMEOUT=timeout
PROCESSMAKER_SYSTEM_SCRIPTS_TIMEOUT_SECONDS=300
DOCKER_SHARED_MEMORY=256m
CUSTOM_EXECUTORS=false
EOF
fi

# اطمینان از داشتن APP_KEY
if ! grep -q '^APP_KEY=base64:' .env; then
  php artisan key:generate --force || true
fi

composer run-script post-autoload-dump || true
php artisan package:discover || true
php artisan config:clear || true
php artisan optimize || true

exec php-fpm

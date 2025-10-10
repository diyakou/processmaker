#!/usr/bin/env bash
set -euo pipefail

cd /var/www/html

# Backup existing .env if it exists and is small (incomplete)
if [ -f .env ] && [ $(wc -c < .env) -lt 500 ]; then
  mv .env .env.backup.$(date +%s)
fi

# Create complete .env if it doesn't exist or is incomplete
if [ ! -f .env ] || [ $(wc -c < .env) -lt 500 ]; then
  cat > .env <<'EOF'
APP_ENV=production
APP_DEBUG=true
APP_URL=https://bpms.clickapps.ir
APP_TIMEZONE=UTC

  
LOG_CHANNEL=stack

DB_CONNECTION=mysql
DB_HOST=processmaker-mysql-1
DB_PORT=33060
DB_DATABASE=processmaker
DB_USERNAME=processmaker
DB_PASSWORD=secret

CACHE_DRIVER=redis
SESSION_DRIVER=redis
SESSION_LIFETIME=120
SESSION_SECURE_COOKIE=true
QUEUE_CONNECTION=redis
BROADCAST_DRIVER=redis
REDIS_HOST=redis
REDIS_PORT=6379

TRUSTED_PROXIES=*
SESSION_DOMAIN=bpms.clickapps.ir
SANCTUM_STATEFUL_DOMAINS=bpms.clickapps.ir

FORCE_HTTPS=true

PROCESSMAKER_SCRIPTS_HOME=/var/www/html/storage/app
PROCESSMAKER_SCRIPTS_DOCKER=/usr/bin/docker
PROCESSMAKER_SCRIPTS_DOCKER_MODE=binding
PROCESSMAKER_SCRIPTS_DOCKER_HOST=
PROCESSMAKER_SCRIPTS_DOCKER_PARAMS=
PROCESSMAKER_SCRIPTS_TIMEOUT=timeout
PROCESSMAKER_SYSTEM_SCRIPTS_TIMEOUT_SECONDS=300

L5_SWAGGER_GENERATE_ALWAYS=false

# Realtime/Websockets via Redis + Echo (proxied through nginx)
PUSHER_HOST=
PUSHER_PORT=6001
PUSHER_SCHEME=http
PUSHER_TLS=false

# Nayra / Message broker (optional)
MESSAGE_BROKER_DRIVER=default
EOF
fi

# Ensure key exists
if ! grep -q "^APP_KEY=base64:" .env; then
  php artisan key:generate --force || true
fi

composer run-script post-autoload-dump || true
php artisan package:discover || true
php artisan config:clear || true
php artisan optimize || true

exec php-fpm



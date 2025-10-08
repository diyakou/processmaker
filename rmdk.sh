#!/usr/bin/env bash
# cleanup_docker_pm.sh
# Usage: ./cleanup_docker_pm.sh [PROJECT_DIR]
# پیش‌فرض: پوشه جاری

set -euo pipefail

PROJECT_DIR="/opt/processmaker"

echo "==> Using project dir: $PROJECT_DIR"
if [ ! -f "$PROJECT_DIR/docker-compose.yml" ] && [ ! -f "$PROJECT_DIR/docker-compose.yaml" ]; then
  echo "docker-compose.yml not found in $PROJECT_DIR"
  exit 1
fi

# 1) Stop and remove compose services, volumes, and local images
echo "==> docker compose down -v --rmi local --remove-orphans"
(
  cd "$PROJECT_DIR"
  docker compose down -v --rmi local --remove-orphans || true
)

# 2) Remove known containers with pm- prefix (if any)
echo "==> Removing pm-* containers (if any)"
docker ps -a --format '{{.ID}} {{.Names}}' | awk '/ pm-(web|app|horizon|echo|scheduler|redis|db)$/ {print $1}' | xargs -r docker rm -f

# 3) Remove known images (pm-app:latest) if remain
echo "==> Removing pm-app:latest image (if exists)"
docker images --format '{{.Repository}}:{{.Tag}} {{.ID}}' | awk '/^pm-app:latest / {print $2}' | xargs -r docker rmi -f

# 4) Prune dangling images, volumes, builders
echo "==> docker image prune -f"
docker image prune -f || true

echo "==> docker volume prune -f"
docker volume prune -f || true

echo "==> docker builder prune -f"
docker builder prune -f || true

echo "==> Done."
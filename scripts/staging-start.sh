#!/usr/bin/env bash

set -euo pipefail

project_dir="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
staging_db="${STAGING_DB:-${project_dir}/backend/database/staging.sqlite}"
gateway_host="${STAGING_HOST:-127.0.0.1}"
gateway_port="${STAGING_PORT:-8080}"
backend_port="${STAGING_BACKEND_PORT:-8081}"
public_url="${STAGING_PUBLIC_URL:-http://${gateway_host}:${gateway_port}}"
backend_log="${project_dir}/backend/storage/logs/staging-server.log"

if [[ ! -f "${staging_db}" ]]; then
  echo "Staging database is missing. Run scripts/staging-prepare.sh first." >&2
  exit 1
fi

if [[ ! -f "${project_dir}/frontend/dist/index.html" ]]; then
  echo "Staging frontend is missing. Run scripts/staging-prepare.sh first." >&2
  exit 1
fi

cleanup() {
  if [[ -n "${backend_pid:-}" ]]; then
    kill "${backend_pid}" 2>/dev/null || true
    wait "${backend_pid}" 2>/dev/null || true
  fi
}
trap cleanup EXIT INT TERM

cd "${project_dir}/backend"
APP_ENV=staging \
APP_DEBUG=false \
APP_URL="${public_url}" \
DB_CONNECTION=sqlite \
DB_DATABASE="${staging_db}" \
SESSION_COOKIE=lams_staging_session \
SESSION_SECURE_COOKIE="${SESSION_SECURE_COOKIE:-false}" \
php artisan serve --host=127.0.0.1 --port="${backend_port}" >"${backend_log}" 2>&1 &
backend_pid=$!

backend_ready=false
for _ in {1..30}; do
  if curl --fail --silent "http://127.0.0.1:${backend_port}/up" >/dev/null; then
    backend_ready=true
    break
  fi
  sleep 0.2
done

if [[ "${backend_ready}" != true ]]; then
  echo "Laravel failed to start. See ${backend_log}" >&2
  exit 1
fi

cd "${project_dir}"
STAGING_HOST="${gateway_host}" \
STAGING_PORT="${gateway_port}" \
STAGING_BACKEND_URL="http://127.0.0.1:${backend_port}" \
node staging/server.mjs

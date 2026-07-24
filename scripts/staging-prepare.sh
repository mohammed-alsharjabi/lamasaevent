#!/usr/bin/env bash

set -euo pipefail

project_dir="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
legacy_dir="${LEGACY_DIST:-}"
staging_db="${STAGING_DB:-${project_dir}/backend/database/staging.sqlite}"

if [[ -z "${legacy_dir}" || ! -d "${legacy_dir}" ]]; then
  echo "Set LEGACY_DIST to the immutable legacy build directory." >&2
  exit 1
fi

if [[ ! -f "${legacy_dir}/sitemap.xml" ]]; then
  echo "LEGACY_DIST does not contain sitemap.xml." >&2
  exit 1
fi

mkdir -p "$(dirname "${staging_db}")"
if [[ ! -e "${staging_db}" ]]; then
  install -m 600 /dev/null "${staging_db}"
fi

export APP_ENV=staging
export APP_DEBUG=false
export DB_CONNECTION=sqlite
export DB_DATABASE="${staging_db}"
export SESSION_COOKIE=lams_staging_session

cd "${project_dir}/backend"
php artisan migrate --force
php artisan legacy:import \
  --legacy="${legacy_dir}" \
  --manifest="${project_dir}/route-manifest.json"

if [[ -n "${ADMIN_EMAIL:-}" && -n "${ADMIN_PASSWORD:-}" ]]; then
  php artisan db:seed --force
else
  echo "Admin seed skipped. Set ADMIN_EMAIL and ADMIN_PASSWORD to create the staging administrator."
fi

php artisan content:export

cd "${project_dir}/frontend"
PATH="/usr/local/bin:/usr/bin:/bin:${PATH}" npm ci
PATH="/usr/local/bin:/usr/bin:/bin:${PATH}" npm run check
PATH="/usr/local/bin:/usr/bin:/bin:${PATH}" npm run build
LEGACY_DIST="${legacy_dir}" PATH="/usr/local/bin:/usr/bin:/bin:${PATH}" npm run test:contract

echo
echo "Staging database: ${staging_db}"
echo "Build ready. Start it with scripts/staging-start.sh"

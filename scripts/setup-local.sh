#!/usr/bin/env bash

set -euo pipefail

project_dir="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
source "${project_dir}/scripts/runtime.sh"
lams_use_node
lams_require_backend_tools

cd "${project_dir}/backend"
composer install --no-interaction

if [[ ! -f .env ]]; then
  cp .env.example .env
fi

if [[ ! -f database/database.sqlite ]]; then
  install -m 600 /dev/null database/database.sqlite
fi

if ! grep -Eq '^APP_KEY=base64:.+' .env; then
  php artisan key:generate
fi

php artisan migrate --force
php artisan db:seed --force
php artisan storage:link --force

legacy_dir="$(php -r 'require "vendor/autoload.php"; $app=require "bootstrap/app.php"; $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap(); echo (string) config("recovery.legacy_dist");')"
if [[ -n "${legacy_dir}" && -d "${legacy_dir}" ]]; then
  php artisan legacy:inspect
  php artisan legacy:import
  php artisan legacy:verify
  php artisan content:export
else
  echo "Legacy import skipped: set LEGACY_DIST in backend/.env."
fi

cd "${project_dir}/frontend"
npm ci
npx playwright install chromium
npm run check
npm run build

echo
echo "Local setup complete."
echo "Admin: http://127.0.0.1:8000/admin"
echo "Site:  http://127.0.0.1:4321"

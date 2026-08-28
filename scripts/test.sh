#!/usr/bin/env bash

set -euo pipefail

project_dir="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
source "${project_dir}/scripts/runtime.sh"
lams_use_node

cd "${project_dir}/backend"
php artisan optimize:clear
vendor/bin/pint --test
vendor/bin/phpstan analyse --memory-limit=1G --no-progress
legacy_dir="$(php -r 'require "vendor/autoload.php"; $app=require "bootstrap/app.php"; $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap(); echo (string) config("recovery.legacy_dist");')"

cd "${project_dir}/frontend"
npm run check
npm run build

cd "${project_dir}/backend"
php artisan test

cd "${project_dir}/frontend"
if [[ -n "${legacy_dir}" && -f "${legacy_dir}/sitemap.xml" ]]; then
  LEGACY_DIST="${legacy_dir}" npm run test:contract
else
  npm run test:contract
fi
npm run test:e2e

node "${project_dir}/scripts/check-links.mjs"

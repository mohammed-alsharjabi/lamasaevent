#!/usr/bin/env bash

set -euo pipefail

project_dir="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
source "${project_dir}/scripts/runtime.sh"
lams_use_node

cd "${project_dir}/backend"
vendor/bin/pint --test
vendor/bin/phpstan analyse --memory-limit=1G --no-progress
php artisan test

cd "${project_dir}/frontend"
npm run check
npm run build
npm run test:contract
npm run test:e2e

node "${project_dir}/scripts/check-links.mjs"

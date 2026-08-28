#!/usr/bin/env bash

set -euo pipefail

project_dir="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
source "${project_dir}/scripts/runtime.sh"
lams_use_node

cd "${project_dir}/backend"
php artisan content:export

cd "${project_dir}/frontend"
npm run check
npm run build

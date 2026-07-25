#!/usr/bin/env bash

set -euo pipefail

project_dir="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"

cd "${project_dir}/backend"
php artisan legacy:verify

cd "${project_dir}"
bash scripts/test.sh
bash scripts/visual-verify.sh

echo "Local verification completed successfully."

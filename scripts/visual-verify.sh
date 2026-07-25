#!/usr/bin/env bash

set -euo pipefail

project_dir="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
source "${project_dir}/scripts/runtime.sh"
lams_use_node

legacy_dir="$(cd "${project_dir}/backend" && php -r 'require "vendor/autoload.php"; $app=require "bootstrap/app.php"; $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap(); echo (string) config("recovery.legacy_dist");')"
if [[ -z "${legacy_dir}" || ! -f "${legacy_dir}/sitemap.xml" ]]; then
  echo "Visual comparison requires LEGACY_DIST in backend/.env." >&2
  exit 1
fi

curl --fail --silent http://127.0.0.1:4321/ >/dev/null || {
  LAMS_OPEN_BROWSER=false bash "${project_dir}/scripts/dev.sh"
}

visual_log="${project_dir}/backend/storage/logs/legacy-visual-server.log"
cd "${legacy_dir}"
php -S 127.0.0.1:4174 >"${visual_log}" 2>&1 &
legacy_pid=$!

cleanup() {
  if kill -0 "${legacy_pid}" 2>/dev/null; then
    kill "${legacy_pid}"
  fi
}
trap cleanup EXIT INT TERM

for _ in {1..40}; do
  curl --fail --silent http://127.0.0.1:4174/ >/dev/null && break
  sleep 0.15
done

cd "${project_dir}/frontend"
LEGACY_BASE_URL=http://127.0.0.1:4174 \
NEW_BASE_URL=http://127.0.0.1:4321 \
npm run test:visual

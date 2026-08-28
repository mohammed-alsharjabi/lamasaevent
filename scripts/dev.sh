#!/usr/bin/env bash

set -euo pipefail

project_dir="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
run_dir="${project_dir}/.run"
source "${project_dir}/scripts/runtime.sh"
lams_use_node
lams_require_backend_tools

mkdir -p "${run_dir}" "${project_dir}/backend/storage/logs"
bash "${project_dir}/scripts/stop-dev.sh"

cd "${project_dir}/backend"
nohup php artisan serve --host=127.0.0.1 --port=8000 \
  >>"${project_dir}/backend/storage/logs/local-server.log" 2>&1 &
backend_pid=$!
printf '%s\n' "${backend_pid}" >"${run_dir}/backend.pid"

nohup php artisan queue:work --sleep=1 --tries=3 --timeout=180 \
  >>"${project_dir}/backend/storage/logs/local-queue.log" 2>&1 &
queue_pid=$!
printf '%s\n' "${queue_pid}" >"${run_dir}/queue.pid"

cd "${project_dir}/frontend"
frontend_output="$(
  env CMS_API_URL=http://127.0.0.1:8000 CMS_ALLOW_STATIC_FALLBACK=true \
    ./node_modules/.bin/astro dev \
    --host 127.0.0.1 \
    --port 4321 \
    --background \
    --force \
    --json 2>&1
)"
printf '%s\n' "${frontend_output}" >>"${project_dir}/backend/storage/logs/local-frontend.log"
frontend_pid="$(printf '%s\n' "${frontend_output}" | sed -n 's/.*(pid \([0-9][0-9]*\)).*/\1/p' | tail -1)"
if [[ -z "${frontend_pid}" ]]; then
  echo "Could not determine the Astro dev server PID." >&2
  exit 1
fi
printf '%s\n' "${frontend_pid}" >"${run_dir}/frontend.pid"

backend_ready=false
frontend_ready=false
for _ in {1..60}; do
  curl --fail --silent http://127.0.0.1:8000/up >/dev/null && backend_ready=true
  curl --fail --silent http://127.0.0.1:4321/ >/dev/null && frontend_ready=true
  if [[ "${backend_ready}" == true && "${frontend_ready}" == true ]]; then
    break
  fi
  sleep 0.25
done

if [[ "${backend_ready}" != true || "${frontend_ready}" != true ]]; then
  echo "Local services did not become ready. Check backend/storage/logs/local-*.log." >&2
  exit 1
fi

echo "Site:  http://127.0.0.1:4321"
echo "Admin: http://127.0.0.1:8000/admin"
echo "Queue worker PID: ${queue_pid}"

if [[ "${LAMS_OPEN_BROWSER:-true}" == "true" ]] && command -v open >/dev/null; then
  open http://127.0.0.1:4321/
  open http://127.0.0.1:8000/admin
fi

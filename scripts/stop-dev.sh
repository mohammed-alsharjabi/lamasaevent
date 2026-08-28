#!/usr/bin/env bash

set -euo pipefail

project_dir="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
run_dir="${project_dir}/.run"
source "${project_dir}/scripts/runtime.sh"

stop_process() {
  local name="$1"
  local pid_file="${run_dir}/${name}.pid"
  local pid
  local process_cwd

  [[ -f "${pid_file}" ]] || return 0
  pid="$(tr -dc '0-9' <"${pid_file}")"

  if [[ -n "${pid}" ]] && kill -0 "${pid}" 2>/dev/null; then
    process_cwd="$(lsof -a -p "${pid}" -d cwd -Fn 2>/dev/null | sed -n 's/^n//p' | head -1)"
    if [[ "${process_cwd}" == "${project_dir}"* ]]; then
      kill "${pid}"
      for _ in {1..20}; do
        kill -0 "${pid}" 2>/dev/null || break
        sleep 0.1
      done
    else
      echo "Skipped PID ${pid}: it no longer belongs to this project." >&2
    fi
  fi

  unlink "${pid_file}"
}

if [[ -x "${project_dir}/frontend/node_modules/.bin/astro" ]]; then
  lams_use_node
  (
    cd "${project_dir}/frontend"
    ./node_modules/.bin/astro dev stop >/dev/null 2>&1 || true
  )
fi
stop_process frontend
stop_process queue
stop_process backend

echo "Local Lamasaevent processes stopped."

#!/usr/bin/env bash

lams_use_node() {
  local candidate
  local version
  local major
  local task_user

  task_user="$(id -un)"
  for candidate in \
    "${LAMS_NODE_BIN:-}" \
    "$(command -v node 2>/dev/null || true)" \
    "/opt/homebrew/bin/node" \
    "/usr/local/bin/node" \
    "/Users/${task_user}/.cache/codex-runtimes/codex-primary-runtime/dependencies/node/bin/node"
  do
    [[ -n "${candidate}" && -x "${candidate}" ]] || continue
    version="$("${candidate}" --version 2>/dev/null || true)"
    major="${version#v}"
    major="${major%%.*}"

    if [[ "${major}" =~ ^[0-9]+$ ]] && (( major >= 22 )); then
      export PATH="$(dirname "${candidate}"):${PATH}"
      return 0
    fi
  done

  echo "Node.js 22.12 or newer is required for Astro 7. Set LAMS_NODE_BIN." >&2
  return 1
}

lams_require_backend_tools() {
  command -v php >/dev/null || {
    echo "PHP 8.2 or newer is required." >&2
    return 1
  }
  command -v composer >/dev/null || {
    echo "Composer is required." >&2
    return 1
  }
}

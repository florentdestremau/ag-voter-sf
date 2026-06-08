#!/usr/bin/env sh
# Wrapper for the embedded FrankenPHP binary.
# Discovers the deterministic embed extract path, exposes it as
# $AG_VOTER_EMBED_DIR so Caddyfile can point `root` at it, then launches.
set -e

SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
BIN="${SCRIPT_DIR}/ag-voter"

# Trigger embed extraction (deterministic per binary, idempotent).
"${BIN}" version >/dev/null

# Find the matching extract directory.
EMBED_DIR="$(ls -dt /tmp/frankenphp_*/ 2>/dev/null | head -1)"
if [ -z "${EMBED_DIR}" ] || [ ! -d "${EMBED_DIR}public" ]; then
    echo "✗ Could not locate FrankenPHP embed extract under /tmp/frankenphp_*/" >&2
    exit 1
fi
EMBED_DIR="${EMBED_DIR%/}"

export AG_VOTER_EMBED_DIR="${EMBED_DIR}"
export APP_CACHE_DIR="${APP_CACHE_DIR:-${SCRIPT_DIR}/var/cache}"
export APP_LOG_DIR="${APP_LOG_DIR:-${SCRIPT_DIR}/var/log}"
export SERVER_NAME="${SERVER_NAME:-:8080}"
export MERCURE_JWT_SECRET="${MERCURE_JWT_SECRET:-!ChangeThisMercureHubJWTSecretKey!}"

mkdir -p "${APP_CACHE_DIR}" "${APP_LOG_DIR}" "${SCRIPT_DIR}/data"

cd "${SCRIPT_DIR}"
exec "${BIN}" run --config Caddyfile "$@"

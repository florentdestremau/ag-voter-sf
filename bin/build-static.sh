#!/usr/bin/env bash
# Build a self-contained Linux x86_64 binary embedding the Symfony app.
# Output: dist/ag-voter
set -euo pipefail

cd "$(dirname "$0")/.."

OUT_DIR="dist"
OUT_BIN="${OUT_DIR}/ag-voter"

mkdir -p "${OUT_DIR}"

echo "→ Building static binary via Dockerfile.static (this can take a while)..."
DOCKER_BUILDKIT=1 docker build \
    --file Dockerfile.static \
    --target export \
    --platform linux/amd64 \
    --output "type=local,dest=${OUT_DIR}" \
    .

for f in "${OUT_BIN}" "${OUT_DIR}/Caddyfile" "${OUT_DIR}/run.sh"; do
    if [[ ! -f "${f}" ]]; then
        echo "✗ Build finished but ${f} is missing." >&2
        exit 1
    fi
done

chmod +x "${OUT_BIN}" "${OUT_DIR}/run.sh"
echo "✓ Binary ready: ${OUT_BIN} ($(du -h "${OUT_BIN}" | cut -f1))"
echo "  Caddyfile:    ${OUT_DIR}/Caddyfile"
echo "  Wrapper:      ${OUT_DIR}/run.sh"
echo
echo "Usage:"
echo "  ${OUT_DIR}/ag-voter php-cli bin/console doctrine:migrations:migrate --no-interaction"
echo "  SERVER_NAME=':8080' ${OUT_DIR}/run.sh"

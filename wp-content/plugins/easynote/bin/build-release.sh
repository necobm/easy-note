#!/usr/bin/env bash
#
# Build a WordPress.org-ready distribution ZIP for EasyNote.
#
# Produces dist/easynote.zip containing a plugin that runs with NO build step:
# the compiled app/build/ is included, alongside the human-readable app/src/ and
# build instructions (WordPress.org requires source for compiled JS). node_modules,
# tests and dev tooling are excluded.
#
# Usage:  bash bin/build-release.sh
# Requires: Node/npm on the host, and the `zip` command.

set -euo pipefail

PLUGIN_SLUG="easynote"
PLUGIN_DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )/.." && pwd )"
DIST_DIR="${PLUGIN_DIR}/dist"
STAGE_DIR="${DIST_DIR}/${PLUGIN_SLUG}"

echo "==> Building the front-end app"
( cd "${PLUGIN_DIR}/app" && npm ci --legacy-peer-deps && npm run build )

echo "==> Staging plugin files"
rm -rf "${DIST_DIR}"
mkdir -p "${STAGE_DIR}"

# Copy the whole plugin, then prune everything that must not ship.
rsync -a \
	--exclude '.git' \
	--exclude 'dist' \
	--exclude 'tests' \
	--exclude 'phpunit.xml.dist' \
	--exclude 'bin' \
	--exclude 'app/node_modules' \
	--exclude 'app/.gitignore' \
	--exclude '**/.DS_Store' \
	"${PLUGIN_DIR}/" "${STAGE_DIR}/"

# Sanity checks: the build output and its source must both be present.
for required in \
	"${STAGE_DIR}/app/build/index.js" \
	"${STAGE_DIR}/app/build/index.asset.php" \
	"${STAGE_DIR}/app/build/public.js" \
	"${STAGE_DIR}/app/src/index.js" \
	"${STAGE_DIR}/readme.txt" \
	"${STAGE_DIR}/LICENSE"; do
	if [ ! -e "${required}" ]; then
		echo "ERROR: missing ${required} in staged package" >&2
		exit 1
	fi
done

echo "==> Creating ZIP"
( cd "${DIST_DIR}" && zip -rq "${PLUGIN_SLUG}.zip" "${PLUGIN_SLUG}" )

echo "==> Done: ${DIST_DIR}/${PLUGIN_SLUG}.zip"

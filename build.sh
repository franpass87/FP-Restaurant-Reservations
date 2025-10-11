#!/usr/bin/env bash
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
PLUGIN_MAIN="${SCRIPT_DIR}/fp-restaurant-reservations.php"
SLUG="$(basename "${PLUGIN_MAIN}" .php)"
BUILD_ROOT="${SCRIPT_DIR}/build"
TARGET_DIR="${BUILD_ROOT}/${SLUG}"

BUMP_TYPE="patch"
SET_VERSION=""
ZIP_NAME=""

print_usage() {
    cat <<USAGE
Usage: bash build.sh [--set-version=X.Y.Z] [--bump=patch|minor|major] [--zip-name=name.zip]
USAGE
}

for arg in "$@"; do
    case "$arg" in
        --set-version=*)
            SET_VERSION="${arg#*=}"
            ;;
        --bump=*)
            BUMP_TYPE="${arg#*=}"
            ;;
        --zip-name=*)
            ZIP_NAME="${arg#*=}"
            ;;
        --help|-h)
            print_usage
            exit 0
            ;;
        *)
            echo "Unknown argument: $arg" >&2
            print_usage >&2
            exit 1
            ;;
    esac
done

if [[ ! -f "${PLUGIN_MAIN}" ]]; then
    echo "Plugin main file not found at ${PLUGIN_MAIN}" >&2
    exit 1
fi

if [[ -n "${SET_VERSION}" ]]; then
    php "${SCRIPT_DIR}/tools/bump-version.php" --set="${SET_VERSION}"
else
    php "${SCRIPT_DIR}/tools/bump-version.php" --"${BUMP_TYPE}"
fi

npm install --silent
npm run build

composer install --no-dev --prefer-dist --no-interaction --optimize-autoloader
composer dump-autoload -o --classmap-authoritative

rm -rf "${TARGET_DIR}"
mkdir -p "${TARGET_DIR}"

RSYNC_EXCLUDES=(
    "--exclude=.git"
    "--exclude=.github"
    "--exclude=tests"
    "--exclude=docs"
    "--exclude=node_modules"
    "--exclude=*.md"
    "--exclude=.idea"
    "--exclude=.vscode"
    "--exclude=build"
    "--exclude=.gitattributes"
    "--exclude=.gitignore"
    "--exclude=package.json"
    "--exclude=package-lock.json"
    "--exclude=phpcs.xml"
    "--exclude=phpstan.neon"
    "--exclude=vite.config.js"
    "--exclude=.codex-state.json"
    "--exclude=.rebuild-state.json"
    "--exclude=.eslintrc.cjs"
    "--exclude=eslint.config.js"
    "--exclude=.prettierrc.json"
    "--exclude=build.sh"
    "--exclude=scripts"
    "--exclude=tools"
    "--exclude=docker-compose.yml"
    "--exclude=AUDIT"
    "--exclude=assets/js/fe"
    "--exclude=*.zip"
    "--exclude=*.log"
)

rsync -a --delete "${RSYNC_EXCLUDES[@]}" "${SCRIPT_DIR}/" "${TARGET_DIR}/"

TIMESTAMP="$(date +%Y%m%d%H%M)"
DEFAULT_ZIP_NAME="${SLUG}-${TIMESTAMP}.zip"
ZIP_PATH="${BUILD_ROOT}/${ZIP_NAME:-${DEFAULT_ZIP_NAME}}"

( cd "${BUILD_ROOT}" && zip -r "${ZIP_PATH##*/}" "${SLUG}" > /dev/null )

FINAL_VERSION=$(php -r '$pattern = "/^(\\s*\\*\\s*Version:\\s*)([^\\r\\n]+)/mi"; $file = "'"${PLUGIN_MAIN}"'"; $contents = file_get_contents($file); if (preg_match($pattern, $contents, $matches)) { echo trim($matches[2]); } else { exit(1); }')

cat <<INFO
Build completed.
Version: ${FINAL_VERSION}
ZIP: ${ZIP_PATH}
INFO

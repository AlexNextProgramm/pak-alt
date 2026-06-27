#!/bin/bash

# ============================================================
# install.sh — универсальный установщик проекта (Pet)
# Запускать из корня проекта: ./install.sh
# Модули установки: script/bash/*.sh
# ============================================================

set -euo pipefail

PROJECT_DIR="$(cd "$(dirname "$(readlink -f "$0")")" && pwd)"
SCRIPT_DIR="$PROJECT_DIR/script/bash"
PROJECT_NAME="$(basename "$PROJECT_DIR")"
WWW_LINK="/var/www/${PROJECT_NAME}"
LOG_FILE="$PROJECT_DIR/log/install.log"
NGINX_CONF="/etc/nginx/sites-available/${PROJECT_NAME}.conf"
MYSQL_ROOT_PASSWORD=""
PHP_FPM_SOCK=""
HAS_ADMIN=false
HAS_SITE=false
NGINX_SERVER_NAME=""
ONLY_MODULE=""

mkdir -p "$PROJECT_DIR/log" "$SCRIPT_DIR"

# shellcheck source=script/bash/lib.sh
source "$SCRIPT_DIR/lib.sh"

DB_NAME="$(normalize_db_name "$PROJECT_NAME")"

parse_install_args "$@"

cd "$PROJECT_DIR"

if [ "$ONLY_MODULE" = "ls" ]; then
    list_install_modules
    exit 0
fi

if [ -n "$ONLY_MODULE" ]; then
    log "=== Установка модуля ($ONLY_MODULE) — проект $PROJECT_NAME ==="
    log "PROJECT_DIR=$PROJECT_DIR"
    run_install_module "$ONLY_MODULE"
    exit 0
fi

log "=== Начало установки проекта ($PROJECT_NAME) ==="
log "PROJECT_DIR=$PROJECT_DIR"

for module_sh in "$SCRIPT_DIR"/*.sh; do
    module_name="$(basename "$module_sh")"
    case "$module_name" in
        lib.sh|cli.sh) continue ;;
    esac

    MODULE_TITLE=""
    MODULE_FUNC=""

    # shellcheck source=/dev/null
    source "$module_sh"

    if [ -n "$MODULE_TITLE" ] && [ -n "$MODULE_FUNC" ]; then
        run_module "$MODULE_TITLE" "$MODULE_FUNC"
    fi
done

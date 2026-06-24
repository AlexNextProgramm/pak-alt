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

for module in symlink apt nginx mysql php nginx-site nodejs database deps permissions github finish cli git; do
    # shellcheck source=/dev/null
    source "$SCRIPT_DIR/${module}.sh"
done

parse_install_args "$@"

cd "$PROJECT_DIR"

if [ -n "$ONLY_MODULE" ]; then
    log "=== Установка модуля ($ONLY_MODULE) — проект $PROJECT_NAME ==="
    log "PROJECT_DIR=$PROJECT_DIR"
    run_install_module "$ONLY_MODULE"
    exit 0
fi

log "=== Начало установки проекта ($PROJECT_NAME) ==="
log "PROJECT_DIR=$PROJECT_DIR"

run_module "Симлинк /var/www" install_symlink
run_module "Обновление apt" apt_update
run_module "Nginx (пакет)" install_nginx
run_module "MySQL 8.0" install_mysql
run_module "PHP-FPM" install_php
run_module "Node.js" install_nodejs
run_module "Git" install_git
run_module "Nginx (конфиг проекта)" configure_nginx_site
run_module "База данных и .env" setup_database
run_module "Зависимости и миграции" process_app_dependencies
run_module "Права доступа" setup_permissions
run_module "GitHub SSH" setup_github_ssh
run_module "Итог" print_summary

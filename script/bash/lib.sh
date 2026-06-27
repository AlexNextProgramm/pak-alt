#!/bin/bash

# Общие функции (подключается из install.sh и deploy.sh)

# Цвета для вывода
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
RED='\033[0;31m'
NC='\033[0m' # No Color

log() {
    local message="[$(date '+%Y-%m-%d %H:%M:%S')] $1"
    echo -e "$message"
    echo "$message" >> "$LOG_FILE"
}

log_success() {
    log "${GREEN}$1${NC}"
}

log_warning() {
    log "${YELLOW}$1${NC}"
}

log_error() {
    log "${RED}$1${NC}"
}

ask_yes_no() {
    local prompt="$1"
    local answer

    read -r -p "$prompt [y/N]: " answer
    case "${answer,,}" in
        y|yes|д|да) return 0 ;;
        *) return 1 ;;
    esac
}

run_module() {
    local title="$1"
    shift

    echo ""
    if ask_yes_no "Запустить модуль «${title}»?"; then
        log ">>> Модуль: ${title}"
        "$@"
    else
        log ">>> Пропуск модуля: ${title}"
    fi
}

command_exists() {
    command -v "$1" &>/dev/null
}

is_pkg_installed() {
    dpkg -s "$1" &>/dev/null
}

install_packages() {
    local missing=()

    for pkg in "$@"; do
        if is_pkg_installed "$pkg"; then
            log "$pkg уже установлен — пропуск."
        else
            missing+=("$pkg")
        fi
    done

    if [ ${#missing[@]} -eq 0 ]; then
        return 0
    fi

    log "Установка пакетов: ${missing[*]}..."
    sudo apt install -y "${missing[@]}"
}

ensure_service() {
    local service="$1"

    if ! systemctl list-unit-files "$service" &>/dev/null; then
        log "Сервис $service не найден."
        return 1
    fi

    sudo systemctl enable "$service" 2>/dev/null || true

    if systemctl is-active --quiet "$service" 2>/dev/null; then
        log "$service уже запущен."
    else
        sudo systemctl start "$service"
        log "$service запущен."
    fi
}

normalize_db_name() {
    local name="$1"
    name="$(echo "$name" | tr '[:upper:]' '[:lower:]')"
    name="$(echo "$name" | tr -- '-. ' '_')"
    name="$(echo "$name" | sed 's/[^a-z0-9_]/_/g')"
    name="$(echo "$name" | sed 's/__*/_/g; s/^_//; s/_$//')"
    echo "$name"
}

detect_apps() {
    HAS_ADMIN=false
    HAS_SITE=false
    [ -d "$PROJECT_DIR/admin/public" ] && HAS_ADMIN=true
    [ -d "$PROJECT_DIR/site/public" ] && HAS_SITE=true
}

# Сканирует PROJECT_DIR в поиске подпапок с composer.json или package.json
# Возвращает список app_dir:app_name через пробел
detect_app_dirs() {
    local apps=()
    local app_dir app_name

    for app_dir in "$PROJECT_DIR"/*/; do
        [ -d "$app_dir" ] || continue
        app_name="$(basename "$app_dir")"

        case "$app_name" in
            vendor|node_modules|log|migrations|service|script|inst|.git) continue ;;
        esac

        if [ -f "$app_dir/composer.json" ] || [ -f "$app_dir/package.json" ]; then
            apps+=("$app_dir:$app_name")
        fi
    done

    echo "${apps[@]}"
}

# Проверяет наличие lock-файла для директории приложения
has_lock_file() {
    local app_dir="$1"

    [ -f "$app_dir/composer.lock" ] || [ -f "$app_dir/package-lock.json" ]
}

# Установка зависимостей (composer install / npm install) если нет lock-файла
install_dependencies() {
    local app_dir="$1"
    local app_name="$2"

    if has_lock_file "$app_dir"; then
        log "lock-файл есть в $app_name — пропуск install."
        return 0
    fi

    # Composer
    if [ -f "$app_dir/composer.json" ] && [ ! -d "$app_dir/vendor" ]; then
        log "Установка composer dependencies ($app_name)..."
        (cd "$app_dir" && composer install --no-interaction) 2>&1 | tee -a "$LOG_FILE"
        log_success "composer install ($app_name): успешно"
    fi

    # npm
    if [ -f "$app_dir/package.json" ] && [ ! -d "$app_dir/node_modules" ]; then
        log "Установка npm dependencies ($app_name)..."
        (cd "$app_dir" && npm install) 2>&1 | tee -a "$LOG_FILE"
        log_success "npm install ($app_name): успешно"
    fi
}

# Обновление composer при изменении composer.json (без --with-dependencies)
update_composer() {
    local app_dir="$1"
    local app_name="$2"
    local composer_path="${app_dir#${PROJECT_DIR}/}composer.json"

    if echo "$CHANGED_FILES" | grep -q "^${composer_path}"; then
        log "Обнаружены изменения в composer.json ($app_name). Обновляем..."
        (cd "$app_dir" && composer update --no-interaction) 2>&1 | tee -a "$LOG_FILE"
        log_success "composer update ($app_name): успешно"
    else
        log "Изменений в composer.json ($app_name) нет. Пропускаем."
    fi
}

# Сборка frontend при изменении package.json или src/
build_frontend() {
    local app_dir="$1"
    local app_name="$2"
    local package_path="${app_dir#${PROJECT_DIR}/}package.json"
    local src_path="${app_name}/src/"

    if echo "$CHANGED_FILES" | grep -qE "^(${package_path}|${src_path})"; then
        log "Обнаружены изменения в $app_name/src/ или package.json. Собираем build..."
        (cd "$app_dir" && npm run build) 2>&1 | tee -a "$LOG_FILE"
        log_success "Build $app_name: успешно"
    else
        log "Изменений в $app_name/src/ нет. Пропускаем build."
    fi
}

# ═══════════════════════════════════════════════════════════
# CLI-функции (парсинг аргументов install.sh)
# ═══════════════════════════════════════════════════════════

print_install_help() {
    cat <<'EOF'
Установщик проекта (Pet)

Использование:
  ./install.sh              интерактивная установка (вопрос перед каждым модулем)
  ./install.sh -m <модуль>  установить только указанный модуль
  ./install.sh ls           список модулей
  ./install.sh -h           справка

Порядок выполнения модулей:
   1. symlink      симлинк /var/www/<проект>
   2. apt          обновление списка пакетов
   3. nginx        пакет nginx
   4. mysql        MySQL 8.0 (mysql-server-8.0)
   5. php          PHP-FPM и расширения
   6. nodejs       Node.js и npm
   7. git          Git (система контроля версий)
   8. nginx-site   конфиг nginx для проекта
   9. database     база данных и .env (алиас: db)
  10. deps         composer, npm, миграции
  11. permissions  права на assets (алиас: perms)
  12. github       настройка SSH-ключей для GitHub
  13. ollama       Ollama + модель gemma3:1b
  14. finish       итоговый вывод

Примеры:
  ./install.sh -m php
  ./install.sh -m database
  ./install.sh -m deps
EOF
}

list_install_modules() {
    local modules=()
    local module_sh module_name module_key MODULE_TITLE MODULE_FUNC

    for module_sh in "$SCRIPT_DIR"/[0-9]*.sh; do
        module_name="$(basename "$module_sh")"
        module_key="${module_name#*.}"
        module_key="${module_key%.sh}"

        MODULE_TITLE=""
        MODULE_FUNC=""

        # shellcheck source=/dev/null
        source "$module_sh"

        if [ -n "$MODULE_TITLE" ] && [ -n "$MODULE_FUNC" ]; then
            modules+=("$module_key")
        fi
    done

    echo ""
    echo "Доступные модули install.sh (в порядке выполнения):"
    echo ""

    for mod in "${modules[@]}"; do
        printf "  %-15s ./install.sh -m %s\n" "$mod" "$mod"
    done

    echo ""
    echo "Пример: ./install.sh -m mysql"
    echo ""
}

run_install_module() {
    local key="${1,,}"
    local module_file

    # Ищем файл по маске [номер]-{key}.sh
    module_file="$(find "$SCRIPT_DIR" -maxdepth 1 -name "[0-9]*-${key}.sh" -print -quit 2>/dev/null)"

    if [ -z "$module_file" ]; then
        echo "Неизвестный модуль: $1" >&2
        echo "Запустите ./install.sh ls для списка доступных модулей." >&2
        exit 1
    fi

    # shellcheck source=/dev/null
    source "$module_file"

    case "$key" in
        symlink)
            log ">>> Модуль: symlink"
            install_symlink
            ;;
        apt)
            log ">>> Модуль: apt"
            apt_update
            ;;
        nginx)
            log ">>> Модуль: nginx"
            install_nginx
            ;;
        mysql)
            log ">>> Модуль: mysql"
            install_mysql
            ;;
        php)
            log ">>> Модуль: php"
            install_php
            ;;
        git)
            log ">>> Модуль: git"
            install_git
            ;;
        nodejs|node)
            log ">>> Модуль: nodejs"
            install_nodejs
            ;;
        nginx-site|nginx_site|site)
            log ">>> Модуль: nginx-site"
            configure_nginx_site
            ;;
        database|db)
            log ">>> Модуль: database"
            setup_database
            ;;
        deps|dependencies)
            log ">>> Модуль: deps"
            process_app_dependencies
            ;;
        permissions|perms)
            log ">>> Модуль: permissions"
            setup_permissions
            ;;
        github)
            log ">>> Модуль: github"
            setup_github_ssh
            ;;
        ollama)
            log ">>> Модуль: ollama"
            setup_ollama
            ;;
        finish|summary)
            log ">>> Модуль: finish"
            print_summary
            ;;
    esac
}

parse_install_args() {
    ONLY_MODULE=""

    while getopts "m:h" opt; do
        case "$opt" in
            m) ONLY_MODULE="$OPTARG" ;;
            h)
                print_install_help
                exit 0
                ;;
            *)
                print_install_help
                exit 1
                ;;
        esac
    done
}

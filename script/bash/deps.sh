#!/bin/bash

MODULE_TITLE="Зависимости и миграции"
MODULE_FUNC="process_app_dependencies"

install_composer_deps() {
    local app_dir="$1"
    local app_name="$2"

    if [ ! -f "$app_dir/composer.json" ]; then
        return 0
    fi

    if ! command_exists composer; then
        log "composer не найден — пропуск $app_name."
        return 0
    fi

    if [ -d "$app_dir/vendor" ]; then
        log "vendor уже есть в $app_name — пропуск composer install."
        return 0
    fi

    log "Установка Composer-зависимостей ($app_name)..."
    (cd "$app_dir" && composer install --no-interaction) 2>&1 | tee -a "$LOG_FILE"
}

install_npm_deps() {
    local app_dir="$1"
    local app_name="$2"

    if [ ! -f "$app_dir/package.json" ]; then
        return 0
    fi

    if ! command_exists npm; then
        log "npm не найден — пропуск $app_name."
        return 0
    fi

    if [ -d "$app_dir/node_modules" ]; then
        log "node_modules уже есть в $app_name — пропуск npm install."
    else
        log "Установка npm-зависимостей ($app_name)..."
        (cd "$app_dir" && npm install) 2>&1 | tee -a "$LOG_FILE"
    fi

    log "Сборка frontend ($app_name)..."
    (cd "$app_dir" && npm run build) 2>&1 | tee -a "$LOG_FILE"
}

run_migrations() {
    local app_dir="$1"
    local app_name="$2"

    if [ ! -f "$app_dir/pet" ]; then
        return 0
    fi

    log "Запуск миграций ($app_name)..."
    (cd "$app_dir" && php pet migrate) 2>&1 | tee -a "$LOG_FILE"
}

process_app_dependencies() {
    local app_dir app_name

    log "Поиск приложений в $PROJECT_DIR..."

    for app_dir in "$PROJECT_DIR"/*/; do
        [ -d "$app_dir" ] || continue
        app_name="$(basename "$app_dir")"

        case "$app_name" in
            vendor|node_modules|log|migrations|service|script|inst|.git) continue ;;
        esac

        if [ ! -f "$app_dir/composer.json" ] && [ ! -f "$app_dir/package.json" ] && [ ! -f "$app_dir/pet" ]; then
            continue
        fi

        log "Обработка: $app_name"
        install_composer_deps "$app_dir" "$app_name"
        install_npm_deps "$app_dir" "$app_name"
        run_migrations "$app_dir" "$app_name"
    done
}

#!/bin/bash

print_summary() {
    detect_apps

    log "=== Установка завершена ==="
    log "PROJECT_DIR=$PROJECT_DIR"
    log "База данных: $DB_NAME"
    log "Файл окружения: $PROJECT_DIR/.env"

    if $HAS_SITE; then
        log "Сайт: http://localhost/"
    fi

    if $HAS_ADMIN && $HAS_SITE; then
        log "Админка: http://localhost/admin"
    elif $HAS_ADMIN; then
        log "Админка: http://localhost/"
    fi
}

#!/bin/bash

install_symlink() {
    log "Проверка симлинка $WWW_LINK..."

    if [ -L "$WWW_LINK" ]; then
        local current_target
        current_target="$(readlink -f "$WWW_LINK")"
        if [ "$current_target" = "$PROJECT_DIR" ]; then
            log "Симлинк $WWW_LINK уже указывает на $PROJECT_DIR."
        else
            log "Симлинк $WWW_LINK указывает на $current_target — не меняем."
        fi
    elif [ -e "$WWW_LINK" ]; then
        log "Путь $WWW_LINK существует и не является симлинком — пропуск."
    else
        sudo mkdir -p /var/www
        sudo ln -s "$PROJECT_DIR" "$WWW_LINK"
        log "Симлинк $WWW_LINK -> $PROJECT_DIR создан."
    fi
}

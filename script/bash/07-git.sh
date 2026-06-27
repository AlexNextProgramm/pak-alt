#!/bin/bash

MODULE_TITLE="Git"
MODULE_FUNC="install_git"

install_git() {
    log "Проверка Git..."
    install_packages git

    if command_exists git; then
        local git_version
        git_version="$(git --version 2>/dev/null)"
        log "Git установлен ($git_version)."
    else
        log "Git не найден после установки — проверьте вручную."
    fi
}
#!/bin/bash

install_nodejs() {
    if command_exists node || command_exists nodejs; then
        local node_version
        node_version="$(node -v 2>/dev/null || nodejs -v 2>/dev/null)"
        log "Node.js уже установлен ($node_version)."
        return 0
    fi

    install_packages nodejs npm

    if command_exists node || command_exists nodejs; then
        node_version="$(node -v 2>/dev/null || nodejs -v 2>/dev/null)"
        log "Node.js установлен ($node_version)."
    else
        log "Node.js не найден после установки — проверьте вручную."
    fi
}

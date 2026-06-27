#!/bin/bash

# ============================================================
# ollama.sh — установка Ollama и модели gemma3:1b
# ============================================================

MODULE_TITLE="Ollama + gemma3:1b"
MODULE_FUNC="setup_ollama"

OLLAMA_VERSION="0.5.13"

install_ollama() {
    if command_exists ollama; then
        log "Ollama уже установлен — пропуск."
        return 0
    fi

    log "Установка Ollama ${OLLAMA_VERSION}..."
    curl -fsSL https://ollama.com/install.sh | sh

    if ! command_exists ollama; then
        log "ОШИБКА: Ollama не установилась."
        return 1
    fi

    log "Ollama ${OLLAMA_VERSION} установлен."
}

pull_ollama_model() {
    local model="$1"

    log "Загрузка модели ${model}..."

    # Убедимся что сервис запущен
    if systemctl list-unit-files ollama.service &>/dev/null; then
        sudo systemctl enable ollama 2>/dev/null || true
        sudo systemctl start ollama 2>/dev/null || true
    fi

    # Ждём пока ollama поднимется
    for i in $(seq 1 10); do
        if ollama list &>/dev/null; then
            break
        fi
        log "Ожидание запуска Ollama (попытка $i)..."
        sleep 2
    done

    ollama pull "$model" 2>&1 | tee -a "$LOG_FILE"

    if ollama list | grep -q "$model"; then
        log "Модель ${model} успешно загружена."
    else
        log "ПРЕДУПРЕЖДЕНИЕ: модель ${model} не найдена после загрузки."
    fi
}

setup_ollama() {
    install_ollama
    pull_ollama_model "gemma3:1b"
}
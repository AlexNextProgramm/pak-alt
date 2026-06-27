#!/bin/bash

# ============================================================
# github.sh — настройка SSH-ключей для GitHub
# Генерирует ключ, добавляет в ssh-agent, показывает публичный
# ключ, ждёт подтверждения и проверяет рукопожатие.
# ============================================================

MODULE_TITLE="GitHub SSH"
MODULE_FUNC="setup_github_ssh"

SSH_DIR="$PROJECT_DIR/ssh"
SSH_KEY="$SSH_DIR/id_ed25519_github"
SSH_KEY_PUB="$SSH_KEY.pub"

setup_github_ssh() {
    log "=== Настройка SSH для GitHub ==="

    # 1. Создаём папку ./ssh
    mkdir -p "$SSH_DIR"
    chmod 700 "$SSH_DIR"

    # 2. Генерируем ключ, если его нет
    if [ -f "$SSH_KEY" ]; then
        log "SSH-ключ уже существует: $SSH_KEY"
    else
        log "Генерация SSH-ключа (ed25519)..."
        ssh-keygen -t ed25519 -f "$SSH_KEY" -N "" -C "deploy-$(hostname)-$(date +%Y%m%d)"
        log "SSH-ключ создан: $SSH_KEY"
    fi

    # 3. Добавляем ключ в ssh-agent
    eval "$(ssh-agent -s)" >/dev/null
    ssh-add "$SSH_KEY" 2>/dev/null || true

    # 4. Показываем публичный ключ
    echo ""
    echo "========================================================"
    echo "  Скопируйте этот публичный ключ в GitHub:"
    echo "  https://github.com/settings/keys"
    echo "========================================================"
    echo ""
    cat "$SSH_KEY_PUB"
    echo ""
    echo "========================================================"
    echo ""

    # 5. Ждём подтверждения от пользователя
    read -r -p "Добавьте ключ в GitHub и нажмите Enter, чтобы продолжить..."

    # 6. Проверяем рукопожатие
    log "Проверка подключения к GitHub..."
    if ssh -T git@github.com 2>&1 | grep -qi "successfully authenticated"; then
        log "✅ Подключение к GitHub успешно установлено!"
    else
        # Пробуем ещё раз с явным указанием ключа
        if ssh -T -i "$SSH_KEY" git@github.com 2>&1 | grep -qi "successfully authenticated"; then
            log "✅ Подключение к GitHub успешно установлено!"
        else
            log "⚠️  Не удалось проверить подключение. Проверьте, что ключ добавлен в GitHub."
            log "   Публичный ключ:"
            cat "$SSH_KEY_PUB"
        fi
    fi
}
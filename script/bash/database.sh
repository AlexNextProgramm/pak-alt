#!/bin/bash

MODULE_TITLE="База данных и .env"
MODULE_FUNC="setup_database"

mysql_is_available() {
    command_exists mysql && mysql_service_running
}

prompt_mysql_password() {
    if [ -n "${MYSQL_ROOT_PASSWORD:-}" ]; then
        if sudo mysql -u root -p"$MYSQL_ROOT_PASSWORD" -e "SELECT 1" &>/dev/null; then
            log "Подключение к MySQL (из MYSQL_ROOT_PASSWORD) успешно."
            return 0
        fi
        log "Пароль из MYSQL_ROOT_PASSWORD не подошёл — запрашиваем вручную."
        MYSQL_ROOT_PASSWORD=""
    fi

    while true; do
        echo ""
        echo "Для настройки базы данных нужен пароль пользователя root MySQL."
        read -r -s -p "Введите пароль root MySQL: " MYSQL_ROOT_PASSWORD
        echo ""

        if [ -z "$MYSQL_ROOT_PASSWORD" ]; then
            echo "Пароль не может быть пустым."
            continue
        fi

        if sudo mysql -u root -p"$MYSQL_ROOT_PASSWORD" -e "SELECT 1" &>/dev/null; then
            log "Подключение к MySQL успешно."
            return 0
        fi

        if sudo mysql -u root -e "SELECT 1" &>/dev/null; then
            log "MySQL без пароля — устанавливаем указанный пароль..."
            sudo mysql -e \
                "ALTER USER 'root'@'localhost' IDENTIFIED WITH mysql_native_password BY '${MYSQL_ROOT_PASSWORD}'; FLUSH PRIVILEGES;"

            if sudo mysql -u root -p"$MYSQL_ROOT_PASSWORD" -e "SELECT 1" &>/dev/null; then
                log "Пароль root MySQL установлен."
                return 0
            fi
        fi

        echo "Не удалось подключиться к MySQL с этим паролем. Попробуйте снова."
    done
}

create_root_env() {
    local env_file="$PROJECT_DIR/.env"

    if [ -f "$env_file" ]; then
        log "$env_file уже существует — пропуск."
        return 0
    fi

    log "Создание $env_file..."
    cat > "$env_file" <<EOF
DEV = 1
MIGRATE_DIR = '..[DS]migrations'
EXTERNAL_MODULE='../service'
ROUTER_DIR="[ROOT][DS]public[DS]router"
VIEW_DIR="[ROOT][DS]public[DS]view"

# база данных
DB_TYPE = mysql
DB_HOST = localhost
DB_USER = root
DB_PORT = 3306
DB_NAME = ${DB_NAME}
DB_PASSWORD = ${MYSQL_ROOT_PASSWORD}
EOF
    chmod 600 "$env_file"
    log "$env_file создан."
}

mysql_database_exists() {
    local db="$1"
    local count

    count="$(sudo mysql -u root -p"$MYSQL_ROOT_PASSWORD" -N -se \
        "SELECT COUNT(*) FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME = '${db}'" 2>/dev/null)"

    [ "$count" = "1" ]
}

setup_database() {
    if ! mysql_is_available; then
        log "MySQL не установлен или не запущен — пропуск настройки базы данных."
        return 0
    fi

    log "Настройка базы данных (имя из PROJECT_NAME: $PROJECT_NAME → $DB_NAME)..."
    prompt_mysql_password

    if mysql_database_exists "$DB_NAME"; then
        log "База данных '$DB_NAME' уже существует."
    else
        log "База данных '$DB_NAME' не найдена — создание..."
        sudo mysql -u root -p"$MYSQL_ROOT_PASSWORD" -e \
            "CREATE DATABASE \`${DB_NAME}\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
        log "База данных '$DB_NAME' создана."
    fi

    create_root_env
}

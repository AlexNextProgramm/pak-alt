#!/bin/bash

print_install_help() {
    cat <<'EOF'
Установщик проекта (Pet)

Использование:
  ./install.sh              интерактивная установка (вопрос перед каждым модулем)
  ./install.sh -m <модуль>  установить только указанный модуль
  ./install.sh -h           справка

Модули (-m):
  symlink      симлинк /var/www/<проект>
  apt          обновление списка пакетов
  nginx        пакет nginx
  mysql        MySQL 8.0 (mysql-server-8.0)
  php          PHP-FPM и расширения
  nodejs       Node.js и npm
  git          Git (система контроля версий)
  nginx-site   конфиг nginx для проекта
  database     база данных и .env (алиас: db)
  deps         composer, npm, миграции
  github       настройка SSH-ключей для GitHub
  permissions  права на assets (алиас: perms)
  ollama       Ollama + модель gemma3:1b
  finish       итоговый вывод

Примеры:
  ./install.sh -m php
  ./install.sh -m database
  ./install.sh -m deps
EOF
}

run_install_module() {
    local key="${1,,}"

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
        *)
            echo "Неизвестный модуль: $1" >&2
            echo "Доступные: symlink apt nginx mysql php nodejs git nginx-site database deps github ollama permissions finish" >&2
            exit 1
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

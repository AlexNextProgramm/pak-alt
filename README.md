# Pak-Alt

**Pak-Alt** — веб-приложение для автоматизации работы страховой медицинской организации (СМО). Система включает админ-панель для сотрудников и публичный сайт для клиентов, а также AI-модуль для парсинга данных.

---

## 📋 Логи и просмотр ошибок

### Логи приложений

Все логи проекта пишутся в директорию [`log/`](log/):

| Файл | Назначение |
|------|------------|
| [`log/admin.log`](log/admin.log) | Ошибки PHP в админ-панели |
| [`log/admin_access.log`](log/admin_access.log) | Access-лог админ-панели |
| [`log/deploy.log`](log/deploy.log) | Лог автоматического деплоя (`deploy.sh`) |
| [`log/install.log`](log/install.log) | Лог установщика (`install.sh`) |

**Просмотр в реальном времени:**

```bash
# Ошибки админки
tail -f log/admin.log

# Деплой
tail -f log/deploy.log

# Все логи сразу
tail -f log/*.log
```

### AI-сервер (Ollama)

AI-модуль использует локальный сервер **Ollama** с моделью `llama3.2:3b`.

**Проверка статуса Ollama:**

```bash
# Проверить, запущен ли Ollama
curl http://localhost:11434/api/tags

# Список установленных моделей
ollama list

# Статус сервиса (systemd)
systemctl status ollama

# Логи Ollama
journalctl -u ollama -f

# Перезапуск Ollama
systemctl restart ollama
```

**Типичные проблемы AI-парсинга:**

| Симптом | Что проверять |
|---------|---------------|
| `Connection refused` | Ollama не запущен → `ollama serve` или `systemctl start ollama` |
| `model "llama3.2:3b" not found` | Модель не установлена → `ollama pull llama3.2:3b` |
| Пустой ответ от LLM | Проверить `ai/config/types.json` — возможно, некорректный промт |
| Ошибка парсинга Excel | Проверить формат файла (поддерживаются .xlsx, .xls, .csv) |

**Ручной запуск AI-парсинга для отладки:**

```bash
cd ai && node index.js --file ./test.xlsx --type parse_insured --pretty
```

### Cron-задачи

Cron-задача проверки почты и парсинга застрахованных:

```bash
# Ручной запуск с отладкой
php script/php/cron.php --debug

# Переменная окружения для отладки
CRON_DEBUG=1 php script/php/cron.php
```

Результаты работы cron пишутся в таблицу [`cron_report`](migrations/26_cron_report.sql) и видны в админ-панели в разделе «Отчёты».

### Webpack (сборка frontend)

```bash
# Ошибки сборки админки
cd admin && npm run build 2>&1 | tee ../log/webpack-error.log

# Режим разработки с подробным выводом
cd admin && npm run dev
```

### PHP-ошибки (debug)

При разработке включить отображение ошибок PHP можно через `.env`:

```bash
# admin/config.constant.php или site/config.constant.php
define('DEBUG', true);
```

---

## Архитектура

Монорепо из двух приложений на фреймворке **Pet**:

| Приложение | Назначение |
|------------|------------|
| [`admin/`](admin/) | Админ-панель для сотрудников (CRM) |
| [`site/`](site/) | Публичный сайт для клиентов |
| [`ai/`](ai/) | CLI-утилита для парсинга Excel через Ollama (LLM) |
| [`service/`](service/) | Общий PHP-код для admin и site |
| [`migrations/`](migrations/) | SQL-миграции |
| [`script/`](script/) | Скрипты установки и деплоя |

---

## Функциональность

### Админ-панель (`admin/`)

- **Пользователи** — управление сотрудниками (администраторы, менеджеры)
- **Застрахованные** — реестр застрахованных лиц с фильтрацией по типу операции (прикрепление/открепление)
- **Компании** — справочник компаний-страхователей
- **Почта** — просмотр входящей почты через IMAP, загрузка вложений
- **AI-парсинг** — загрузка Excel-файлов, парсинг данных через Ollama (LLM), управление конфигурацией промтов
- **Переменные** — управление системными переменными (контакты, IMAP, API-ключи)
- **Отчёты** — cron-отчёты о проверке почты
- **Документация** — встроенная бизнес-документация

### Публичный сайт (`site/`)

Отдельное приложение с собственной архитектурой layout, авторизацией через SMS и клиентским кабинетом.

### AI-модуль (`ai/`)

Node.js CLI-утилита для чтения Excel-файлов и отправки данных в локальную LLM (Ollama, модель `llama3.2:3b`). Поддерживает типы команд: парсинг контактов, адресов, товаров, классификация текста, суммаризация.

---

## Технологический стек

### Backend
- **PHP 8.0+** — Pet framework (собственный MVC)
- **MySQL** — InnoDB, миграции через `php pet migrate`
- **Composer** — зависимости: `phpmailer/phpmailer`, `mpdf/mpdf`, `willybahuaud/gaitcha`

### Frontend
- **TypeScript** — per-page entry points, webpack-сборка
- **React 18** — JSX factory `integ`, Rocet DOM
- **SCSS** — модульная структура (elements, page, module)
- **Bootstrap 5** — UI-компоненты
- **Chart.js** — графики
- **CKEditor 4** — WYSIWYG-редактор
- **IMask** — маски ввода

### Инфраструктура
- **Nginx** — веб-сервер
- **Ollama** — локальная LLM (модель `llama3.2:3b`)
- **Git hooks** — автоматический деплой через cron

---

## Структура проекта

```
├── admin/                      # Админ-панель
│   ├── public/
│   │   ├── App/                # PHP-код (MVC)
│   │   │   ├── Controller/     # Контроллеры
│   │   │   ├── Form/           # Формы (сохранение данных)
│   │   │   ├── Model/          # Модели
│   │   │   ├── Module/         # Модули (Auth, Tool, UI)
│   │   │   └── Enum/           # Перечисления (Menu, UsersType)
│   │   ├── router/web.php      # Маршруты
│   │   ├── view/               # PHP-шаблоны
│   │   └── index.php           # Точка входа
│   ├── src/                    # Frontend (TS/SCSS)
│   │   ├── page/               # Entry points по страницам
│   │   ├── module/             # Модули (ajax, datatable, UI)
│   │   ├── css/                # SCSS-стили
│   │   └── event/              # Глобальные обработчики
│   ├── webpack.config.js
│   ├── package.json
│   └── composer.json
│
├── site/                       # Публичный сайт
│   └── (аналогичная структура)
│
├── ai/                         # AI-утилита (Node.js)
│   ├── index.js                # CLI entry point
│   ├── config/types.json       # Конфигурация промтов
│   └── src/
│       ├── reader.js           # Чтение Excel
│       └── ollama.js           # Работа с Ollama API
│
├── service/                    # Общий PHP-код
│   ├── function.php
│   ├── Model/VariableModel.php
│   └── Module/
│       ├── Ai/                 # Парсеры для AI
│       └── Cron/               # Cron-задачи
│
├── migrations/                 # SQL-миграции
├── script/                     # Скрипты установки/деплоя
│   ├── bash/                   # Bash-модули установки
│   └── php/                    # PHP-скрипты (cron, bootstrap)
├── deploy.sh                   # Автоматический деплой
└── install.sh                  # Установщик проекта
```

---

## Установка

### Быстрая установка

```bash
./install.sh
```

Инсталлятор автоматически:
1. Создаёт симлинк в `/var/www/pak-alt`
2. Устанавливает системные пакеты (nginx, MySQL, PHP 8.x, Node.js)
3. Настраивает nginx-конфиг
4. Создаёт БД и накатывает миграции
5. Устанавливает PHP и Node.js зависимости
6. Настраивает права доступа
7. Клонирует репозиторий и настраивает GitHub
8. Устанавливает Ollama (опционально)

### Ручная установка

```bash
# 1. Установка зависимостей PHP
cd admin && composer install
cd ../site && composer install

# 2. Установка зависимостей frontend
cd admin && npm install
cd ../site && npm install

# 3. Настройка .env
cp admin/config.constnt.example.php admin/config.constant.php
# отредактировать DB_* параметры

# 4. Миграции
cd admin && php pet migrate
cd ../site && php pet migrate

# 5. Сборка frontend
cd admin && npm run build
cd ../site && npm run build
```

---

## Разработка

```bash
# Админ-панель
cd admin && npm run dev    # watch frontend
cd admin && php pet serve  # dev-сервер (порт 8080)

# Публичный сайт
cd site && npm run dev
cd site && php pet serve

# AI-модуль
cd ai && node index.js --file ./data.xlsx --type parse_contacts
```

---

## Деплой

Автоматический деплой через cron:

```bash
* * * * * cd /path/to/pak-alt && ./deploy.sh
```

Скрипт [`deploy.sh`](deploy.sh):
1. Проверяет ветку (по умолчанию `main`)
2. Стягивает изменения из git
3. Обновляет Composer-зависимости при изменении `composer.json`
4. Накатывает миграции при изменениях в `migrations/`
5. Собирает frontend при изменениях в `src/`

---

## База данных

### Основные таблицы

| Таблица | Назначение |
|---------|------------|
| [`users`](migrations/1_usermodel.sql) | Сотрудники (администраторы) |
| [`zastrakhovannye`](migrations/4_zastrakhovannye.sql) | Застрахованные лица |
| [`company`](migrations/5_company.sql) | Компании-страхователи |
| [`upload_file`](migrations/27_upload_file.sql) | Загруженные файлы |
| [`cron_report`](migrations/26_cron_report.sql) | Отчёты cron-задач |
| [`variable`](migrations/3_variable.sql) | Системные переменные |

### Переменные

Системные переменные хранятся в таблице `variable` с типами:
- `contacts` — контактные данные (phone, email, address, telegram, whatsapp)
- `imap` — настройки почтового ящика (host, port, username, password)
- `plusofon` — API-ключ SMS-сервиса

---

## Лицензия

Проект является внутренним и не предназначен для публичного распространения.
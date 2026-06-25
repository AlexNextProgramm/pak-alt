# Pak-Alt AI

Пакет для работы с Ollama (модель `llama3.2:3b`). Читает Excel-файлы и отправляет данные в LLM по заданным промтам.

## Установка

```bash
cd ai
npm install
```

## Требования

- Node.js 18+
- [Ollama](https://ollama.com/) с запущенной моделью `llama3.2:3b`

```bash
# Установка модели (если ещё не установлена)
ollama pull llama3.2:3b

# Запуск Ollama
ollama serve
```

## Использование

```bash
node index.js --file ./data.xlsx --type parse_contacts
```

### Обязательные аргументы

| Аргумент | Описание |
|----------|----------|
| `--file, -f` | Путь к Excel-файлу (.xlsx, .xls, .csv) |
| `--type, -t` | Тип команды (см. таблицу ниже) |

### Типы команд

| Тип | Описание |
|-----|----------|
| `parse_contacts` | Парсинг контактов: имена, телефоны, email, должности |
| `parse_addresses` | Парсинг адресов: город, улица, дом, индекс |
| `parse_products` | Парсинг товаров: название, артикул, цена, количество |
| `classify_text` | Классификация текстовых данных по категориям |
| `summarize` | Суммирование/обобщение данных |
| `custom` | Пользовательский промт (требует `--prompt`) |

### Опции

| Опция | Описание |
|-------|----------|
| `--prompt, -p` | Пользовательский промт (только для `type=custom`) |
| `--sheet, -s` | Имя листа (по умолчанию первый) |
| `--max-rows, -r` | Максимум строк для отправки в LLM (по умолчанию 100) |
| `--output, -o` | Сохранить результат в файл |
| `--pretty` | Красивый вывод JSON |
| `--help, -h` | Показать справку |

### Примеры

```bash
# Парсинг контактов
node index.js -f ./contacts.xlsx -t parse_contacts

# Парсинг товаров с указанием листа
node index.js -f ./products.xlsx -t parse_products -s "Товары"

# Сохранение результата в файл
node index.js -f ./data.xlsx -t parse_addresses -o result.json

# Пользовательский промт
node index.js -f ./data.xlsx -t custom -p "Извлеки все телефонные номера в формате +7XXXXXXXXXX"

# Ограничение количества строк
node index.js -f ./big.xlsx -t classify_text -r 50
```

## Конфигурация

Файл `config/types.json` содержит:

- **ollama** — настройки подключения к Ollama (host, model, temperature, maxTokens)
- **types** — типы команд с промтами

Промты используют плейсхолдеры:
- `{{DATA}}` — данные из Excel (JSON)
- `{{CUSTOM_PROMPT}}` — пользовательский промт (только для `custom`)

### Добавление нового типа

Добавьте запись в `config/types.json`:

```json
{
  "parse_orders": {
    "description": "Парсинг заказов: номер, дата, сумма, статус",
    "prompt": "Твоя задача: извлечь из данных информацию о заказах...\n\nДанные:\n{{DATA}}"
  }
}
```

## Структура проекта

```
ai/
├── index.js              # Главный entry point (CLI)
├── package.json          # Зависимости
├── README.md             # Документация
├── config/
│   └── types.json        # Конфиг с типами и промтами
└── src/
    ├── reader.js         # Чтение Excel-файлов
    └── ollama.js         # Работа с Ollama API
```

## Программное использование

Модули можно использовать из другого Node.js кода:

```js
import { readExcel, dataToPrompt } from './src/reader.js';
import { askOllama, buildPrompt } from './src/ollama.js';

// Читаем Excel
const data = readExcel('./data.xlsx', { sheetName: 'Лист1' });

// Формируем промт
const dataStr = dataToPrompt(data.rows, 50);
const { prompt } = buildPrompt('parse_contacts', dataStr);

// Отправляем в Ollama
const result = await askOllama(prompt);
console.log(result);
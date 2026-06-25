#!/usr/bin/env node

/**
 * Pak-Alt AI — пакет для работы с Ollama.
 *
 * Использование:
 *   node index.js --file ./data.xlsx --type parse_data
 *   node index.js --file ./data.xlsx --type parse_data --llm --model gemma3:1b
 *   node index.js --file ./data.xlsx --type custom --prompt "Извлеки все email"
 *
 * Опции:
 *   --file, -f       Путь к Excel-файлу (обязательно)
 *   --type, -t       Тип команды (обязательно)
 *   --model, -m      Модель Ollama (по умолчанию из config/types.json)
 *   --prompt, -p     Пользовательский промт (только для type=custom)
 *   --sheet, -s      Имя листа (опционально, по умолчанию первый)
 *   --max-rows, -r   Максимум строк для отправки в LLM (по умолчанию 100)
 *   --llm            Использовать LLM вместо детерминированного парсера (parse_data)
 *   --raw            Сырые строки без заголовков (только с --llm)
 *   --output, -o     Сохранить результат в файл (опционально)
 *   --pretty         Красивый вывод JSON (по умолчанию true)
 *   --help, -h       Показать справку
 */

import { readExcel, dataToPrompt, parseInsuredPersons } from './src/reader.js';
import { setMapConfigPath } from './src/map-config.js';
import { askOllama, buildPrompt, loadConfig } from './src/ollama.js';
import { writeFileSync } from 'fs';
import { resolve } from 'path';

function showHelp() {
  const config = loadConfig();
  const types = Object.entries(config.types).map(([key, val]) =>
    `  ${key.padEnd(20)} ${val.description}`
  ).join('\n');

  console.log(`
Pak-Alt AI — обработка Excel-файлов через Ollama

Использование:
  node index.js --file <путь> --type <тип> [опции]

Обязательные аргументы:
  --file, -f <путь>       Путь к Excel-файлу (.xlsx, .xls, .csv)
  --type, -t <тип>        Тип команды

Типы команд:
${types}

Опции:
  --model, -m <модель>    Модель Ollama (по умолчанию: ${config.ollama.model})
  --prompt, -p <текст>    Пользовательский промт (только для type=custom)
  --sheet, -s <имя>       Имя листа (по умолчанию первый)
  --max-rows, -r <число>  Макс. строк для LLM (по умолчанию 100)
  --raw                   Читать без заголовков (сырые строки, только с --llm)
  --llm                   Использовать LLM вместо детерминированного парсера
  --output, -o <путь>     Сохранить результат в файл
  --pretty                Красивый вывод JSON
  --help, -h              Показать эту справку

Примеры:
  node index.js -f ./test.xlsx -t parse_data
  node index.js -f ./test.xlsx -t parse_data --model gemma3:1b --raw
  node index.js -f ./data.xlsx -t custom -p "Извлеки все телефонные номера"
`);
}

function parseArgs() {
  const args = process.argv.slice(2);
  const parsed = {};

  for (let i = 0; i < args.length; i++) {
    const arg = args[i];

    switch (arg) {
      case '--file': case '-f':
        parsed.file = args[++i];
        break;
      case '--type': case '-t':
        parsed.type = args[++i];
        break;
      case '--model': case '-m':
        parsed.model = args[++i];
        break;
      case '--prompt': case '-p':
        parsed.prompt = args[++i];
        break;
      case '--sheet': case '-s':
        parsed.sheet = args[++i];
        break;
      case '--max-rows': case '-r':
        parsed.maxRows = parseInt(args[++i], 10) || 100;
        break;
      case '--raw':
        parsed.raw = true;
        break;
      case '--llm':
        parsed.llm = true;
        break;
      case '--filename':
        parsed.filename = args[++i];
        break;
      case '--config': case '-c':
        parsed.config = args[++i];
        break;
      case '--output': case '-o':
        parsed.output = args[++i];
        break;
      case '--pretty':
        parsed.pretty = true;
        break;
      case '--quiet': case '-q':
        parsed.quiet = true;
        break;
      case '--help': case '-h':
        parsed.help = true;
        break;
    }
  }

  return parsed;
}

async function main() {
  const args = parseArgs();

  if (args.help) {
    showHelp();
    process.exit(0);
  }

  // Проверка обязательных аргументов
  if (!args.file) {
    console.error('Ошибка: не указан --file');
    showHelp();
    process.exit(1);
  }

  if (!args.type) {
    console.error('Ошибка: не указан --type');
    showHelp();
    process.exit(1);
  }

  try {
    let result;
    const log = args.quiet ? () => {} : console.log.bind(console);

    // Если передан --config, устанавливаем путь к конфигу
    if (args.config) {
      setMapConfigPath(args.config);
    }

    // parse_data: по умолчанию детерминированный парсер (без LLM)
    if (args.type === 'parse_data' && !args.llm) {
      log(`[1/2] Чтение и парсинг файла: ${args.file}`);
      const parseOptions = { sheetName: args.sheet, filename: args.filename };
      if (args.config) {
        log(`      Конфиг: ${args.config}`);
      }
      result = parseInsuredPersons(args.file, parseOptions);
      log(`      Найдено записей: ${result.length}`);
    } else {
      // 1. Читаем Excel
      log(`[1/3] Чтение файла: ${args.file}`);
      const excelData = readExcel(args.file, {
        sheetName: args.sheet,
        headerRow: !args.raw,  // --raw отключает заголовки
      });

      if (excelData.rows.length === 0) {
        console.error('Ошибка: файл не содержит данных');
        process.exit(1);
      }

      log(`      Лист: ${excelData.sheetName}`);
      log(`      Строк: ${excelData.totalRows}`);
      if (!args.raw) {
        log(`      Заголовки: ${excelData.headers.join(', ')}`);
      } else {
        log(`      Режим: сырые строки (без заголовков)`);
      }

      // 2. Формируем промт
      log(`[2/3] Подготовка промта (тип: ${args.type})`);

      const dataStr = dataToPrompt(excelData.rows, args.maxRows || 100);
      const { prompt } = buildPrompt(args.type, dataStr, args.prompt);

      if (excelData.totalRows > (args.maxRows || 100)) {
        log(`      Внимание: отправляется только ${args.maxRows || 100} из ${excelData.totalRows} строк`);
      }

      // 3. Отправляем в Ollama
      const modelName = args.model || null;
      log(`[3/3] Отправка запроса в Ollama (модель: ${modelName || 'по умолчанию'})...`);
      result = await askOllama(prompt, { model: modelName });
    }

    // 4. Выводим результат
    const outputJson = JSON.stringify(result, null, args.pretty !== false ? 2 : undefined);

    if (args.output) {
      const outputPath = resolve(process.cwd(), args.output);
      writeFileSync(outputPath, outputJson, 'utf-8');
      log(`\n✅ Результат сохранён в: ${outputPath}`);
    } else if (args.quiet) {
      console.log(outputJson);
    } else {
      console.log('\n✅ Результат:');
      console.log(outputJson);
    }

    process.exit(0);
  } catch (error) {
    console.error(`\n❌ Ошибка: ${error.message}`);
    if (error.stack) {
      console.error(error.stack);
    }
    process.exit(1);
  }
}

main();
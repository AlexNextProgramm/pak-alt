import { readFileSync } from 'fs';
import { resolve, dirname } from 'path';
import { fileURLToPath } from 'url';

const __dirname = dirname(fileURLToPath(import.meta.url));
const DEFAULT_CONFIG_PATH = resolve(__dirname, '../mamp/parse-insured.json');

let cachedConfig = null;
let currentConfigPath = DEFAULT_CONFIG_PATH;

/**
 * Устанавливает путь к файлу конфига (из флага --config).
 * @param {string} configPath
 */
export function setMapConfigPath(configPath) {
  currentConfigPath = resolve(process.cwd(), configPath);
  cachedConfig = null; // сбрасываем кеш
}

/**
 * Загружает конфиг парсера.
 * @param {boolean} [reload=false]
 * @returns {object}
 */
export function loadMapConfig(reload = false) {
  if (cachedConfig && !reload) {
    return cachedConfig;
  }

  const raw = readFileSync(currentConfigPath, 'utf-8');
  cachedConfig = JSON.parse(raw);
  return cachedConfig;
}

/**
 * Путь к текущему файлу конфигурации.
 * @returns {string}
 */
export function getMapConfigPath() {
  return currentConfigPath;
}

/**
 * Словарь алиасов колонок: поле → массив алиасов.
 * @returns {Record<string, string[]>}
 */
export function getColumnAliases() {
  const config = loadMapConfig();
  const aliases = {};

  for (const [field, meta] of Object.entries(config.column_aliases)) {
    aliases[field] = meta.aliases;
  }

  return aliases;
}

/**
 * Поля с датами (для конвертации Excel-сериалов).
 * @returns {Set<string>}
 */
export function getDateFields() {
  return new Set(loadMapConfig().date_fields);
}

/**
 * Ключевые слова по типу операции.
 * @param {'прикрепление'|'открепление'} type
 * @param {'header_keywords'|'filename_keywords'|'strong_keywords'} kind
 * @returns {string[]}
 */
export function getOperationKeywords(type, kind) {
  const config = loadMapConfig();
  return config.operation_types[type]?.[kind] ?? [];
}

/**
 * Все ключевые слова открепления / прикрепления для шапки.
 */
export function getHeaderKeywords() {
  const config = loadMapConfig();
  return {
    attachment: config.operation_types.прикрепление.header_keywords,
    detachment: config.operation_types.открепление.header_keywords,
  };
}

/**
 * Ключевые слова для имени файла.
 */
export function getFilenameKeywords() {
  const config = loadMapConfig();
  return {
    attachment: config.operation_types.прикрепление.filename_keywords,
    detachment: config.operation_types.открепление.filename_keywords,
  };
}

/**
 * Сильные (приоритетные) ключевые слова при конфликте.
 */
export function getStrongKeywords() {
  const config = loadMapConfig();
  return {
    attachment: config.operation_types.прикрепление.strong_keywords,
    detachment: config.operation_types.открепление.strong_keywords,
  };
}

/**
 * Legacy-маппинг: русское название колонки → ключ поля (из label + первого алиаса).
 * @returns {Record<string, string>}
 */
export function getInsuredColumnMap() {
  const config = loadMapConfig();
  const map = {};

  for (const [field, meta] of Object.entries(config.column_aliases)) {
    if (field === 'phone') continue;
    const label = meta.label;
    if (label) {
      map[label] = field;
    }
  }

  return map;
}

function normalizeProgramText(value) {
  return String(value)
    .trim()
    .toLowerCase()
    .replace(/ё/g, 'е')
    .replace(/\s+/g, ' ');
}

/**
 * Нормализует программу помощи к кодам АПП, АПП+ПНД, АПП+ПНД+СТОМ, ПНД, СТОМ.
 * @param {unknown} value
 * @returns {string}
 */
export function normalizeProgram(value) {
  const raw = String(value ?? '').trim();
  if (!raw) return '';

  const config = loadMapConfig().program_normalize;
  if (!config?.components || !config?.order) {
    return raw;
  }

  const text = normalizeProgramText(raw);
  const found = new Set();

  const parts = raw
    .split(/[,;/+]+/)
    .map(part => part.trim())
    .filter(Boolean);

  const isLetterCodeList = parts.length > 0 && parts.every((part) => /^[пвс]$/i.test(part));
  if (isLetterCodeList) {
    for (const part of parts) {
      const letter = part.charAt(0).toLowerCase();
      for (const [code, meta] of Object.entries(config.components)) {
        if ((meta.letter_codes ?? []).includes(letter)) {
          found.add(code);
        }
      }
    }
  }

  for (const [code, meta] of Object.entries(config.components)) {
    for (const keyword of meta.keywords ?? []) {
      if (text.includes(keyword)) {
        found.add(code);
        break;
      }
    }
  }

  if (found.size === 0) {
    return raw;
  }

  return config.order
    .filter(code => found.has(code))
    .join(config.separator ?? '+');
}

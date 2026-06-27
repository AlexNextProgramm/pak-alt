import fetch from 'node-fetch';
import { readFileSync } from 'fs';
import { resolve, dirname } from 'path';
import { fileURLToPath } from 'url';

const __dirname = dirname(fileURLToPath(import.meta.url));
const CONFIG_PATH = resolve(__dirname, '../config/types.json');

/**
 * Загружает конфиг из config/types.json
 */
function loadConfig() {
  const raw = readFileSync(CONFIG_PATH, 'utf-8');
  return JSON.parse(raw);
}

/**
 * Отправляет запрос в Ollama API и возвращает распарсенный JSON-ответ.
 *
 * @param {string} prompt - Текст промта
 * @param {object} [options] - Опции
 * @param {string} [options.model] - Модель (из конфига по умолчанию)
 * @param {string} [options.host] - Адрес Ollama (из конфига по умолчанию)
 * @param {number} [options.temperature] - Температура (из конфига по умолчанию)
 * @param {number} [options.maxTokens] - Максимум токенов (из конфига по умолчанию)
 * @returns {Promise<object>} Распарсенный JSON-ответ
 */
export async function askOllama(prompt, options = {}) {
  const config = loadConfig();
  const ollamaCfg = config.ollama;

  const model = options.model || ollamaCfg.model;
  const host = options.host || ollamaCfg.host;
  const temperature = options.temperature ?? ollamaCfg.temperature;
  const maxTokens = options.maxTokens || ollamaCfg.maxTokens;

  const url = `${host}/api/generate`;

  const payload = {
    model,
    prompt,
    stream: false,
    options: {
      temperature,
      num_predict: maxTokens,
    },
  };

  console.log(`[Ollama] Отправка запроса к ${url}`);
  console.log(`[Ollama] Модель: ${model}, temperature: ${temperature}, maxTokens: ${maxTokens}`);

  const response = await fetch(url, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(payload),
  });

  if (!response.ok) {
    const errorText = await response.text();
    throw new Error(`Ollama API error (${response.status}): ${errorText}`);
  }

  const result = await response.json();

  if (!result.response) {
    throw new Error('Ollama вернул пустой ответ');
  }

  // Пробуем извлечь JSON из ответа
  const cleaned = extractJson(result.response);

  if (!cleaned) {
    console.warn('[Ollama] Не удалось извлечь JSON из ответа. Возвращаю сырой текст.');
    return { raw: result.response };
  }

  return cleaned;
}

/**
 * Пытается извлечь JSON из текста ответа.
 * Ищет блок ```json ... ``` или первый { } / [ ]
 *
 * @param {string} text - Сырой текст ответа
 * @returns {object|array|null}
 */
function extractJson(text) {
  if (!text) return null;

  // Пробуем найти блок ```json ... ```
  const jsonBlockMatch = text.match(/```(?:json)?\s*([\s\S]*?)```/);
  if (jsonBlockMatch) {
    try {
      return JSON.parse(jsonBlockMatch[1].trim());
    } catch {
      // невалидный JSON внутри блока — пробуем дальше
    }
  }

  // Пробуем распарсить весь текст как JSON
  try {
    return JSON.parse(text.trim());
  } catch {
    // невалидный JSON
  }

  // Пробуем найти первый { ... } или [ ... ]
  const firstObject = text.match(/(\{[\s\S]*\})/);
  if (firstObject) {
    try {
      return JSON.parse(firstObject[1]);
    } catch {
      // невалидный JSON
    }
  }

  const firstArray = text.match(/(\[[\s\S]*\])/);
  if (firstArray) {
    try {
      return JSON.parse(firstArray[1]);
    } catch {
      // невалидный JSON
    }
  }

  return null;
}

/**
 * Возвращает промт для заданного типа команды.
 * Заменяет плейсхолдеры {{DATA}} и {{CUSTOM_PROMPT}}.
 *
 * @param {string} type - Тип команды (ключ из config/types.json.types)
 * @param {string} dataJson - Данные в виде JSON-строки
 * @param {string} [customPrompt] - Пользовательский промт (для типа custom)
 * @returns {{ prompt: string, typeConfig: object }}
 */
export function buildPrompt(type, dataJson, customPrompt = '') {
  const config = loadConfig();

  if (!config.types[type]) {
    throw new Error(`Неизвестный тип команды: "${type}". Доступные типы: ${Object.keys(config.types).join(', ')}`);
  }

  const typeConfig = config.types[type];
  let prompt = typeConfig.prompt;

  // Заменяем плейсхолдер данных
  prompt = prompt.replace(/\{\{DATA\}\}/g, dataJson);

  // Для кастомного типа — заменяем пользовательский промт
  if (type === 'custom') {
    if (!customPrompt) {
      throw new Error('Для типа "custom" необходимо указать --prompt');
    }
    prompt = prompt.replace(/\{\{CUSTOM_PROMPT\}\}/g, customPrompt);
  }

  return { prompt, typeConfig };
}

export { loadConfig };
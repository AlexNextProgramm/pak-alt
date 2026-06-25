import * as XLSX from 'xlsx';
import SSF from 'ssf';
import { readFileSync } from 'fs';
import { resolve, extname, basename } from 'path';
import {
  loadMapConfig,
  getColumnAliases,
  getDateFields,
  getFilenameKeywords,
  getHeaderKeywords,
  getInsuredColumnMap,
  getStrongKeywords,
  normalizeProgram,
} from './map-config.js';

export { loadMapConfig, getMapConfigPath } from './map-config.js';
export const INSURED_COLUMN_MAP = getInsuredColumnMap();

/**
 * Читает Excel-файл и возвращает данные в виде массива объектов.
 * Поддерживает .xlsx, .xls, .csv
 *
 * @param {string} filePath - Путь к файлу (абсолютный или относительный)
 * @param {object} [options] - Опции
 * @param {number} [options.sheetIndex=0] - Индекс листа (по умолчанию первый)
 * @param {string} [options.sheetName] - Имя листа (приоритетнее sheetIndex)
 * @param {boolean} [options.headerRow=true] - Первая строка — заголовки
 * @returns {{ headers: string[], rows: object[], raw: any[][] }}
 */
export function readExcel(filePath, options = {}) {
  const {
    sheetIndex = 0,
    sheetName = null,
    headerRow = true,
  } = options;

  // Разрешаем путь относительно CWD
  const fullPath = resolve(process.cwd(), filePath);
  const ext = extname(fullPath).toLowerCase();

  if (!['.xlsx', '.xls', '.csv'].includes(ext)) {
    throw new Error(`Неподдерживаемый формат файла: ${ext}. Используйте .xlsx, .xls или .csv`);
  }

  // Читаем файл
  const fileBuffer = readFileSync(fullPath);
  const workbook = XLSX.read(fileBuffer, { type: 'buffer' });

  // Выбираем лист
  let sheet;
  if (sheetName) {
    sheet = workbook.Sheets[sheetName];
    if (!sheet) {
      throw new Error(`Лист "${sheetName}" не найден. Доступные листы: ${workbook.SheetNames.join(', ')}`);
    }
  } else {
    sheet = workbook.Sheets[workbook.SheetNames[sheetIndex]];
  }

  // Конвертируем в JSON
  const rawData = XLSX.utils.sheet_to_json(sheet, { header: 1, defval: '' });

  if (!rawData || rawData.length === 0) {
    return { headers: [], rows: [], raw: [] };
  }

  let headers = [];
  let rows = [];

  if (headerRow) {
    headers = rawData[0].map(h => String(h).trim());
    const dataRows = rawData.slice(1);

    rows = dataRows.map(row => {
      const obj = {};
      headers.forEach((header, i) => {
        obj[header] = row[i] !== undefined ? row[i] : '';
      });
      return obj;
    });
  } else {
    // Если нет заголовков — создаём колонки col_0, col_1, ...
    headers = rawData[0].map((_, i) => `col_${i}`);
    rows = rawData.map(row => {
      const obj = {};
      headers.forEach((header, i) => {
        obj[header] = row[i] !== undefined ? row[i] : '';
      });
      return obj;
    });
  }

  return {
    headers,
    rows,
    raw: rawData,
    sheetName: sheetName || workbook.SheetNames[sheetIndex],
    totalRows: rows.length,
  };
}

/**
 * Маппинг русских названий колонок Excel в английские ключи.
 * Выполняется на уровне кода, чтобы не полагаться на LLM для простого переименования.
 *
 * @param {object[]} rows - Массив строк с русскими ключами
 * @param {object} columnMap - Словарь маппинга: { 'русское название': 'английский_ключ' }
 * @returns {object[]} Массив строк с английскими ключами
 */
export function mapColumns(rows, columnMap) {
  return rows.map(row => {
    const mapped = {};
    for (const [ruKey, enKey] of Object.entries(columnMap)) {
      mapped[enKey] = row[ruKey] !== undefined ? String(row[ruKey]).trim() : '';
    }
    return mapped;
  });
}

function getParserConfig() {
  return loadMapConfig();
}

function normalizeHeader(value) {
  return String(value).trim().toLowerCase().replace(/ё/g, 'е').replace(/\s+/g, ' ');
}

function normalizePersonCase(value) {
  const text = String(value).trim();
  if (!text) return '';

  const upper = text.toLocaleUpperCase('ru-RU');
  if (text !== upper) return text;

  return text
    .toLocaleLowerCase('ru-RU')
    .split(/(\s+|-)/)
    .map((part) => {
      if (!part.trim()) return part;
      return part.charAt(0).toLocaleUpperCase('ru-RU') + part.slice(1);
    })
    .join('');
}

function splitFio(value) {
  const parts = String(value).trim().split(/\s+/).filter(Boolean);
  if (parts.length === 0) {
    return { surname: '', name: '', patronymic: '' };
  }
  if (parts.length === 1) {
    return { surname: normalizePersonCase(parts[0]), name: '', patronymic: '' };
  }
  if (parts.length === 2) {
    return {
      surname: normalizePersonCase(parts[0]),
      name: normalizePersonCase(parts[1]),
      patronymic: '',
    };
  }

  return {
    surname: normalizePersonCase(parts[0]),
    name: normalizePersonCase(parts[1]),
    patronymic: normalizePersonCase(parts.slice(2).join(' ')),
  };
}

/**
 * Форматирует значение ячейки; Excel-сериалы дат → DD.MM.YYYY.
 * @param {unknown} value
 * @param {string} [field]
 * @returns {string}
 */
function formatCellValue(value, field = '') {
  if (value === '' || value === null || value === undefined) return '';

  const config = getParserConfig();
  const dateFields = getDateFields();
  const { min_serial, max_serial, min_year, max_year } = config.excel_date;

  if (dateFields.has(field) && typeof value === 'number' && value > min_serial && value < max_serial) {
    try {
      const d = SSF.parse_date_code(value);
      if (d && d.y >= min_year && d.y <= max_year) {
        return `${String(d.d).padStart(2, '0')}.${String(d.m).padStart(2, '0')}.${d.y}`;
      }
    } catch {
      // не дата — вернём как строку ниже
    }
  }

  return String(value).trim();
}

function headerMatchScore(header, alias) {
  const { includes_min_alias_length, starts_with_min_alias_length } = getParserConfig().header_match_rules;

  if (!header || !alias) return 0;
  if (header === alias) return 100;
  if (alias.startsWith('№') && header.startsWith(alias)) return 90;
  if (alias.length >= starts_with_min_alias_length && header.startsWith(alias)) return 80;
  if (header.includes(alias) && alias.length >= includes_min_alias_length) {
    const headerWords = header.split(' ').filter(Boolean).length;
    const aliasWords = alias.split(' ').filter(Boolean).length;
    if (headerWords > aliasWords + 1) return 30;
    return 50;
  }
  return 0;
}

function headerMatches(header, alias) {
  return headerMatchScore(header, alias) > 0;
}

/**
 * Собирает текст шапки документа (строки до заголовков таблицы).
 * @param {any[][]} rawData
 * @param {number} headerRowIndex
 * @returns {string}
 */
function collectDocumentHeaderText(rawData, headerRowIndex) {
  const parts = [];
  for (let i = 0; i < headerRowIndex; i++) {
    for (const cell of rawData[i]) {
      const text = String(cell).trim();
      if (text) parts.push(text);
    }
  }
  return normalizeHeader(parts.join(' '));
}

/**
 * Определяет тип операции по произвольному тексту.
 * @param {string} text
 * @param {string[]} [extraDetach]
 * @param {string[]} [extraAttach]
 * @returns {'прикрепление'|'открепление'|''}
 */
function detectOperationTypeFromText(text, extraDetach = [], extraAttach = []) {
  const normalized = normalizeHeader(text);
  const headerKw = getHeaderKeywords();
  const strongKw = getStrongKeywords();

  const detachKeywords = [...headerKw.detachment, ...extraDetach];
  const attachKeywords = [...headerKw.attachment, ...extraAttach];

  const hasKeyword = (keywords) => keywords.some(kw => normalized.includes(kw));

  const isDetachment = hasKeyword(detachKeywords);
  const isAttachment = hasKeyword(attachKeywords);

  if (isDetachment && !isAttachment) return 'открепление';
  if (isAttachment && !isDetachment) return 'прикрепление';

  if (strongKw.detachment.some(kw => normalized.includes(kw))) return 'открепление';
  if (strongKw.attachment.some(kw => normalized.includes(kw))) return 'прикрепление';

  if (isAttachment) return 'прикрепление';
  if (isDetachment) return 'открепление';

  return '';
}

/**
 * Определяет тип операции по имени файла.
 * @param {string} filePath
 * @returns {'прикрепление'|'открепление'|''}
 */
function detectOperationTypeFromFilename(filePath) {
  const name = basename(filePath, extname(filePath));
  const text = name.replace(/[-_.]+/g, ' ');
  const filenameKw = getFilenameKeywords();
  return detectOperationTypeFromText(text, filenameKw.detachment, filenameKw.attachment);
}

/**
 * Определяет тип операции по шапке документа и/или имени файла.
 * @param {any[][]} rawData
 * @param {number} headerRowIndex
 * @param {string} [filePath]
 * @returns {'прикрепление'|'открепление'|''}
 */
export function detectOperationType(rawData, headerRowIndex, filePath = '') {
  const fromHeader = detectOperationTypeFromText(
    collectDocumentHeaderText(rawData, headerRowIndex),
  );
  if (fromHeader) return fromHeader;

  const headerRowText = normalizeHeader(
    rawData[headerRowIndex].map(cell => String(cell)).join(' '),
  );
  const fromTableHeader = detectOperationTypeFromText(headerRowText);
  if (fromTableHeader) return fromTableHeader;

  if (filePath) {
    return detectOperationTypeFromFilename(filePath);
  }

  return '';
}

/**
 * Ищет строку с заголовками таблицы (Фамилия, Имя, ...).
 * @param {any[][]} rawData
 * @returns {number}
 */
function findHeaderRowIndex(rawData) {
  const detection = getParserConfig().header_detection;
  const groups = detection.required_alias_groups
    || [detection.required_aliases || ['фамилия', 'имя']];

  for (let i = 0; i < rawData.length; i++) {
    const normalized = rawData[i].map(normalizeHeader);
    const matchesGroup = groups.some(group =>
      group.every(alias => normalized.some(h => headerMatches(h, alias))),
    );
    if (matchesGroup) {
      return i;
    }
  }
  return -1;
}

/**
 * Строит маппинг поле → индекс колонки по строке заголовков.
 * @param {any[]} headerRow
 * @returns {Record<string, number>}
 */
function buildInsuredColumnMap(headerRow) {
  const colMap = {};
  const normalized = headerRow.map(normalizeHeader);
  const fieldAliases = getColumnAliases();

  for (const [field, aliases] of Object.entries(fieldAliases)) {
    let bestIndex = -1;
    let bestScore = 0;

    for (let i = 0; i < normalized.length; i++) {
      const header = normalized[i];
      if (!header) continue;

      for (const alias of aliases) {
        const score = headerMatchScore(header, alias);
        if (score > bestScore) {
          bestScore = score;
          bestIndex = i;
        }
      }
    }

    if (bestIndex >= 0) {
      colMap[field] = bestIndex;
    }
  }

  return colMap;
}

/**
 * Индекс колонки «№ п/п», если есть.
 * @param {any[]} headerRow
 * @returns {number}
 */
function findSerialColumnIndex(headerRow) {
  const serialColumns = getParserConfig().serial_columns.map(normalizeHeader);
  const normalized = headerRow.map(normalizeHeader);

  for (let i = 0; i < normalized.length; i++) {
    const header = normalized[i];
    if (!header) continue;
    if (serialColumns.includes(header)) return i;
    if (header.startsWith('№') && header.includes('п/п')) return i;
  }

  return -1;
}

/**
 * Проверяет, является ли строка строкой данных (а не шапкой/пустой).
 * @param {any[]} row
 * @param {Record<string, number>} colMap
 * @param {number} [serialColIndex]
 * @returns {boolean}
 */
function isInsuredDataRow(row, colMap, serialColIndex = -1) {
  const config = getParserConfig();
  const rules = config.data_row_rules;

  const nonEmpty = row.filter(cell => String(cell).trim() !== '');
  if (nonEmpty.length === 0) return false;

  const joined = row.join(' ').toUpperCase();
  if (config.skip_row_keywords.some(kw => joined.includes(kw))) {
    return false;
  }

  const serialColumns = config.serial_columns.map(normalizeHeader);
  const firstCell = normalizeHeader(row[0]);
  if (serialColumns.includes(firstCell) || firstCell === 'фамилия' || firstCell === 'фио') return false;
  if (firstCell.startsWith('№') && firstCell.includes('п/п')) return false;

  if (rules.require_serial_number && serialColIndex >= 0) {
    const serial = row[serialColIndex];
    const serialNum = Number(serial);
    if (!Number.isFinite(serialNum) || serialNum <= 0 || !Number.isInteger(serialNum)) {
      return false;
    }
  }

  const surname = colMap.surname !== undefined ? String(row[colMap.surname] ?? '').trim() : '';
  const name = colMap.name !== undefined ? String(row[colMap.name] ?? '').trim() : '';
  const fio = colMap.fio !== undefined ? String(row[colMap.fio] ?? '').trim() : '';
  const policy = colMap.policy_number !== undefined ? String(row[colMap.policy_number] ?? '').trim() : '';

  if (rules.reject_underscore_in_name && (surname.includes('_') || name.includes('_') || fio.includes('_'))) {
    return false;
  }

  if (rules.require_surname && !surname && !fio) return false;
  if (rules.require_name_or_policy && !(name || policy || fio)) return false;

  return true;
}

/**
 * Преобразует строку Excel в объект застрахованного лица.
 * @param {any[]} row
 * @param {Record<string, number>} colMap
 * @returns {object}
 */
function rowToInsuredPerson(row, colMap, operationType = '') {
  const get = (field) => {
    const idx = colMap[field];
    if (idx === undefined) return '';
    return formatCellValue(row[idx], field);
  };

  const phone = get('phone');

  let surname = get('surname');
  let name = get('name');
  let patronymic = get('patronymic');

  if (colMap.fio !== undefined) {
    const fio = splitFio(row[colMap.fio]);
    if (!surname) surname = fio.surname;
    if (!name) name = fio.name;
    if (!patronymic) patronymic = fio.patronymic;
  } else {
    surname = normalizePersonCase(surname);
    name = normalizePersonCase(name);
    patronymic = normalizePersonCase(patronymic);
  }

  return {
    operation_type: operationType,
    surname,
    name,
    patronymic,
    birth_date: get('birth_date'),
    gender: get('gender'),
    address: get('address'),
    phone_home: get('phone_home') || phone,
    phone_work: get('phone_work') || phone,
    phone_mobile: get('phone_mobile') || phone,
    policy_number: get('policy_number'),
    service_start: get('service_start'),
    service_end: get('service_end'),
    program: normalizeProgram(get('program')),
    workplace: get('workplace'),
  };
}

/**
 * Детерминированный парсинг застрахованных лиц из Excel.
 * Находит строку заголовков внутри листа и извлекает строки данных.
 *
 * @param {string} filePath
 * @param {object} [options]
 * @param {string} [options.sheetName]
 * @param {number} [options.sheetIndex]
 * @returns {object[]}
 */
export function parseInsuredPersons(filePath, options = {}) {
  const excelData = readExcel(filePath, {
    sheetName: options.sheetName,
    sheetIndex: options.sheetIndex,
    headerRow: false,
  });

  const headerRowIndex = findHeaderRowIndex(excelData.raw);
  const errors = getParserConfig().errors;

  if (headerRowIndex === -1) {
    throw new Error(errors.no_header_row);
  }

  const colMap = buildInsuredColumnMap(excelData.raw[headerRowIndex]);
  const serialColIndex = findSerialColumnIndex(excelData.raw[headerRowIndex]);
  const operationType = detectOperationType(excelData.raw, headerRowIndex, filePath);

  if (!operationType) {
    throw new Error(errors.no_operation_type);
  }

  const persons = [];

  for (let i = headerRowIndex + 1; i < excelData.raw.length; i++) {
    const row = excelData.raw[i];
    if (isInsuredDataRow(row, colMap, serialColIndex)) {
      persons.push(rowToInsuredPerson(row, colMap, operationType));
    }
  }

  return persons;
}

/**
 * Конвертирует данные Excel в строку JSON для передачи в промт.
 * Ограничивает размер, чтобы не превысить контекст LLM.
 *
 * @param {object[]} rows - Массив строк
 * @param {number} [maxRows=100] - Максимальное количество строк
 * @returns {string} JSON-строка
 */
export function dataToPrompt(rows, maxRows = 100) {
  const limited = rows.slice(0, maxRows);
  return JSON.stringify(limited, null, 2);
}
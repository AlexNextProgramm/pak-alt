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
 * Дата из имени файла: _24_06_2026 или -24-06-2026 → 24.06.2026
 * @param {string} filePath
 * @returns {string}
 */
function extractDateFromFilename(filePath) {
  const name = basename(filePath, extname(filePath));
  const match = name.match(/(\d{2})[-_.](\d{2})[-_.](\d{4})/);
  if (!match) {
    return '';
  }

  return `${match[1]}.${match[2]}.${match[3]}`;
}

/**
 * @param {string} token
 * @returns {'прикрепление'|'открепление'|''}
 */
function detectOperationTypeFromFilenameToken(token) {
  const value = normalizeHeader(token);
  if (!value) {
    return '';
  }

  if (value === 'откреп' || value.startsWith('откреп') || value === 'закр' || value === 'snyat' || value === 'detach') {
    return 'открепление';
  }

  if (value === 'откр' || (value.startsWith('откр') && !value.startsWith('откреп'))) {
    return 'прикрепление';
  }

  return '';
}

/**
 * Определяет тип операции по имени файла.
 * @param {string} filePath
 * @returns {'прикрепление'|'открепление'|''}
 */
function detectOperationTypeFromFilename(filePath) {
  const baseName = basename(filePath, extname(filePath));
  const tokens = baseName.replace(/ё/g, 'е').split(/[-_.]+/);

  for (const token of tokens) {
    const fromToken = detectOperationTypeFromFilenameToken(token);
    if (fromToken === 'открепление') {
      return 'открепление';
    }
  }

  for (const token of tokens) {
    const fromToken = detectOperationTypeFromFilenameToken(token);
    if (fromToken === 'прикрепление') {
      return 'прикрепление';
    }
  }

  const text = baseName.replace(/[-_.]+/g, ' ');
  const filenameKw = getFilenameKeywords();
  return detectOperationTypeFromText(text, filenameKw.detachment, filenameKw.attachment);
}

/**
 * @param {object} person
 * @param {string} filePath
 * @returns {object}
 */
function finalizeInsuredPerson(person, filePath) {
  const filenameDate = extractDateFromFilename(filePath);

  if (person.operation_type === 'прикрепление' && !person.service_start && filenameDate) {
    person.service_start = filenameDate;
  }

  if (person.operation_type === 'открепление' && !person.service_end && filenameDate) {
    person.service_end = filenameDate;
  }

  if (person.operation_type === 'открепление' && !person.program) {
    person.program = '';
  }

  return person;
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

function isDateLike(value) {
  const text = String(value ?? '').trim();
  return /^\d{2}\.\d{2}\.\d{4}$/.test(text) || /^\d{2}\/\d{2}\/\d{4}$/.test(text);
}

/**
 * Дата прикрепления/открепления из шапки блока («Снять с:», «Прикрепить с:»).
 * @param {any[][]} rawData
 * @param {number} headerRowIndex
 * @param {'прикрепление'|'открепление'} operationType
 * @returns {string}
 */
function extractBlockServiceDate(rawData, headerRowIndex, operationType) {
  const start = Math.max(0, headerRowIndex - 20);

  for (let i = start; i < headerRowIndex; i++) {
    const row = rawData[i] ?? [];

    for (let j = 0; j < row.length; j++) {
      const label = normalizeHeader(String(row[j] ?? ''));
      if (!label) {
        continue;
      }

      const isDetachLabel = label.includes('снять с');
      const isAttachLabel = label.includes('прикреп') && (label.includes(' с') || label.endsWith('с'));
      if (operationType === 'открепление' && !isDetachLabel) {
        continue;
      }
      if (operationType === 'прикрепление' && !isAttachLabel) {
        continue;
      }

      for (let k = j + 1; k < row.length; k++) {
        const value = formatCellValue(row[k], operationType === 'открепление' ? 'service_end' : 'service_start');
        if (isDateLike(value)) {
          return normalizeSlashDate(value);
        }
      }
    }
  }

  return '';
}

/**
 * @param {object} person
 * @param {string} blockDate
 * @param {'прикрепление'|'открепление'} operationType
 * @returns {object}
 */
function applyBlockDates(person, blockDate, operationType) {
  if (!blockDate) {
    return person;
  }

  if (operationType === 'открепление') {
    person.service_end = blockDate;
  } else if (operationType === 'прикрепление') {
    person.service_start = blockDate;
  }

  return person;
}

/**
 * @param {string} filePath
 * @returns {string[]}
 */
function listParseableSheetNames(filePath) {
  const fullPath = resolve(process.cwd(), filePath);
  const ext = extname(fullPath).toLowerCase();
  if (ext === '.csv') {
    return [];
  }

  const fileBuffer = readFileSync(fullPath);
  const workbook = XLSX.read(fileBuffer, { type: 'buffer' });
  const names = workbook.SheetNames.filter(name => !/xlr|norange/i.test(name));

  return names.length > 0 ? names : workbook.SheetNames;
}

function headerMatchesAnyAlias(header, aliasOrField) {
  const fieldAliases = getColumnAliases();
  let aliases = fieldAliases[aliasOrField];

  if (!aliases) {
    const fieldEntry = Object.entries(fieldAliases).find(([, list]) => list.includes(aliasOrField));
    if (fieldEntry) {
      aliases = fieldEntry[1];
    }
  }

  return (aliases ?? [aliasOrField]).some(alias => headerMatches(header, alias));
}

/**
 * @param {any[][]} rawData
 * @returns {number[]}
 */
function findAllHeaderRowIndexes(rawData) {
  const detection = getParserConfig().header_detection;
  const groups = detection.required_alias_groups
    || [detection.required_aliases || ['фамилия', 'имя']];
  const indexes = [];

  for (let i = 0; i < rawData.length; i++) {
    const normalized = rawData[i].map(normalizeHeader);
    const matchesGroup = groups.some(group =>
      group.every(aliasOrField => normalized.some(h => headerMatchesAnyAlias(h, aliasOrField))),
    );
    if (matchesGroup) {
      indexes.push(i);
    }
  }

  return indexes;
}

/**
 * @param {any[][]} rawData
 * @param {string} contextPath
 * @returns {object[]}
 */
function parseInsuredFromRawData(rawData, contextPath) {
  const errors = getParserConfig().errors;
  const headerIndices = findAllHeaderRowIndexes(rawData);

  if (headerIndices.length === 0) {
    throw new Error(errors.no_header_row);
  }

  const persons = [];
  let hasOperationType = false;

  for (let b = 0; b < headerIndices.length; b++) {
    const headerRowIndex = headerIndices[b];
    const endRow = headerIndices[b + 1] ?? rawData.length;
    const colMap = buildInsuredColumnMap(rawData[headerRowIndex]);
    const serialColIndex = findSerialColumnIndex(rawData[headerRowIndex]);
    const operationType = detectOperationType(rawData, headerRowIndex, contextPath);

    if (!operationType) {
      continue;
    }

    hasOperationType = true;
    const blockDate = extractBlockServiceDate(rawData, headerRowIndex, operationType);

    for (let i = headerRowIndex + 1; i < endRow; i++) {
      const row = rawData[i];
      if (!isInsuredDataRow(row, colMap, serialColIndex)) {
        continue;
      }

      let person = rowToInsuredPerson(row, colMap, operationType);
      person = applyBlockDates(person, blockDate, operationType);
      persons.push(finalizeInsuredPerson(person, contextPath));
    }
  }

  if (!hasOperationType) {
    throw new Error(errors.no_operation_type);
  }

  if (persons.length === 0) {
    throw new Error('AI не нашёл записей в файле');
  }

  return persons;
}

/**
 * Ищет строку с заголовками таблицы (Фамилия, Имя, ...).
 * @param {any[][]} rawData
 * @returns {number}
 */
function findHeaderRowIndex(rawData) {
  const indexes = findAllHeaderRowIndexes(rawData);
  return indexes.length > 0 ? indexes[0] : -1;
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

  const serialColumns = config.serial_columns.map(normalizeHeader);
  const firstCell = normalizeHeader(row[0]);
  if (serialColumns.includes(firstCell) || firstCell === 'фамилия' || firstCell === 'фио') return false;
  if (firstCell.startsWith('№') && firstCell.includes('п/п')) return false;

  let hasValidSerial = false;
  if (serialColIndex >= 0) {
    const serialNum = Number(row[serialColIndex]);
    hasValidSerial = Number.isFinite(serialNum) && serialNum > 0 && Number.isInteger(serialNum);
  }

  if (rules.require_serial_number && serialColIndex >= 0 && !hasValidSerial) {
    return false;
  }

  if (!hasValidSerial && config.skip_row_keywords.some(kw => joined.includes(kw))) {
    return false;
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

  if (rules.require_birth_date_when_mapped && colMap.birth_date !== undefined) {
    const birth = formatCellValue(row[colMap.birth_date], 'birth_date');
    if (!isDateLike(birth)) {
      return false;
    }
  }

  if (rules.require_policy_digits_when_mapped && colMap.policy_number !== undefined) {
    const policyNumber = String(row[colMap.policy_number] ?? '').trim();
    if (!/\d/.test(policyNumber)) {
      return false;
    }
  }

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
    const fioIdx = colMap.fio;
    const parsed = splitFio(get('fio'));
    const fromSameFioColumn = (idx) => idx === fioIdx;

    if (!surname || fromSameFioColumn(colMap.surname)) {
      surname = parsed.surname;
    }
    if (!name || fromSameFioColumn(colMap.name)) {
      name = parsed.name;
    }
    if (!patronymic || fromSameFioColumn(colMap.patronymic)) {
      patronymic = parsed.patronymic;
    }
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
    program: get('program') ? normalizeProgram(get('program')) : '',
    workplace: get('workplace'),
  };
}

function normalizeSlashDate(value) {
  const text = String(value ?? '').trim();
  const slashMatch = text.match(/^(\d{2})\/(\d{2})\/(\d{4})$/);
  if (slashMatch) {
    return `${slashMatch[1]}.${slashMatch[2]}.${slashMatch[3]}`;
  }

  return text;
}

function normalizeAlphaDate(value) {
  if (value === '' || value === null || value === undefined) {
    return '';
  }

  const config = getParserConfig();
  const { min_serial, max_serial, min_year, max_year } = config.excel_date;

  if (typeof value === 'number' && value > min_serial && value < max_serial) {
    try {
      const d = SSF.parse_date_code(value);
      if (d && d.y >= min_year && d.y <= max_year) {
        return `${String(d.d).padStart(2, '0')}.${String(d.m).padStart(2, '0')}.${d.y}`;
      }
    } catch {
      // fall through
    }
  }

  return normalizeSlashDate(String(value).trim());
}

function normalizeAlphaGender(value) {
  const gender = String(value ?? '').trim().toUpperCase();
  if (gender === 'F' || gender === 'Ж') return 'Ж';
  if (gender === 'M' || gender === 'М') return 'М';

  return gender;
}

/**
 * @param {any[]} headerRow
 * @returns {boolean}
 */
function isAlphaAllExportHeaderRow(headerRow) {
  const config = getParserConfig().alpha_all_export;
  if (!config?.required_headers?.length) {
    return false;
  }

  const headers = new Set(headerRow.map(cell => normalizeHeader(String(cell))));
  return config.required_headers.every(
    header => headers.has(normalizeHeader(header)),
  );
}

/**
 * @param {string} dateFrom
 * @param {string} dateCancel
 * @returns {'прикрепление'|'открепление'|''}
 */
function detectAlphaOperationType(dateFrom, dateCancel) {
  const from = normalizeAlphaDate(dateFrom);
  const cancel = normalizeAlphaDate(dateCancel);

  if (!from || !cancel) {
    return '';
  }

  if (from === cancel) {
    return 'прикрепление';
  }

  return 'открепление';
}

/**
 * @param {unknown} riskCode
 * @param {unknown} medProgShort
 * @param {unknown} [progName]
 * @returns {string}
 */
function resolveAlphaProgram(riskCode, medProgShort, progName = '') {
  const config = getParserConfig().alpha_all_export?.risk_code_map ?? {};
  const risk = String(riskCode ?? '').trim().toUpperCase();

  if (risk && config[risk]) {
    return config[risk];
  }

  return normalizeProgram(
    [riskCode, medProgShort, progName].filter(part => String(part ?? '').trim() !== '').join(' '),
  );
}

/**
 * Парсинг сводного выгрузки АО «АльфаСтрахование» (лист ALL, английские заголовки).
 * Тип операции по строке: date_cancel = date_from → прикрепление, иначе → открепление.
 *
 * @param {string} filePath
 * @param {object} [options]
 * @returns {object[]}
 */
export function parseAlphaAllExport(filePath, options = {}) {
  const excelData = readExcel(filePath, {
    sheetName: options.sheetName,
    sheetIndex: options.sheetIndex,
    headerRow: true,
  });

  if (!isAlphaAllExportHeaderRow(excelData.headers)) {
    throw new Error('Файл не является сводной выгрузкой АльфаСтрахование (ALL)');
  }

  const seen = new Set();
  const persons = [];

  for (const row of excelData.rows) {
    const policyNumber = String(row.policy_number ?? '').trim();
    if (!policyNumber) {
      continue;
    }

    const operationType = detectAlphaOperationType(row.date_from, row.date_cancel);
    if (!operationType) {
      continue;
    }

    const dedupeKey = `${policyNumber}|${operationType}`;
    if (seen.has(dedupeKey)) {
      continue;
    }
    seen.add(dedupeKey);

    const fio = splitFio(row.fio ?? '');
    const dateFrom = normalizeAlphaDate(row.date_from);
    const dateTo = normalizeAlphaDate(row.date_to);
    const dateCancel = normalizeAlphaDate(row.date_cancel);

    const serviceStart = operationType === 'прикрепление' ? dateFrom : dateCancel;
    const serviceEnd = operationType === 'прикрепление' ? dateTo : dateCancel;

    persons.push({
      operation_type: operationType,
      surname: fio.surname,
      name: fio.name,
      patronymic: fio.patronymic,
      birth_date: normalizeAlphaDate(row.birth_date),
      gender: normalizeAlphaGender(row.person_sex),
      address: String(row.address ?? '').trim(),
      phone_home: String(row.phone_home ?? '').trim(),
      phone_work: String(row.phone_office ?? '').trim(),
      phone_mobile: String(row.per_mobile_phone ?? row.add_phone ?? '').trim(),
      policy_number: policyNumber,
      service_start: serviceStart,
      service_end: serviceEnd,
      program: resolveAlphaProgram(row.risk_code, row.med_prog_short, row.prog_name),
      workplace: String(row.insurer ?? row.company_of_work ?? '').trim(),
    });
  }

  if (persons.length === 0) {
    throw new Error('В сводной выгрузке АльфаСтрахование не найдено записей');
  }

  return persons;
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
  const contextPath = options.filename || filePath;

  const tryParseRaw = (raw) => {
    if (raw.length > 0 && isAlphaAllExportHeaderRow(raw[0])) {
      return parseAlphaAllExport(filePath, options);
    }

    return parseInsuredFromRawData(raw, contextPath);
  };

  if (options.sheetName) {
    const excelData = readExcel(filePath, {
      sheetName: options.sheetName,
      sheetIndex: options.sheetIndex,
      headerRow: false,
    });

    return tryParseRaw(excelData.raw);
  }

  if (options.sheetIndex != null) {
    const excelData = readExcel(filePath, {
      sheetIndex: options.sheetIndex,
      headerRow: false,
    });

    return tryParseRaw(excelData.raw);
  }

  const sheetNames = listParseableSheetNames(filePath);
  let lastError = null;

  for (const sheetName of sheetNames) {
    try {
      const excelData = readExcel(filePath, { sheetName, headerRow: false });
      return tryParseRaw(excelData.raw);
    } catch (error) {
      lastError = error;
    }
  }

  if (lastError) {
    throw lastError;
  }

  const excelData = readExcel(filePath, { headerRow: false });

  return tryParseRaw(excelData.raw);
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
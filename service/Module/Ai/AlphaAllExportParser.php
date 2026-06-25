<?php

namespace Module\Ai;

use RuntimeException;
use ZipArchive;

class AlphaAllExportParser
{
    private const REQUIRED_HEADERS = ['policy_number', 'date_from', 'date_cancel', 'fio'];

    /** @var array<string, string> */
    private array $riskCodeMap;

    public function __construct(?array $riskCodeMap = null)
    {
        $this->riskCodeMap = $riskCodeMap ?? $this->loadRiskCodeMap();
    }

    public static function isAlphaAllExport(string $absolutePath): bool
    {
        $ext = strtolower(pathinfo($absolutePath, PATHINFO_EXTENSION));
        if ($ext !== 'xlsx') {
            return false;
        }

        try {
            $rows = self::readSheetRows($absolutePath, 1);
        } catch (\Throwable) {
            return false;
        }

        if ($rows === []) {
            return false;
        }

        return self::headersMatch($rows[0]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function parse(string $absolutePath): array
    {
        if (!is_file($absolutePath)) {
            throw new RuntimeException('Файл для парсинга не найден: ' . $absolutePath);
        }

        $matrix = self::readSheetRows($absolutePath);
        if ($matrix === []) {
            throw new RuntimeException('Файл не содержит данных');
        }

        if (!self::headersMatch($matrix[0])) {
            throw new RuntimeException('Файл не является сводной выгрузкой АльфаСтрахование (ALL)');
        }

        $headers = array_map(static fn($value): string => trim((string)$value), $matrix[0]);
        $seen = [];
        $persons = [];

        for ($i = 1, $count = count($matrix); $i < $count; $i++) {
            $row = self::combineRow($headers, $matrix[$i]);
            $person = $this->mapRow($row);
            if ($person === null) {
                continue;
            }

            $dedupeKey = $person['policy_number'] . '|' . $person['operation_type'];
            if (isset($seen[$dedupeKey])) {
                continue;
            }

            $seen[$dedupeKey] = true;
            $persons[] = $person;
        }

        if ($persons === []) {
            throw new RuntimeException('В сводной выгрузке АльфаСтрахование не найдено записей');
        }

        return $persons;
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>|null
     */
    private function mapRow(array $row): ?array
    {
        $policyNumber = trim((string)($row['policy_number'] ?? ''));
        if ($policyNumber === '') {
            return null;
        }

        $operationType = $this->detectOperationType($row['date_from'] ?? null, $row['date_cancel'] ?? null);
        if ($operationType === '') {
            return null;
        }

        $fio = $this->splitFio((string)($row['fio'] ?? ''));
        $dateFrom = $this->normalizeDate($row['date_from'] ?? null);
        $dateTo = $this->normalizeDate($row['date_to'] ?? null);
        $dateCancel = $this->normalizeDate($row['date_cancel'] ?? null);

        $serviceStart = $operationType === 'прикрепление' ? $dateFrom : $dateCancel;
        $serviceEnd = $operationType === 'прикрепление' ? $dateTo : $dateCancel;

        return [
            'operation_type' => $operationType,
            'surname' => $fio['surname'],
            'name' => $fio['name'],
            'patronymic' => $fio['patronymic'],
            'birth_date' => $this->normalizeDate($row['birth_date'] ?? null),
            'gender' => $this->normalizeGender($row['person_sex'] ?? null),
            'address' => trim((string)($row['address'] ?? '')) ?: '',
            'phone_home' => trim((string)($row['phone_home'] ?? '')) ?: '',
            'phone_work' => trim((string)($row['phone_office'] ?? '')) ?: '',
            'phone_mobile' => trim((string)($row['per_mobile_phone'] ?? $row['add_phone'] ?? '')) ?: '',
            'policy_number' => $policyNumber,
            'service_start' => $serviceStart,
            'service_end' => $serviceEnd,
            'program' => $this->resolveProgram(
                $row['risk_code'] ?? null,
                $row['med_prog_short'] ?? null,
                $row['prog_name'] ?? null,
            ),
            'workplace' => trim((string)($row['insurer'] ?? $row['company_of_work'] ?? '')) ?: '',
        ];
    }

    /**
     * @return array{surname: string, name: string, patronymic: string}
     */
    private function splitFio(string $value): array
    {
        $parts = preg_split('/\s+/u', trim($value), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        if ($parts === []) {
            return ['surname' => '', 'name' => '', 'patronymic' => ''];
        }
        if (count($parts) === 1) {
            return ['surname' => $this->normalizePersonCase($parts[0]), 'name' => '', 'patronymic' => ''];
        }
        if (count($parts) === 2) {
            return [
                'surname' => $this->normalizePersonCase($parts[0]),
                'name' => $this->normalizePersonCase($parts[1]),
                'patronymic' => '',
            ];
        }

        return [
            'surname' => $this->normalizePersonCase($parts[0]),
            'name' => $this->normalizePersonCase($parts[1]),
            'patronymic' => $this->normalizePersonCase(implode(' ', array_slice($parts, 2))),
        ];
    }

    private function normalizePersonCase(string $value): string
    {
        $text = trim($value);
        if ($text === '') {
            return '';
        }

        $upper = mb_strtoupper($text, 'UTF-8');
        if ($text !== $upper) {
            return $text;
        }

        return mb_convert_case(mb_strtolower($text, 'UTF-8'), MB_CASE_TITLE, 'UTF-8');
    }

    private function detectOperationType(mixed $dateFrom, mixed $dateCancel): string
    {
        $from = $this->normalizeDate($dateFrom);
        $cancel = $this->normalizeDate($dateCancel);

        if ($from === '' || $cancel === '') {
            return '';
        }

        return $from === $cancel ? 'прикрепление' : 'открепление';
    }

    private function normalizeGender(mixed $value): string
    {
        $gender = mb_strtoupper(trim((string)$value), 'UTF-8');
        if ($gender === 'F' || $gender === 'Ж') {
            return 'Ж';
        }
        if ($gender === 'M' || $gender === 'М') {
            return 'М';
        }

        return $gender;
    }

    private function normalizeDate(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        if (is_numeric($value)) {
            $serial = (float)$value;
            if ($serial > 20000 && $serial < 100000) {
                $timestamp = (int)round(($serial - 25569) * 86400);
                return gmdate('d.m.Y', $timestamp);
            }
        }

        $text = trim((string)$value);
        if (preg_match('#^(\d{2})[./](\d{2})[./](\d{4})$#', $text, $matches) === 1) {
            return sprintf('%s.%s.%s', $matches[1], $matches[2], $matches[3]);
        }

        return $text;
    }

    private function resolveProgram(mixed $riskCode, mixed $medProgShort, mixed $progName): string
    {
        $risk = mb_strtoupper(trim((string)$riskCode), 'UTF-8');
        if ($risk !== '' && isset($this->riskCodeMap[$risk])) {
            return $this->riskCodeMap[$risk];
        }

        $parts = array_filter([
            trim((string)$riskCode),
            trim((string)$medProgShort),
            trim((string)$progName),
        ], static fn(string $part): bool => $part !== '');
        $combined = mb_strtolower(trim(implode(' ', $parts)), 'UTF-8');
        $combined = str_replace('ё', 'е', $combined);

        $found = [];
        if (preg_match('/\b(апп|поликлин|амбулатор|амбулаторн)/u', $combined) === 1) {
            $found['АПП'] = true;
        }
        if (preg_match('/\b(пнд|помощь на дому|вызов на дом|вызов врача|на дому)/u', $combined) === 1) {
            $found['ПНД'] = true;
        }
        if (preg_match('/\b(стом|стоматолог)/u', $combined) === 1) {
            $found['СТОМ'] = true;
        }

        if ($found === []) {
            return trim((string)$medProgShort) ?: trim((string)$riskCode);
        }

        $order = ['АПП', 'ПНД', 'СТОМ'];

        return implode('+', array_values(array_filter($order, static fn(string $code): bool => isset($found[$code]))));
    }

    /**
     * @param list<string> $headers
     * @param list<string> $values
     * @return array<string, string>
     */
    private static function combineRow(array $headers, array $values): array
    {
        $row = [];
        foreach ($headers as $index => $header) {
            if ($header === '') {
                continue;
            }
            $row[$header] = trim((string)($values[$index] ?? ''));
        }

        return $row;
    }

    /**
     * @param list<string> $headerRow
     */
    private static function headersMatch(array $headerRow): bool
    {
        $headers = [];
        foreach ($headerRow as $header) {
            $headers[mb_strtolower(trim((string)$header), 'UTF-8')] = true;
        }

        foreach (self::REQUIRED_HEADERS as $required) {
            if (!isset($headers[$required])) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return array<string, string>
     */
    private function loadRiskCodeMap(): array
    {
        $path = ParseConfig::activePath();
        if ($path === null || !is_file($path)) {
            return [
                'ПВ' => 'АПП+ПНД',
                'ПСВ' => 'АПП+ПНД',
            ];
        }

        $raw = file_get_contents($path);
        if ($raw === false) {
            return [];
        }

        $config = json_decode($raw, true);
        if (!is_array($config)) {
            return [];
        }

        $map = $config['alpha_all_export']['risk_code_map'] ?? [];

        return is_array($map) ? array_map(static fn($value): string => (string)$value, $map) : [];
    }

    /**
     * @return list<list<string>>
     */
    private static function readSheetRows(string $absolutePath, ?int $maxRows = null): array
    {
        if (!class_exists(ZipArchive::class)) {
            throw new RuntimeException('Расширение ZipArchive недоступно');
        }

        $zip = new ZipArchive();
        if ($zip->open($absolutePath) !== true) {
            throw new RuntimeException('Не удалось открыть Excel-файл');
        }

        $sharedStrings = self::readSharedStrings($zip);
        $sheetXml = $zip->getFromName('xl/worksheets/sheet1.xml');
        $zip->close();

        if ($sheetXml === false) {
            throw new RuntimeException('Лист sheet1 не найден в Excel-файле');
        }

        return self::parseSheetXml($sheetXml, $sharedStrings, $maxRows);
    }

    /**
     * @return list<string>
     */
    private static function readSharedStrings(ZipArchive $zip): array
    {
        $xml = $zip->getFromName('xl/sharedStrings.xml');
        if ($xml === false) {
            return [];
        }

        $document = new \DOMDocument();
        if (@$document->loadXML($xml) === false) {
            return [];
        }

        $xpath = new \DOMXPath($document);
        $xpath->registerNamespace('m', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');

        $strings = [];
        foreach ($xpath->query('//m:si') ?: [] as $item) {
            $parts = [];
            foreach ($xpath->query('.//m:t', $item) ?: [] as $textNode) {
                $parts[] = $textNode->textContent;
            }
            $strings[] = implode('', $parts);
        }

        return $strings;
    }

    /**
     * @param list<string> $sharedStrings
     * @return list<list<string>>
     */
    private static function parseSheetXml(string $xml, array $sharedStrings, ?int $maxRows): array
    {
        $document = new \DOMDocument();
        if (@$document->loadXML($xml) === false) {
            throw new RuntimeException('Не удалось прочитать лист Excel');
        }

        $xpath = new \DOMXPath($document);
        $xpath->registerNamespace('m', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');

        $matrix = [];
        $maxColumnIndex = 0;

        foreach ($xpath->query('//m:sheetData/m:row') ?: [] as $rowNode) {
            $rowIndex = (int)$rowNode->getAttribute('r');
            if ($rowIndex <= 0) {
                continue;
            }

            if ($maxRows !== null && $rowIndex > $maxRows) {
                break;
            }

            $rowValues = [];
            foreach ($xpath->query('m:c', $rowNode) ?: [] as $cellNode) {
                $reference = $cellNode->getAttribute('r');
                if (!preg_match('/^([A-Z]+)(\d+)$/', $reference, $matches)) {
                    continue;
                }

                $columnIndex = self::columnIndex($matches[1]);
                $maxColumnIndex = max($maxColumnIndex, $columnIndex);
                $rowValues[$columnIndex] = self::readCellValue($cellNode, $sharedStrings);
            }

            $normalized = array_fill(0, $maxColumnIndex + 1, '');
            foreach ($rowValues as $index => $value) {
                $normalized[$index] = $value;
            }

            $matrix[$rowIndex - 1] = $normalized;
        }

        if ($matrix === []) {
            return [];
        }

        ksort($matrix);

        return array_values($matrix);
    }

    /**
     * @param list<string> $sharedStrings
     */
    private static function readCellValue(\DOMElement $cellNode, array $sharedStrings): string
    {
        $type = $cellNode->getAttribute('t');
        $valueNode = null;
        foreach ($cellNode->childNodes as $child) {
            if ($child instanceof \DOMElement && in_array($child->tagName, ['v', 't'], true)) {
                $valueNode = $child;
                break;
            }
            if ($child instanceof \DOMElement && $child->tagName === 'is') {
                $textParts = [];
                foreach ($child->getElementsByTagNameNS('http://schemas.openxmlformats.org/spreadsheetml/2006/main', 't') as $textNode) {
                    $textParts[] = $textNode->textContent;
                }

                return trim(implode('', $textParts));
            }
        }

        if (!$valueNode) {
            return '';
        }

        $raw = $valueNode->textContent;
        if ($type === 's') {
            return trim($sharedStrings[(int)$raw] ?? '');
        }

        return trim($raw);
    }

    private static function columnIndex(string $letters): int
    {
        $letters = strtoupper($letters);
        $index = 0;
        $length = strlen($letters);
        for ($i = 0; $i < $length; $i++) {
            $index = $index * 26 + (ord($letters[$i]) - ord('A') + 1);
        }

        return $index - 1;
    }
}

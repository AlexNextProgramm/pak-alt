<?php

namespace Module\Ai;

use RuntimeException;

class InsuredParser
{
    private string $nodeBin;
    private string $aiDir;

    public function __construct(?string $nodeBin = null, ?string $aiDir = null)
    {
        $this->nodeBin = NodeBin::resolve($nodeBin);
        $this->aiDir = $aiDir ?: (ParseConfig::repoRoot() . '/ai');
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function parseFile(string $absolutePath, ?string $originalFilename = null): array
    {
        if (!is_file($absolutePath)) {
            throw new RuntimeException('Файл для парсинга не найден: ' . $absolutePath);
        }

        $ext = strtolower(pathinfo($absolutePath, PATHINFO_EXTENSION));
        if (!in_array($ext, ['xlsx', 'xls', 'csv'], true)) {
            throw new RuntimeException('Неподдерживаемый формат файла: .' . $ext);
        }

        if (AlphaAllExportParser::isAlphaAllExport($absolutePath)) {
            return (new AlphaAllExportParser())->parse($absolutePath);
        }

        return $this->parseViaNode($absolutePath, $originalFilename);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function parseViaNode(string $absolutePath, ?string $originalFilename = null): array
    {
        $aiScript = realpath($this->aiDir . '/index.js');
        if (!$aiScript) {
            throw new RuntimeException('Скрипт AI не найден');
        }

        $this->nodeBin = NodeBin::requireAvailable($this->nodeBin);

        $configPath = ParseConfig::activePath() ?? '';
        $configFlag = $configPath !== '' ? sprintf('--config %s', escapeshellarg($configPath)) : '';
        $filenameFlag = '';

        if ($originalFilename !== null && trim($originalFilename) !== '') {
            $filenameFlag = sprintf('--filename %s', escapeshellarg($originalFilename));
        }

        $cmd = sprintf(
            'cd %s && %s index.js --file %s --type parse_data --quiet %s %s 2>&1',
            escapeshellarg(dirname($aiScript)),
            escapeshellarg($this->nodeBin),
            escapeshellarg($absolutePath),
            $configFlag,
            $filenameFlag,
        );

        $output = shell_exec($cmd);
        if ($output === null) {
            throw new RuntimeException('Ошибка выполнения AI-скрипта');
        }

        return $this->decodeOutput($output);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function decodeOutput(string $output): array
    {
        $trimmed = trim($output);
        $jsonStr = $trimmed;
        $result = json_decode($jsonStr, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            $jsonStr = null;
            $marker = '✅ Результат:';
            $markerPos = strpos($output, $marker);
            if ($markerPos !== false) {
                $jsonStr = trim(substr($output, $markerPos + strlen($marker)));
            } elseif (preg_match('/(\[[\s\S]*\]|\{[\s\S]*\})\s*$/', $trimmed, $matches)) {
                $jsonStr = $matches[1];
            }

            if ($jsonStr === null || $jsonStr === '') {
                $errorMsg = trim($output);
                throw new RuntimeException($errorMsg !== '' ? $errorMsg : 'Не удалось распознать результат AI');
            }

            $result = json_decode($jsonStr, true);
        }

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new RuntimeException('Ошибка парсинга JSON: ' . json_last_error_msg());
        }

        if (is_array($result) && isset($result['error'])) {
            throw new RuntimeException((string)$result['error']);
        }

        if (!is_array($result) || $result === []) {
            throw new RuntimeException('AI не нашёл записей в файле');
        }

        if (!array_is_list($result)) {
            throw new RuntimeException('Некорректный формат результата парсинга');
        }

        return $result;
    }
}

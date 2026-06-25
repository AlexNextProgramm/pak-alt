<?php

namespace App\Controller\Ajax\Ai;

use App\Controller\AjaxController;
use App\Form\Form;
use App\Module\Ai\ParseConfig;
use App\Module\Auth;
use Pet\Router\Error as RE;
use Pet\Router\Response;

class Parse extends AjaxController
{
    public function helper(): array
    {
        Auth::init();
        if (!Auth::$isAuth) {
            RE::setHttp(RE::STATUS_HTTP::FORBIDDEN);
            Response::die('Нет авторизации');
        }

        // Проверяем загруженный файл
        $file = $_FILES['file'] ?? null;
        if (!$file || $file['error'] !== UPLOAD_ERR_OK) {
            return Form::errorInput('file', 'Ошибка загрузки файла');
        }

        $tmpPath = $file['tmp_name'];
        $origName = $file['name'];

        // Разрешённые расширения
        $ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
        if (!in_array($ext, ['xlsx', 'xls', 'csv'])) {
            return Form::errorInput('file', 'Допустимы только файлы .xlsx, .xls, .csv');
        }

        // Путь к скрипту AI (корень репозитория: 6 уровней вверх от Ai/)
        $aiScript = realpath(__DIR__ . '/../../../../../../ai/index.js');
        if (!$aiScript) {
            return ['type' => 'fire', 'message' => 'Скрипт AI не найден'];
        }

        // Полный путь к node (из окружения веб-сервера может не быть в PATH)
        $nodeBin = '/home/alex/.nvm/versions/node/v20.19.5/bin/node';

        $configPath = ParseConfig::activePath() ?? '';

        // Копируем во временный файл с правильным расширением
        $tmpDir = sys_get_temp_dir();
        $tmpFile = $tmpDir . '/pak-alt-ai-' . uniqid() . '.' . $ext;
        copy($tmpPath, $tmpFile);

        // Формируем команду с полным путём к node и флагом --config
        $configFlag = !empty($configPath) ? sprintf('--config %s', escapeshellarg($configPath)) : '';
        $cmd = sprintf(
            'cd %s && %s index.js --file %s --type parse_data --pretty --quiet %s 2>&1',
            escapeshellarg(dirname($aiScript)),
            escapeshellarg($nodeBin),
            escapeshellarg($tmpFile),
            $configFlag
        );

        $output = shell_exec($cmd);

        // Удаляем временный файл
        @unlink($tmpFile);

        if ($output === null) {
            return ['type' => 'fire', 'message' => 'Ошибка выполнения AI-скрипта'];
        }

        // Парсим JSON из вывода (--quiet: чистый JSON; иначе — после маркера "✅ Результат:")
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
                if (empty($errorMsg)) {
                    $errorMsg = 'Не удалось распознать результат AI';
                }
                return [
                    'type' => 'error',
                    'message' => $errorMsg,
                ];
            }

            $result = json_decode($jsonStr, true);
        }

        if (json_last_error() !== JSON_ERROR_NONE) {
            return [
                'type' => 'error',
                'message' => 'Ошибка парсинга JSON: ' . json_last_error_msg() . "\n\nВывод:\n" . $output,
            ];
        }

        // Если результат — объект с ошибкой
        if (is_array($result) && isset($result['error'])) {
            return [
                'type' => 'error',
                'message' => $result['error'],
            ];
        }

        // Если результат — пустой массив
        if (is_array($result) && count($result) === 0) {
            return [
                'type' => 'error',
                'message' => 'AI не нашёл записей в файле. Проверьте формат данных.',
            ];
        }

        return [
            'type' => 'success',
            'data' => $result,
            'count' => is_array($result) ? count($result) : 0,
        ];
    }
}
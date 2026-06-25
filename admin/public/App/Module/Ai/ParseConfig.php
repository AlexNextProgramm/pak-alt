<?php

namespace App\Module\Ai;

use Model\VariableModel;

class ParseConfig
{
    public const VAR_TYPE = 'ai';
    public const VAR_NAME = 'parse_config';

    public static function defaultPath(): ?string
    {
        $path = realpath(__DIR__ . '/../../../../../ai/mamp/parse-insured.json');

        return $path ?: null;
    }

    public static function uploadDir(): string
    {
        $dir = realpath(__DIR__ . '/../../../../var/uploads/config/ai');
        if ($dir) {
            return $dir;
        }

        $dir = __DIR__ . '/../../../../var/uploads/config/ai';
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        return realpath($dir) ?: $dir;
    }

    public static function uploadPath(): string
    {
        return self::uploadDir() . '/parse.json';
    }

    public static function variablePath(): string
    {
        $variable = new VariableModel(['type' => self::VAR_TYPE, 'name' => self::VAR_NAME]);
        if (!$variable->isInfo()) {
            return '';
        }

        $path = trim((string)$variable->get('value'));
        if ($path === '' || !is_file($path)) {
            return '';
        }

        return $path;
    }

    public static function activePath(): ?string
    {
        $custom = self::variablePath();
        if ($custom !== '') {
            return $custom;
        }

        return self::defaultPath();
    }

    public static function isCustom(): bool
    {
        return self::variablePath() !== '';
    }

    public static function setVariablePath(string $path): void
    {
        $variable = new VariableModel();
        $variable->ifExistSetOrCreate([
            'type' => self::VAR_TYPE,
            'name' => self::VAR_NAME,
            'value' => $path,
        ]);
    }

    public static function clearVariable(): void
    {
        $variable = new VariableModel(['type' => self::VAR_TYPE, 'name' => self::VAR_NAME]);
        if ($variable->isInfo()) {
            $variable->set(['value' => '']);
        }
    }

    public static function deleteUploadFile(): void
    {
        $path = self::uploadPath();
        if (is_file($path)) {
            @unlink($path);
        }
    }

    /**
     * @return array{content: string, isCustom: bool, path: string}|array{error: string}
     */
    public static function load(): array
    {
        $path = self::activePath();
        if (!$path) {
            return ['error' => 'Файл конфига не найден'];
        }

        $content = file_get_contents($path);
        if ($content === false) {
            return ['error' => 'Ошибка чтения файла конфига'];
        }

        return [
            'content' => $content,
            'isCustom' => self::isCustom(),
            'path' => $path,
        ];
    }
}

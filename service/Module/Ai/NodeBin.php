<?php

namespace Module\Ai;

use RuntimeException;

class NodeBin
{
    public static function resolve(?string $preferred = null): string
    {
        if ($preferred !== null && $preferred !== '' && self::isExecutable($preferred)) {
            return $preferred;
        }

        $fromEnv = self::fromEnv();
        if ($fromEnv !== '' && self::isExecutable($fromEnv)) {
            return $fromEnv;
        }

        return 'node';
    }

    public static function isAvailable(?string $bin = null): bool
    {
        $resolved = self::resolve($bin);

        return $resolved !== 'node' && self::isExecutable($resolved);
    }

    public static function requireAvailable(?string $bin = null): string
    {
        $resolved = self::resolve($bin);
        if (!self::isAvailable($resolved)) {
            throw new RuntimeException('Node.js не найден. Укажите NODE_BIN в .env');
        }

        return $resolved;
    }

    private static function fromEnv(): string
    {
        if (defined('NODE_BIN')) {
            $value = constant('NODE_BIN');
            if (is_string($value) && $value !== '') {
                return trim($value);
            }
        }

        if (function_exists('env') && defined('ROOT')) {
            $value = env('NODE_BIN');
            if (is_string($value) && $value !== '') {
                return trim($value);
            }
        }

        return '';
    }

    private static function isExecutable(string $path): bool
    {
        return $path !== '' && $path !== 'node' && is_executable($path);
    }
}

<?php

namespace Module\Gaitcha;

use Gaitcha\Config;

/**
 * Конфигурация Gaitcha для проекта.
 * 
 * Секретный ключ берётся из константы GAITCHA_SECRET (определяется в config.constant.php).
 * Если константа не задана — используется значение по умолчанию (только для разработки).
 */
class GaitchaConfig
{
    private static ?Config $instance = null;

    /**
     * Создаёт или возвращает существующий экземпляр Config.
     *
     * @return Config
     */
    public static function get(): Config
    {
        if (self::$instance === null) {
            $secret = defined('GAITCHA_SECRET') && GAITCHA_SECRET !== ''
                ? GAITCHA_SECRET
                : 'dev-secret-key-change-me-before-production!';

            self::$instance = new Config([
                'secret'          => $secret,
                'ttl'             => 120,
                'score_threshold' => 0.3,
                'debug'           => false,
                'no_js_fallback'  => 'reject',
                'anti_replay'     => false,
                'pow'             => false,
            ]);
        }

        return self::$instance;
    }
}
<?php

namespace App\Enum;

use App\Enum\UsersType as UT;

class Menu
{
    const HOME = 1;
    const VARIABLES = 2;
    const COMPANY = 3;
    const CRON_REPORT = 8;
    const DOCUMENTATION = 9;
    const MAIL = 10;

    public static function data($UT = UT::SYSADMIN): array
    {
        return [
            self::HOME => (object)['url' => '/', 'name' => 'Главная', 'icon' => 'menu.home'],
            self::COMPANY => (object)['url' => '/company', 'name' => 'Компании', 'icon' => 'menu.company'],
            self::MAIL => (object)['url' => '/mail', 'name' => 'Почта', 'icon' => 'menu.mail'],
            self::VARIABLES => (object)['url' => '/variables', 'name' => 'Переменные', 'icon' => 'menu.variables'],
            self::CRON_REPORT => (object)['url' => '/cron-report', 'name' => 'Отчёт крона', 'icon' => 'menu.cron-report'],
            self::DOCUMENTATION => (object)['url' => '/documentation', 'name' => 'Документация', 'icon' => 'menu.documentation'],
        ];
    }
}

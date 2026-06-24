<?php

namespace App\Enum;

use App\Enum\UsersType as UT;

class Menu
{
    const HOME = 1;

    public static function data($UT = UT::SYSADMIN): array
    {
        return [
            self::HOME => (object)['url' => '/', 'name' => 'Главная', 'icon' => 'menu.home'],
        ];
    }
}

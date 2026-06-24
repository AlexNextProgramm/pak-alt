<?php

namespace App\Enum;
class UsersType
{
    const SYSADMIN = 1;
    const ADMIN = 3;

    public static function data(): array
    {
        return [
            self::SYSADMIN => 'Системный Администратор',
            self::ADMIN => 'Администратор',
        ];
    }

    public static function get(?int $type = null): ?string
    {
        if (!empty($type)) {
            return self::data()[$type] ?? null;
        }
        return null;
    }

}

<?php

namespace App\Table;

use Module\Model\UserModel;

class User extends UserModel implements Itable
{
    private array $allowedFilters = ['id', 'name', 'surname', 'email', 'phone', 'type'];

    public function renameFilter(string &$k, array|string &$v): bool
    {
        return in_array($k, $this->allowedFilters, true);
    }

    public function getDatatable(array $filters, string $where): void
    {
        if ($where !== '') {
            $this->whereAdd($where);
        }

        $this->orderBy('id', 'DESC');
    }

    public function behind(array &$items): void
    {
        if ($items === []) {
            return;
        }

        $types = \App\Enum\UsersType::data();

        foreach ($items as &$item) {
            $item['type_text'] = $types[(int)($item['type'] ?? 0)] ?? '—';
            $item['cdate_text'] = !empty($item['cdate'])
                ? date('d.m.Y H:i', strtotime($item['cdate']))
                : '—';
            $item['fio'] = trim(($item['name'] ?? '') . ' ' . ($item['surname'] ?? '')) ?: '—';
            $item['phone'] = trim((string)($item['phone'] ?? '')) ?: '—';
            $item['email'] = trim((string)($item['email'] ?? '')) ?: '—';
        }
    }
}
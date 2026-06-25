<?php

namespace App\Table;

use App\Model\ZastrakhovannyeModel;

class Zastrakhovannye extends ZastrakhovannyeModel implements Itable
{
    private array $allowedFilters = [
        'id',
        'operation_type',
        'surname',
        'name',
        'patronymic',
        'address',
        'policy_number',
        'phone_mobile',
        'program',
        'workplace',
    ];

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
        $labels = [
            'прикрепление' => 'Прикрепление',
            'открепление' => 'Открепление',
        ];

        foreach ($items as &$row) {
            $type = (string)($row['operation_type'] ?? '');
            $row['operation_type_label'] = $labels[$type] ?? ($type !== '' ? $type : '—');
        }
        unset($row);
    }
}

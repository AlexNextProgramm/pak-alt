<?php

namespace App\Table;

use Model\VariableModel;

class Variable extends VariableModel implements Itable
{
    private array $allowedFilters = ['id', 'type', 'name'];

    public function renameFilter(string &$k, array|string &$v): bool
    {
        return in_array($k, $this->allowedFilters, true);
    }

    public function getDatatable(array $filters, string $where): void
    {
        if ($where !== '') {
            $this->whereAdd($where);
        }

        $this->orderBy('type', 'ASC');
        $this->orderBy('name', 'ASC');
    }

    public function behind(array &$items): void
    {
    }
}

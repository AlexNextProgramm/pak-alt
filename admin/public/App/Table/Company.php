<?php

namespace App\Table;

use App\Model\CompanyModel;

class Company extends CompanyModel implements Itable
{
    private array $allowedFilters = ['id', 'name', 'email'];

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
    }
}
<?php

namespace App\Controller;

abstract class AdminSectionController extends PageController
{
    protected function crudMode(): string
    {
        $params = request()->input();

        if (!is_array($params) || !array_key_exists('id', $params)) {
            return 'table';
        }

        return 'form';
    }

    protected function crudIsEdit(): bool
    {
        $params = request()->input();

        if (!is_array($params) || !array_key_exists('id', $params)) {
            return false;
        }

        return $params['id'] !== '' && $params['id'] !== null;
    }

    protected function crudRecordId(): ?int
    {
        if (!$this->crudIsEdit()) {
            return null;
        }

        return (int)request()->input('id');
    }
}

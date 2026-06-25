<?php

namespace App\Table;

use Model\Table;
use Pet\Errors\AppException;
use Pet\Model\Model;
use Pet\Request\Request;
use Pet\Router\Response;
use Pet\Tools\Tools;


class Datatable
{
    public static $action = "datatable";
    public Itable|Model $model;
    protected $namespace = "App\\Table\\";
    public $result = [
        "item" => [],
        "pages" => [
            'all' => 0,
        ],
    ];

    /**
     * init
     *
     * @param  Request $request
     * @return array
     */
    final public function init(Request $request):array
    {
        $nameTable = str_replace(".", "\\", $request->header[self::$action]);
        $nameTable = ucfirst($nameTable);
        $nameClass = $this->namespace.$nameTable;

        if (!class_exists($nameClass)) {
            throw new AppException("Нет такого класса таблицы $nameClass", E_ERROR);
        }

        $this->model = new $nameClass();
        $this->datatables(json_decode(attr('table'), true));

        // Проверяем, есть ли ошибка IMAP в модели
        if (property_exists($this->model, 'imapError') && $this->model->imapError !== null) {
            Response::set(Response::TYPE_JSON);
            return [
                'item' => [],
                'pages' => ['all' => 0],
                'fire' => [
                    'type' => 'fire',
                    'status' => 'error',
                    'text' => $this->model->imapError,
                ],
            ];
        }

        $this->model->behind($this->result['item']);
        Response::set(Response::TYPE_JSON);

        return $this->result;
    }


    /**
     * datatables
     *
     * @param array $filter
     * @return void
     */
    public function datatables(array $filter): void
    {
        $pages = (object)$filter['pages'];
        $renameSearch = [];

        foreach ($filter['search'] as $k => $v) {
            if (!$this->model->renameFilter($k, $v, $filter['search'])) {
                continue;
            }
            $renameSearch[$k] = $v;
        }

        $filter['search'] = $renameSearch;
        $where = self::separateFilter($filter);

        $this->result['item'] = $this->model->find(callback: function (Model $m) use ($filter, $where, $pages) {
            $this->model->getDatatable($filter, $where);
            $countAll = $this->model->st ?? (clone $m)->select("COUNT(*) as st")->fetch(false)['st'];
            $this->result['pages']['all'] =  $countAll ?? 0;
            $m->limit($pages->limit ?? 10);
            $offset = (($pages->count - 1) * ($pages->limit ?? 0)) ?: 0;
            if (!empty($offset)) {
                $m->offset($offset);
            }
        });
    }

    /**
     * separateFilter
     *
     * @param  array $filter
     * @param  string $separate
     * @return string
     */
    public static function separateFilter(array $filter, string $separate = " AND "): string
    {
        if (!key_exists("search", $filter)) {
            return "";
        }

        $conditions = Tools::filter($filter['search'], function ($k, $v) {
            if (gettype($v) == 'array' && key_exists('sign', $v)) {
                $value = $v['value'];
                $value =  $v['sign'] == "LIKE" ? " '%$value%' " : " '$value' ";
                return $k . " " . $v['sign'] . $value;
            }

            if (gettype($v) == 'array') {
                $values = array_values(array_filter($v, static fn($item) => $item !== '' && $item !== null));
                if ($values === []) {
                    return '';
                }

                return $k . " IN (" . implode(", ", $values) . ")";
            }
            return $k . " = '$v'";
        });

        return implode(" $separate ", array_values(array_filter($conditions, static fn($item) => $item !== '')));
    }
}

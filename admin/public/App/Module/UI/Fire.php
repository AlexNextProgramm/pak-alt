<?php
namespace APP\Module\UI;

use Pet\Router\Response;

class Fire
{
    const SUCCESS = 'success';
    const ERROR = 'error';

    public $status;
    public $text = '';
    public $header = '';
    public $type = "fire";

    public function __construct($text = '', string $status = self::SUCCESS)
    {
        $this->text = $text;
        $this->header = $status == self::ERROR ? 'Ошибка!' : ($status == self::SUCCESS ? 'Успех':'');
        $this->status = $status;
    }
    public static function response($text = '', string $status = self::SUCCESS): void
    {
        Response::set(Response::TYPE_JSON);
        Response::die(new Fire($text, $status));
    }
}

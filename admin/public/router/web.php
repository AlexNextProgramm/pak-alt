<?php

use App\Controller\AjaxController;
use App\Controller\LoginController;
use App\Controller\ModalController;
use App\Controller\Page\HomeController;
use App\Form\Form;
use App\Module\Auth;
use App\Table\Datatable;
use Pet\Router\Error as RE;
use Pet\Router\Response;
use Pet\Router\Router;
use Module\Upload\Storage;

Router::$event = [
    Form::$action => [Form::class, 'init'],
    Datatable::$action => [function () {
        Auth::init();
        if (!Auth::$isAuth) {
            RE::setHttp(RE::STATUS_HTTP::FORBIDDEN);
            Response::die('Нет авторизации');
        }
        return (new Datatable())->init(request());
    }],
];

Router::get('/uploads/*', Storage::class);

Router::middleware(
    [Auth::class, 'init']
)->set(
    Router::get('/', [HomeController::class, 'index']),
    Router::get('/login', [LoginController::class, 'index']),
    Router::post('/ajax/{name}', [AjaxController::class, 'index']),
    Router::post('/modal', [ModalController::class, 'index']),
);

<?php

use App\Controller\AjaxController;
use App\Controller\LoginController;
use App\Controller\ModalController;
use App\Controller\Page\CompanyController;
use App\Controller\Page\CronReportController;
use App\Controller\Page\DocumentationController;
use App\Controller\Page\HomeController;
use App\Controller\Page\MailController;
use App\Controller\Page\VariablesController;
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
    Router::get('/company', [CompanyController::class, 'index']),
    Router::get('/mail', [MailController::class, 'index']),
    Router::get('/mail/attachment', [MailController::class, 'attachment']),
    Router::get('/variables', [VariablesController::class, 'index']),
    Router::get('/cron-report', [CronReportController::class, 'index']),
    Router::get('/documentation', [DocumentationController::class, 'index']),
    Router::get('/login', [LoginController::class, 'index']),
    Router::post('/ajax/{name}', [AjaxController::class, 'index']),
    Router::post('/modal', [ModalController::class, 'index']),
);

<?php

namespace APP\Controller;

use APP\Module\Auth;
use Pet\Controller;
use Pet\Request\Request;
use Pet\View\View;

class ModalController extends Controller
{
    public static $dir = 'modal';
    public function index()
    {
        Auth::init();
        return [
            'html' => View::getTemplate('modal.' . attr('template'), attr()),
        ];
    }
}
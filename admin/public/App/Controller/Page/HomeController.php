<?php

namespace App\Controller\Page;

use App\Controller\PageController;
use App\Module\Auth;
use Pet\Request\Request;

class HomeController extends PageController
{
    public function index(Request $request)
    {
        view('page.home.init', [
            'header' => 'Главная',
            'data' => Auth::$profile,
        ]);
    }
}

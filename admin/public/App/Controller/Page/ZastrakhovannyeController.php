<?php

namespace App\Controller\Page;

use App\Controller\PageController;
use Pet\Request\Request;
use Pet\View\View;

class ZastrakhovannyeController extends PageController
{
    public function index(Request $request)
    {
        View::append([
            'desktop' => '/zastrakhovannye/desktop.php',
        ]);

        view('page.zastrakhovannye.init', [
            'header' => 'Застрахованные',
        ]);
    }
}
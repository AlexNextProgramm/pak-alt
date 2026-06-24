<?php

namespace App\Controller\Page;

use App\Controller\PageController;
use Pet\Request\Request;
use Pet\View\View;

class VariablesController extends PageController
{
    public function index(Request $request)
    {
        View::append([
            'desktop' => '/variables/desktop.php',
        ]);

        view('page.variables.init', [
            'header' => 'Переменные',
        ]);
    }
}

<?php

namespace App\Controller\Page;

use App\Controller\PageController;
use Pet\Request\Request;
use Pet\View\View;

class AiController extends PageController
{
    public function index(Request $request)
    {
        View::append([
            'desktop' => '/ai/desktop.php',
        ]);

        view('page.ai.init', [
            'header' => 'AI — Обработка данных',
        ]);
    }
}
<?php

namespace App\Controller\Page;

use App\Controller\PageController;
use Pet\Request\Request;
use Pet\View\View;

class CompanyController extends PageController
{
    public function index(Request $request)
    {
        View::append([
            'desktop' => '/company/desktop.php',
        ]);

        view('page.company.init', [
            'header' => 'Страховые компании',
        ]);
    }
}
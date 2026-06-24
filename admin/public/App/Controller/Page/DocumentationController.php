<?php

namespace App\Controller\Page;

use App\Controller\PageController;
use Pet\Request\Request;

class DocumentationController extends PageController
{
    public function index(Request $request)
    {
        view('page.documentation.init', []);
    }
}
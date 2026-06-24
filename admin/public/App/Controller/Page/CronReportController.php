<?php

namespace App\Controller\Page;

use App\Controller\PageController;
use Pet\Request\Request;

class CronReportController extends PageController
{
    public function index(Request $request)
    {
        view('page.cron-report.init', []);
    }
}
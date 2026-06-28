<?php

namespace App\Controller\Page;

use App\Controller\PageController;
use App\Enum\UsersType;
use Module\Model\UserModel;
use Pet\Request\Request;
use Pet\View\View;

class UserController extends PageController
{
    public function index(Request $request)
    {
        View::append([
            'desktop' => '/user/desktop.php',
        ]);

        view('page.user.init', [
            'header' => 'Пользователи',
        ]);
    }
}
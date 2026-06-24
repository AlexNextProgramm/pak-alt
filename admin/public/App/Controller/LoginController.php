<?php

namespace App\Controller;

use App\Module\Auth;
use Pet\Controller;
use Pet\Request\Request;
use Pet\Router\Response;

class LoginController extends Controller
{
    public function index(Request $request)
    {
        if (Auth::$isAuth) {
            Response::redirect('/');
        }
        view('page.login.init', [
            'headerButtons' => [
            ]
        ]);
    }
}

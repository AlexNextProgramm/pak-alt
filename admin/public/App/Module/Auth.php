<?php

namespace App\Module;

use App\Model\UsersModel;
use Pet\Cookie\Cookie;
use Pet\Router\Response;

class Auth
{
    public static $isAuth = false;
    public static $profile = null;

    public static function init()
    {

        $auth = Cookie::get('auth');
        if (!$auth) {
            if (request()->path != '/login') {
                Response::redirect('/login');
            }
            return;
        }

        $Users = new UsersModel(['auth' => $auth]);

        if (!$Users->isInfo()) {
            Cookie::delete('auth');
            Response::redirect('/login');
        }
        self::$isAuth = true;
        self::$profile = $Users->data();
       // if (empty(self::$profile['img'])) {
        //    self::$profile['img'] = IMG_RELAT . "avatar_man/1.png";
       // }
    }

}

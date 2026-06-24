<?php

namespace APP\Controller;

use App\Enum\Menu;
use App\Module\Auth;

use Pet\Controller;
use Pet\View\View;

class PageController extends Controller
{

    public function __construct()
    {
        $pageInit = in_array(request()->path, ["/",""]) ?  '/home' : request()->path ;
        View::append(["desktop" =>  "$pageInit/desktop.php"]);
        View::append(["menu" => Menu::data((int)Auth::$profile['type'])]);
        // View::append(["headerLink" => MenuHeaderEnum::data()]);
    }
}

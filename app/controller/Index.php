<?php
namespace app\controller;

use app\BaseController;
use think\captcha\facade\Captcha;
use think\facade\View;

class Index extends BaseController
{
    public function index()
    {
        return View::fetch();
    }

    public function lyear_main()
    {
        return View::fetch();
    }
}

<?php
namespace app\controller;

use app\BaseController;
use think\captcha\facade\Captcha;
use think\facade\View;

class Index extends BaseController
{
    public function index()
    {
        return redirect("/user/index");
    }
}

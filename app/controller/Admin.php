<?php

namespace app\controller;
/*
 *                  _ooOoo_
 *                 o8888888o
 *                 88" . "88
 *                 (| -_- |)
 *                 O\  =  /O
 *              ____/`---'\____
 *            .'  \\|     |//  `.
 *           /  \\|||  :  |||//  \
 *          /  _||||| -:- |||||-  \
 *          |   | \\\  -  /// |   |
 *          | \_|  ''\---/''  |   |
 *          \  .-\__  `-`  ___/-. /
 *        ___`. .'  /--.--\  `. . __
 *     ."" '<  `.___\_<|>_/___.'  >'"".
 *    | | :  `- \`.;`\ _ /`;.`/ - ` : | |
 *    \  \ `-.   \_ __\ /__ _/   .-` /  /
 *======`-.____`-.___\_____/___.-`____.-'======
 *                   `=---='
 *^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^
 *             佛祖保佑       永无BUG
 */

use app\BaseController;
use think\facade\Db;
use think\facade\Request;
use think\facade\View;

class Admin extends BaseController
{
    public function index()
    {
        if (!$this->authenticate()) return redirect("/admin/login");
        $sql = Db::table("user")->where("ukey", cookie("ukey"))->find();
        View::assign($sql);
        return View::fetch();
    }

    public function lyear_main()
    {
        if (!$this->authenticate()) return redirect("/admin/login");
        View::assign("records", Db::table("records")->select()->count());
        View::assign("domain",DB::table("domain")->select()->count());
        View::assign("user",DB::table("user")->select()->count());
        View::assign("tickets",DB::table("tickets")->field('identity')->distinct(true)->count());
        //获取回复的工单数量
        $time = strtotime(date('Y-m-d',strtotime(date('Y-m-d',time()))-(86400*6)));//获取七天前的时间戳
        $count = [];
        for ($i=0; $i < 7; $i++) { 
            array_push($count,Db::table("tickets")->whereDay('up_time',date('Y-m-d',$time))->count());
            $time+=86400;
        }
        View::assign("tickets_count","[".implode(",",$count)."]");
        //获取审核量
        $time = strtotime(date('Y-m-d',strtotime(date('Y-m-d',time()))-(86400*6)));//获取七天前的时间戳
        $count = [];
        for ($i=0; $i < 7; $i++) { 
            array_push($count,Db::table("censor")->whereDay('create_time',date('Y-m-d',$time))->count());
            $time+=86400;
        }
        View::assign("censor_count","[".implode(",",$count)."]");
        return View::fetch();
    }

    public function invite()
    {
        return View::fetch();
    }

    public function login_api()
    {

        if (!captcha_check(Request::param("captcha"))) {
            // 验证码错误
            return json(['code' => 0, 'msg' => '验证码错误']);
        }
        $db = Db::table("user")->where("username", Request::param("username"))->find();
        if ($db) {
            if ($db["password"] != md5(Request::param("password"))) {
                return json(['code' => 0, 'msg' => '密码错误']);
            }
        } else {
            return json(['code' => 0, 'msg' => '用户不存在']);
        }
        $ukey = md5($db["username"] . $db["password"] . time());
        $db["ukey"] = $ukey;
        Db::table("user")->where("username", Request::param("username"))->update(["ukey" => $ukey]);
        if (Request::param("remember") == "on") {
            cookie("ukey", $ukey, 3600 * 24 * 5);
        } else {
            cookie("ukey", $ukey);
        }
        return json([
            "code" => 200,
            "msg" => "登录成功"
        ]);
    }

    public function api()
    {
        if (!$this->authenticate()) {
            return json([
                "code" => 0,
                "msg" => "鉴权失败"
            ]);
        } //鉴权错误
        switch(Request::param("tag")){
            case "update_password":
                $db = Db::table("user")->where("ukey", cookie("ukey"))->find();
                if(Db::table("user")->where("id",$db["id"])->update(["password"=>md5(Request::param("password"))])){
                    return json([
                        "code"=>200,
                        "msg"=>"修改成功"
                    ]);
                }else{
                    return json([
                        "code"=>0,
                        "msg"=>"修改失败"
                    ]);
                }
                break;
        }
    }

    public function login()
    {
        cookie("ukey", null);
        return View::fetch();
    }

    private function authenticate()
    {
        $sql = Db::table("user")->where("ukey", cookie("ukey"))->find();
        if (!$sql) {
            return false;
        }
        $sql1 = Db::table("auth")->where("auth", $sql["auth"])->find();
        if (!$sql1 || $sql1["name"] != "admin") {
            //鉴权不通过
            return false;
        }
        return true;
    }
}

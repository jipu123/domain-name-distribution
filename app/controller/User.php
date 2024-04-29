<?php
namespace app\controller;

use app\BaseController;
use think\facade\Db;
use think\facade\Request;
use think\facade\View;

class User extends BaseController
{
    public function index()
    {
        if (!$this->authenticate()) return redirect("/user/login");
        $sql = Db::table("user")->where("ukey", cookie("ukey"))->find();
        View::assign($sql);
        return View::fetch();
    }

    public function lyear_main()
    {
        return View::fetch();
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
                if (Db::table("user")->where("id", $db["id"])->update(["password" => md5(Request::param("password"))])) {
                    return json([
                        "code" => 200,
                        "msg" => "修改成功"
                    ]);
                } else {
                    return json([
                        "code" => 0,
                        "msg" => "修改失败"
                    ]);
                }
                break;
            case "update_email":
                $db = Db::table("user")->where("ukey", cookie("ukey"))->find();
                if (Db::table("user")->where("id", $db["id"])->update(["email" => Request::param("email")])) {
                    return json([
                        "code" => 200,
                        "msg" => "修改成功"
                    ]);
                } else {
                    return json([
                        "code" => 0,
                        "msg" => "修改失败"
                    ]);
                }
                break;
        }
    }

    public function enroll_api()
    {
        if (!captcha_check(Request::param("captcha"))) {
            // 验证码错误
            return json(['code' => 0, 'msg' => '验证码错误']);
        }
        $db = Db::table("user")->where("username", Request::param("username"))->find();
        if ($db) {
            return json(['code' => 0, 'msg' => '用户已存在']);
        }
        $db = Db::table("user")->where("email", Request::param("email"))->find();
        if ($db) {
            return json(['code' => 0, 'msg' => '邮箱已存在']);
        }
        $db = Db::table("user")->where("usernick",Request::param("usernick"))->find();
        if ($db) {
            return json(['code' => 0, 'msg' => '昵称已存在']);
        }
        $db = Db::table("invite")->where("value",Request::param("invite"))->where("is_lock",0)->whereTime("over_time",">",time())->find();
        if (!$db) {
            return json(['code' => 0, 'msg' => '邀请码错误']);
        }
        if($db["current"]>=$db["max"]){
            return json(['code' => 0, 'msg' => '邀请码已失效']);
        }
        Db::table("invite")->where("id",$db["id"])->inc("current")->update();
        $ukey = md5(Request::param("username").Request::param("password").time());
        $data = [
            "username" => Request::param("username"),
            "password" => md5(Request::param("password")),
            "email" => Request::param("email"),
            "usernick" => Request::param("usernick"),
            "record_num" => $db["record_num"],
            "domain_num" => $db["domain_num"],
            "ukey" => $ukey,
            "update_time" => date("Y-m-d H:i:s", time())
        ];
        Db::table("user")->insert($data);
        cookie("ukey", $ukey);
        return json([
            "code" => 200,
            "msg" => "注册成功"
        ]);
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

    public function login()
    {
        cookie("ukey", null);
        return View::fetch();
    }

    public function enroll()
    {
        return View::fetch();
    }

    private function authenticate()
    {
        $sql = Db::table("user")->where("ukey", cookie("ukey"))->find();
        if (!$sql) {
            return false;
        }
        Db::table("user")->where("id",$sql["id"])->update(["update_time" => date("Y-m-d H:i:s",time())]);
        return true;
    }
}

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
        View::assign("domain", DB::table("domain")->select()->count());
        View::assign("user", DB::table("user")->select()->count());
        View::assign("tickets", DB::table("ticket_from")->count());
        //获取回复的工单数量
        $time = strtotime(date('Y-m-d', strtotime(date('Y-m-d', time())) - (86400 * 6))); //获取七天前的时间戳
        $count = [];
        for ($i = 0; $i < 7; $i++) {
            array_push($count, Db::table("ticket_from")->whereDay('update_time', date('Y-m-d', $time))->count());
            $time += 86400;
        }
        View::assign("tickets_count", "[" . implode(",", $count) . "]");
        //获取审核量
        $time = strtotime(date('Y-m-d', strtotime(date('Y-m-d', time())) - (86400 * 6))); //获取七天前的时间戳
        $count = [];
        for ($i = 0; $i < 7; $i++) {
            array_push($count, Db::table("censor")->whereDay('create_time', date('Y-m-d', $time))->count());
            $time += 86400;
        }
        View::assign("censor_count", "[" . implode(",", $count) . "]");
        return View::fetch();
    }

    public function invite()
    {
        if (!$this->authenticate()) return redirect("/admin/login");
        return View::fetch();
    }
    public function user()
    {
        if (!$this->authenticate()) return redirect("/admin/login");
        return View::fetch();
    }
    public function domain()
    {
        if (!$this->authenticate()) return redirect("/admin/login");
        return View::fetch();
    }
    public function tickets()
    {
        if (!$this->authenticate()) return redirect("/admin/login");
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
        switch (Request::param("tag")) {
            case "tickets":
                if(Request::param("act")=="list"){
                    $db = Db::table("ticket_from")->order(Request::param("sort"), Request::param("sortOrder"))->paginate([
                        "list_rows" => Request::param("limit"),
                        "page" => Request::param("page")
                    ])->each(function($item,$key){
                        $item["count"] = Db::table("tickets")->where("identity",$item["id"])->count();
                        return $item;
                    })->toArray();
                    return json([
                        "total" => $db["total"],
                        "rows" => $db["data"]
                    ]);
                }
                break;
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
            case "invite":
                if (Request::param("act") == "list") {
                $db = Db::table("invite")->order(Request::param("sort"), Request::param("sortOrder"))->paginate([
                    "list_rows" => Request::param("limit"),
                    "page" => Request::param("page")
                ])->toArray();
                return json([
                    "total" => $db["total"],
                    "rows" => $db["data"]
                ]);
                } elseif (Request::param("act") == "xg") {
                    $db = Db::table("invite")->where("id", Request::param("id"))->update(["is_lock" => Request::param("is_lock")]);
                    if ($db) {
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
                } elseif (Request::param("act") == "add") {
                    $invite = trim(Request::param("invite"));
                    $current = Request::param("current");
                    $domain = Request::param("domain");
                    $record = Request::param("record");
                    $time = Request::param("time");
                    if (empty($invite)) {
                        return json([
                            "code" => 0,
                            "msg" => "邀请码不能为空"
                        ]);
                    }
                    if (!preg_match("/^([0-9]{4})-([0-9]{2})-([0-9]{2})$/", $time)) {
                        return json([
                            "code" => 0,
                            "msg" => "时间格式必须为YYYY-mm-dd"
                        ]);
                    }
                    if (!is_numeric($current) || !is_numeric($domain) || !is_numeric($record)) {
                        return json([
                            "code" => 0,
                            "msg" => "必须为数字"
                        ]);
                    }
                    if (Db::table("invite")->where("value", $invite)->find()) {
                        return json([
                            "code" => 0,
                            "msg" => "邀请码已存在"
                        ]);
                    }
                    if (Db::table("invite")->insert([
                        "value" => $invite,
                        "max" => $current,
                        "domain_num" => $domain,
                        "record_num" => $record,
                        "over_time" => $time
                    ])) {
                        return json([
                            "code" => 200,
                            "msg" => "添加成功"
                        ]);
                    } else {
                        return json([
                            "code" => 0,
                            "msg" => "添加失败"
                        ]);
                    }
                } else {
                    return json([
                        "code" => 0,
                        "msg" => "未知操作"
                    ]);
                }
                break;
            case "user":
                if(Request::param("act") == "list"){
                    $db = Db::table("user_auth")->order(Request::param("sort"), Request::param("sortOrder"))->paginate([
                        "list_rows" => Request::param("limit"),
                        "page" => Request::param("page")
                    ])->toArray();
                    return json([
                        "total" => $db["total"],
                        "rows" => $db["data"]
                    ]);
                }else if(Request::param("act") == "auth"){
                    $db = Db::table("auth")->select()->toarray();
                    return json($db);
                }else if(Request::param("act") == "add"){
                    $usernick = trim(Request::param("usernick"));
                    $username = trim(Request::param("username"));
                    $password = trim(Request::param("password"));
                    $email = trim(Request::param("email"));
                    $domain = Request::param("domain");
                    $record = Request::param("record");
                    $auth = Request::param("auth");
                    if(empty($username) || empty($password) || empty($usernick) || empty($email)){
                        return json([
                            "code" => 0,
                            "msg" => "内容不完整"
                        ]);
                    }
                    if(Db::table("user")->where("username", $username)->find()){
                        return json([
                            "code" => 0,
                            "msg" => "用户名已存在"
                        ]);
                    }
                    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                        return json([
                            "code" => 0,
                            "msg" => "邮箱格式不正确"
                        ]);
                    }
                    if(!is_numeric($domain) || !is_numeric($record)){
                        return json([
                            "code" => 0,
                            "msg" => "必须为数字"
                        ]);
                    }
                    if(!Db::table("auth")->where("auth", $auth)->find()){
                        return json([
                            "code" => 0,
                            "msg" => "权限不存在"
                        ]);
                    }
                    if(Db::table("user")->insert([
                        "username" => $username,
                        "password" => md5($password),
                        "email" => $email,
                        "usernick" => $usernick,
                        "domain_num" => $domain,
                        "record_num" => $record,
                        "ukey" => md5($username . md5($password) . time()),
                        "auth" => $auth
                    ])){
                        return json([
                            "code" => 200,
                            "msg" => "添加成功"
                        ]);
                    }else{
                        return json([
                            "code" => 0,
                            "msg" => "添加失败"
                        ]);
                    }

                }else if(Request::param("act") == "xg_lock"){
                    $db = Db::table("user_auth")->where("id", Request::param("id"))->update(["is_lock" => Request::param("is_lock")]);
                    if ($db) {
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
                }else if(Request::param("act") == "xg_nick"){
                    if(empty(Request::param("usernick"))){
                        return json([
                            "code" => 0,
                            "msg" => "昵称不能为空"
                        ]);
                    }
                    $db = Db::table("user_auth")->where("id", Request::param("id"))->update(["usernick" => Request::param("usernick")]);
                    if ($db) {
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
                }else if(Request::param("act") == "xg_name"){
                    if(empty(Request::param("username"))){
                        return json([
                            "code" => 0,
                            "msg" => "用户名不能为空"
                        ]);
                    }
                    $db = Db::table("user_auth")->where("id", Request::param("id"))->update(["username" => Request::param("username")]);
                    if ($db) {
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
                }else if(Request::param("act") == "xg_auth"){
                    if(!Db::table("auth")->where("auth", Request::param("auth"))->find()){
                        return json([
                            "code" => 0,
                            "msg" => "权限不存在"
                        ]);
                    }
                    $db = Db::table("user")->where("id", Request::param("id"))->update(["auth" => Request::param("auth")]);
                    if ($db) {
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
                }else if(Request::param("act") == "xg_email"){
                    if (!filter_var(Request::param("email"), FILTER_VALIDATE_EMAIL)) {
                        return json([
                            "code" => 0,
                            "msg" => "邮箱格式不正确"
                        ]);
                    }
                    $db = Db::table("user")->where("id", Request::param("id"))->update(["email" => Request::param("email")]);
                    if ($db) {
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
                }else if(Request::param("act") == "xg_domain"){
                    
                    if(!is_numeric(Request::param("domain_num"))){
                        return json([
                            "code" => 0,
                            "msg" => "必须为数字"
                        ]);
                    }
                    if(Request::param("domain_num") < 0){
                        return json([
                            "code" => 0,
                            "msg" => "不能小于0"
                        ]);
                    }
                    $db = Db::table("user")->where("id", Request::param("id"))->update(["domain_num" => Request::param("domain_num")]);
                    if ($db) {
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
                }else if(Request::param("act") == "xg_record"){
                    if(!is_numeric(Request::param("record_num"))){
                        return json([
                            "code" => 0,
                            "msg" => "必须为数字"
                        ]);
                    }
                    if(Request::param("record_num") < 0){
                        return json([
                            "code" => 0,
                            "msg" => "不能小于0"
                        ]);
                    }
                    $db = Db::table("user")->where("id", Request::param("id"))->update(["record_num" => Request::param("record_num")]);
                    if ($db) {
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
                }else if(Request::param("act") == "xg_pass"){
                    if(empty(Request::param("password"))){
                        return json([
                            "code" => 0,
                            "msg" => "密码不能为空"
                        ]);
                    }
                    $db = Db::table("user")->where("id", Request::param("id"))->update(["password" => md5(Request::param("password"))]);
                    if ($db) {
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
                }
                break;
                case "domain":
                    if(Request::param("act") == "list"){
                        $db = Db::table("domain")->order(Request::param("sort"), Request::param("sortOrder"))->paginate([
                            "list_rows" => Request::param("limit"),
                            "page" => Request::param("page")
                        ])->toArray();
                        return json([
                            "total" => $db["total"],
                            "rows" => $db["data"]
                        ]);
                    }else if(Request::param("act") == "xg_state"){
                        $db = Db::table("domain")->where("id", Request::param("id"))->update(["state" => Request::param("state")]);
                        if ($db) {
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
                    }else if(Request::param("act") == "xg_ba"){
                        $db = Db::table("domain")->where("id", Request::param("id"))->update(["is_record" => Request::param("is_record")]);
                        if ($db) {
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
                    }else if(Request::param("act") == "add"){
                        if(empty(Request::param("domain"))){
                            return json([
                                "code" => 0,
                                "msg" => "域名不能为空"
                            ]);
                        }
                        if (!preg_match('/^(?:[-A-Za-z0-9]+\.)+[A-Za-z]{2,6}$/', Request::param("domain"))) {
                            return json([
                                "code" => 0,
                                "msg" => "域名格式不正确"
                            ]);
                        }
                        $db = Db::table("domain")->where("dom", Request::param("domain"))->find();
                        if($db){
                            return json([
                                "code" => 0,
                                "msg" => "域名已存在"
                            ]);
                        }
                        $db = Db::table("domain")->insert([
                            "dom" => Request::param("domain"),
                            "is_record" => Request::param("record"),
                            "state" => 1
                        ]);
                        if ($db) {
                            return json([
                                "code" => 200,
                                "msg" => "添加成功"
                            ]);
                        } else {
                            return json([
                                "code" => 0,
                                "msg" => "添加失败"
                            ]);
                        }
                    }
                    break;
        }
        print_r(Request::param());
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
        Db::table("user")->where("id",$sql["id"])->update(["update_time" => date("Y-m-d H:i:s",time())]);
        return true;
    }
}

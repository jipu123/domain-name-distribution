<?php

namespace app\controller;


use app\BaseController;
use think\facade\Db;
use think\facade\Request;
use think\facade\View;
use think\facade\Env;

use AlibabaCloud\SDK\Alidns\V20150109\Alidns;
use \Exception;
use AlibabaCloud\Tea\Exception\TeaError;
use AlibabaCloud\Tea\Utils\Utils;

use Darabonba\OpenApi\Models\Config;
use AlibabaCloud\SDK\Alidns\V20150109\Models\DeleteDomainRecordRequest;
use AlibabaCloud\Tea\Utils\Utils\RuntimeOptions;

use PHPMailer\PHPMailer\PHPMailer;

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

    public function dns()
    {
        if (!$this->authenticate()) return redirect("/admin/login");
        return View::fetch();
    }

    public function censor()
    {
        if (!$this->authenticate()) return redirect("/admin/login");
        return View::fetch();
    }

    public function censor_api()
    {
        if (!$this->authenticate()) {
            return json([
                "code" => 0,
                "msg" => "鉴权失败"
            ]);
        } //鉴权错误
        switch (Request::param("tag")) {
            case "list":
                $db = Db::table("user_records")->where("is_delect", "0")->order(Request::param("sort"), Request::param("sortOrder"))->paginate([
                    "list_rows" => Request::param("limit"),
                    "page" => Request::param("page")
                ])->each(function ($item, $key) {
                    unset($item["ukey"]);
                    return $item;
                })->toArray();
                return json([
                    "total" => $db["total"],
                    "rows" => $db["data"]
                ]);
                break;
            case "ban":
                $record = Db::table("records")->where("id", Request::param("dns_id"))->find();
                $sub = $record["sub"];
                $domain = Db::table("domain")->where("id", $record["dom_id"])->where("state", 0)->find();
                $user = Db::table("user")->where("ukey", cookie("ukey"))->find();
                $config = new Config([
                    'accessKeyId' => Env::get("ALIYUN.ALIBABA_CLOUD_ACCESS_KEY_ID"),
                    'accessKeySecret' => Env::get("ALIYUN.ALIBABA_CLOUD_ACCESS_KEY_SECRET")
                ]);
                $config->endpoint = "alidns.cn-hangzhou.aliyuncs.com";
                $client = new Alidns($config);
                $deleteDomainRecordRequest = new DeleteDomainRecordRequest([
                    "recordId" => $record["RecordId"],
                ]);
                $runtime = new RuntimeOptions([]);
                try {
                    // 修改成功
                    $res = $client->deleteDomainRecordWithOptions($deleteDomainRecordRequest, $runtime);
                    if ($res->statusCode != 200) {
                        return json(["code" => 300, "msg" => "上游服务器错误"]);
                    }
                    $res = $res->body;
                } catch (Exception $error) {
                    return json(["code" => 300, "msg" => $error->getMessage()]);
                }
                Db::table("censor")->insert([
                    "user_id" => $user["id"],
                    "domain_id" => Request::param("dns_id"),
                    "outcome" => 1,
                    "comment" => Request::param("comment"),
                ]);
                $record = Db::table("records")->where("id", Request::param("dns_id"))->update([
                    "is_delect" => 2,
                    "audit" => date("Y-m-d H:i:s", time())
                ]); //更新数据库的datetime时间
                $body = $this->MailBody($user["usernick"],3,$sub.".".$domain["dom"],$record["type"],$record["value"],Request::param("comment"));
                $this->SendEmail($user["email"],$body);
                return json(["code" => 00, "msg" => "封禁成功"]);
                break;
            case "censor":
                $user = Db::table("user")->where("ukey", cookie("ukey"))->find();
                Db::table("censor")->insert([
                    "user_id" => $user["id"],
                    "domain_id" => Request::param("dns_id")
                ]);
                return json(["code" => 0, "msg" => "已提交审核"]);
                break;
        }
    }

    public function dns_api()
    {
        if (!$this->authenticate()) {
            return json([
                "code" => 0,
                "msg" => "鉴权失败"
            ]);
        } //鉴权错误
        switch (Request::param("tag")) {
            case "list":

                if (strlen(Request::param("query")) > 0) {
                    $db = Db::table("user_records")->where("usernick", "like", "%" . Request::param("query") . "%")->order(Request::param("sort"), Request::param("sortOrder"))->paginate([
                        "list_rows" => Request::param("limit"),
                        "page" => Request::param("page")
                    ])->each(function ($item, $key) {
                        unset($item["ukey"]);
                        return $item;
                    })->toArray();;
                } else {
                    $db = Db::table("user_records")->order(Request::param("sort"), Request::param("sortOrder"))->paginate([
                        "list_rows" => Request::param("limit"),
                        "page" => Request::param("page")
                    ])->each(function ($item, $key) {
                        unset($item["ukey"]);
                        return $item;
                    })->toArray();
                }
                return json([
                    "total" => $db["total"],
                    "rows" => $db["data"]
                ]);
                break;
        }
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
                if (Request::param("act") == "list") {
                    $db = Db::table("ticket_from")->order(Request::param("sort"), Request::param("sortOrder"))->paginate([
                        "list_rows" => Request::param("limit"),
                        "page" => Request::param("page")
                    ])->each(function ($item, $key) {
                        $item["count"] = Db::table("tickets")->where("identity", $item["id"])->count();
                        return $item;
                    })->toArray();
                    return json([
                        "total" => $db["total"],
                        "rows" => $db["data"]
                    ]);
                }else  if(Request::param("act") == "xg"){
                    $db = Db::table("ticket_from")->where("id", Request::param("id"))->update(["is_lock" => Request::param("is_lock")]);
                    if ($db) {
                        return json([
                            "code" => 0,
                            "msg" => "修改成功"
                        ]);
                    } else {
                        return json([
                            "code" => 200,
                            "msg" => "修改失败"
                        ]);
                    }
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
                if (Request::param("act") == "list") {
                    $db = Db::table("user_auth")->order(Request::param("sort"), Request::param("sortOrder"))->paginate([
                        "list_rows" => Request::param("limit"),
                        "page" => Request::param("page")
                    ])->toArray();
                    return json([
                        "total" => $db["total"],
                        "rows" => $db["data"]
                    ]);
                } else if (Request::param("act") == "auth") {
                    $db = Db::table("auth")->select()->toarray();
                    return json($db);
                } else if (Request::param("act") == "add") {
                    $usernick = trim(Request::param("usernick"));
                    $username = trim(Request::param("username"));
                    $password = trim(Request::param("password"));
                    $email = trim(Request::param("email"));
                    $domain = Request::param("domain");
                    $record = Request::param("record");
                    $auth = Request::param("auth");
                    if (empty($username) || empty($password) || empty($usernick) || empty($email)) {
                        return json([
                            "code" => 0,
                            "msg" => "内容不完整"
                        ]);
                    }
                    if (Db::table("user")->where("username", $username)->find()) {
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
                    if (!is_numeric($domain) || !is_numeric($record)) {
                        return json([
                            "code" => 0,
                            "msg" => "必须为数字"
                        ]);
                    }
                    if (!Db::table("auth")->where("auth", $auth)->find()) {
                        return json([
                            "code" => 0,
                            "msg" => "权限不存在"
                        ]);
                    }
                    if (Db::table("user")->insert([
                        "username" => $username,
                        "password" => md5($password),
                        "email" => $email,
                        "usernick" => $usernick,
                        "domain_num" => $domain,
                        "record_num" => $record,
                        "ukey" => md5($username . md5($password) . time()),
                        "auth" => $auth
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
                } else if (Request::param("act") == "xg_lock") {
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
                } else if (Request::param("act") == "xg_nick") {
                    if (empty(Request::param("usernick"))) {
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
                } else if (Request::param("act") == "xg_name") {
                    if (empty(Request::param("username"))) {
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
                } else if (Request::param("act") == "xg_auth") {
                    if (!Db::table("auth")->where("auth", Request::param("auth"))->find()) {
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
                } else if (Request::param("act") == "xg_email") {
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
                } else if (Request::param("act") == "xg_domain") {

                    if (!is_numeric(Request::param("domain_num"))) {
                        return json([
                            "code" => 0,
                            "msg" => "必须为数字"
                        ]);
                    }
                    if (Request::param("domain_num") < 0) {
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
                } else if (Request::param("act") == "xg_record") {
                    if (!is_numeric(Request::param("record_num"))) {
                        return json([
                            "code" => 0,
                            "msg" => "必须为数字"
                        ]);
                    }
                    if (Request::param("record_num") < 0) {
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
                } else if (Request::param("act") == "xg_pass") {
                    if (empty(Request::param("password"))) {
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
                if (Request::param("act") == "list") {
                    $db = Db::table("domain")->order(Request::param("sort"), Request::param("sortOrder"))->paginate([
                        "list_rows" => Request::param("limit"),
                        "page" => Request::param("page")
                    ])->toArray();
                    return json([
                        "total" => $db["total"],
                        "rows" => $db["data"]
                    ]);
                } else if (Request::param("act") == "xg_state") {
                    $db = Db::table("domain")->where("id", Request::param("id"))->update(["state" => Request::param("state")]);
                    if ($db) {
                        return json([
                            "code" => 0,
                            "msg" => "修改成功"
                        ]);
                    } else {
                        return json([
                            "code" => 200,
                            "msg" => "修改失败"
                        ]);
                    }
                } else if (Request::param("act") == "xg_ba") {
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
                } else if (Request::param("act") == "add") {
                    if (empty(Request::param("domain"))) {
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
                    if ($db) {
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
        Db::table("user")->where("id", $sql["id"])->update(["update_time" => date("Y-m-d H:i:s", time())]);
        return true;
    }

    private function MailBody($usernick, $type, $domain, $DnsType, $value, $msg)
    {
        $body = "<div><h4>尊敬的用户 " . $usernick . " :</h4><p>";
        if ($type == 1) {
            $body = $body . "您对域名" . $domain . "进行了解析记录的删除操作。";
        } else if ($type == 2) {
            $body = $body . "您添加了域名" . $domain;
        } else {
            $body = $body . "管理员封禁了您的域名" . $domain . ",封禁域名不会返还域名额度";
        }
        $body = $body . "</p><table><tr><th>记录类型</th><th>主机记录</th><th>域名</th></tr><tr><td>" . $DnsType .
            "</td><td>" . $value . "</td><td>" . $domain . "</td></tr></table>";
        if ($type != 1 && $type != 2) {
            $body = $body . "<p>封禁原因:" . $msg . "</p>";
        }
        $body = $body . "</div>";
        return $body;
    }

    private function SendEmail($target, $body)
    {
        $mail = new PHPMailer(true);                              // Passing `true` enables exceptions
        try {
            //服务器配置
            $mail->CharSet = "UTF-8";                     //设定邮件编码
            $mail->SMTPDebug = 0;                        // 调试模式输出
            $mail->isSMTP();                             // 使用SMTP
            $mail->Host = Env::get("EMAIL.SERVER");                // SMTP服务器
            $mail->SMTPAuth = true;                      // 允许 SMTP 认证
            $mail->Username = Env::get("EMAIL.USEREMAIL");               // SMTP 用户名  即邮箱的用户名
            $mail->Password = Env::get("EMAIL.PWD");               // SMTP 密码  部分邮箱是授权码(例如163邮箱)
            $mail->SMTPSecure = Env::get("EMAIL.SMTPSECURE");                    // 允许 TLS 或者ssl协议
            $mail->Port = Env::get("EMAIL.PROT");                            // 服务器端口 25 或者465 具体要看邮箱服务器支持

            $mail->setFrom(Env::get("EMAIL.USEREMAIL"), '学习域名分发');  //发件人
            $mail->addAddress($target, '尊敬的用户');  // 收件人
            $mail->addReplyTo(Env::get("EMAIL.USEREMAIL"), 'info'); //回复的时候回复给哪个邮箱 建议和发件人一致

            //Content
            $mail->isHTML(true);                                  // 是否以HTML文档格式发送  发送后客户端可直接显示对应HTML内容
            $mail->Subject = '你的dns解析改变,请查收';
            $mail->Body    = $body;

            $mail->send();
            return true;
        } catch (Exception $e) {
            echo '邮件发送失败: ', $mail->ErrorInfo;
        }
    }
}

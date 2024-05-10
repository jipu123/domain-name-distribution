<?php

namespace AlibabaCloud\SDK\Sample;

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

use PHPMailer\PHPMailer\PHPMailer;

use Darabonba\OpenApi\Models\Config;
use AlibabaCloud\SDK\Alidns\V20150109\Models\DescribeSubDomainRecordsRequest;
use AlibabaCloud\SDK\Alidns\V20150109\Models\DeleteDomainRecordRequest;
use AlibabaCloud\SDK\Alidns\V20150109\Models\AddDomainRecordRequest;
use AlibabaCloud\Tea\Utils\Utils\RuntimeOptions;

class user extends BaseController
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
        if (!$this->authenticate()) return redirect("/user/login");
        $user = Db::table("user")->where("ukey", cookie("ukey"))->find();
        View::assign($user);
        View::assign("record_count", Db::table("records")->where("user_id", $user["id"])->count());
        View::assign("domain_count", Db::table("domain")->count());
        View::assign("domain", Db::table("domain")->select()->each(function ($item, $key) {
            $item["nums"] = Db::table("records")->where("dom_id", $item["id"])->count();
            return $item;
        })->toArray());
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
        switch (Request::param("tag")) {
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

    public function parse()
    { //用户的解析
        if (!$this->authenticate()) return redirect("/user/login");
        View::assign("domains", Db::table("domain")->select());
        return View::fetch();
    }

    public function reply()
    {
        if (!$this->authenticate()) return redirect("/user/login");
        return View::fetch();
    }

    public function reply_api()
    {
        if (!$this->authenticate()) {
            return json([
                "code" => 0,
                "msg" => "鉴权失败"
            ]);
        } //鉴权错误
        switch (Request::param("tag")) {
            case "from":
                $user = Db::table("user")->where("ukey", cookie("ukey"))->find();
                $db = Db::table("ticket_from")->where("user_id", $user["id"])->order(Request::param("sort"), Request::param("sortOrder"))->paginate([
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
                break;
            case "list":
                $user = Db::table("user")->where("ukey", cookie("ukey"))->find();
                $sql = Db::table("ticket_from")->where("user_id", $user["id"])->where("id", Request::param("id"))->find();
                if (!$sql) {
                    return json([
                        "code" => 200,
                        "msg" => "无权访问"
                    ]);
                }
                $db = Db::table("ticket_view")->where("identity", Request::param("id"))->select()->toArray();
                $ticket = Db::table("ticket_from")->where("id", Request::param("id"))->find();
                return json([
                    "code" => 0,
                    "total" => count($db),
                    "rows" => $db,
                    "user" => $user["id"],
                    "title" => $ticket["title"],
                ]);
                break;
            case "add":
                $user = Db::table("user")->where("ukey", cookie("ukey"))->find();
                $data = [
                    "title" =>Request::param("title"),
                    "user_id" => $user["id"],
                ];
                Db::table("ticket_from")->insert($data);
                $db = Db::table("ticket_from")->where("user_id", $user["id"])->order("id", "desc")->find();
                return json([
                    "id" => $db["id"],
                    "code" => 0,
                ]);
                break;
            case "msg":
                $user = Db::table("user")->where("ukey", cookie("ukey"))->find();
                $sql = Db::table("ticket_from")->where("user_id", $user["id"])->where("id", Request::param("id"))->find();
                if (!$sql) {
                    return json([
                        "code" => 200,
                        "msg" => "无权访问"
                    ]);
                }
                if (Request::param("msg") == "") {
                    return json([
                        "code" => 200,
                        "msg" => "内容不能为空"
                    ]);
                }
                if ($sql["is_lock"] == 1) {
                    return json([
                        "code" => 200,
                        "msg" => "工单已关闭"
                    ]);
                }
                $body = $this->ReplyEmail($user, Request::param("msg"), $sql);
                $admin = Db::table("user")->where("auth", 999)->find();
                $this->SendEmail($admin["email"], $body, "工单回复: " . $sql["title"]);
                $data = [
                    "identity" => Request::param("id"),
                    "user" => $user["id"],
                    "msg" => Request::param("msg"),
                ];
                Db::table("tickets")->insert($data);
                Db::table("ticket_from")->where('id', Request::param("id"))->update([
                    "update_time" =>date("Y-m-d H:i:s", time()),
                ]);
                return json([
                    "code" => 0,
                    "msg" => "回复成功"
                ]);
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
        $db = Db::table("user")->where("usernick", Request::param("usernick"))->find();
        if ($db) {
            return json(['code' => 0, 'msg' => '昵称已存在']);
        }
        $db = Db::table("invite")->where("value", Request::param("invite"))->where("is_lock", 0)->whereTime("over_time", ">", time())->find();
        if (!$db) {
            return json(['code' => 0, 'msg' => '邀请码错误']);
        }
        if ($db["current"] >= $db["max"]) {
            return json(['code' => 0, 'msg' => '邀请码已失效']);
        }
        Db::table("invite")->where("id", $db["id"])->inc("current")->update();
        $ukey = md5(Request::param("username") . Request::param("password") . time());
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

    public function parse_api()
    {
        if (!$this->authenticate()) return json(["code" => 0, "msg" => "鉴权失败"]);
        if (!Request::param("act")) {
            //查询
            $user = Db::table("user")->where("ukey", cookie("ukey"))->find();
            $db = Db::table("user_records")->where("ukey", cookie("ukey"))->order(Request::param("sort"), Request::param("sortOrder"))->paginate([
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
        } else if (Request::param("act") == "add") {
            //添加
            $records = Db::table("records")->where("sub", Request::param("sub"))->where("dom_id", Request::param("domain"))->where("is_delect", 0)->find();
            if ($records) {
                return json(["code" => 300, "msg" => "解析记录已存在"]);
            }
            $user = Db::table("user")->where("ukey", cookie("ukey"))->find();
            //验证子域名是否合法
            if (!preg_match("/^[a-zA-Z0-9]+$/", Request::param("sub"))) {
                return json(["code" => 300, "msg" => "子域名不合法"]);
            }
            //验证用户约是否足够
            $domain = Db::table("domain")->where("id", Request::param("domain"))->where("state", 0)->find();
            if ($domain["is_record"] == 1) {
                //已经备案
                if ($user["record_num"] <= 0) {
                    return json(["code" => 300, "msg" => "备案域名次数不足"]);
                }
            } else {
                //未备案
                if ($user["domain_num"] <= 0) {
                    return json(["code" => 300, "msg" => "普通域名次数不足"]);
                }
            }
            //验证子域名是否存在
            $config = new Config([
                'accessKeyId' => Env::get("ALIYUN.ALIBABA_CLOUD_ACCESS_KEY_ID"),
                'accessKeySecret' => Env::get("ALIYUN.ALIBABA_CLOUD_ACCESS_KEY_SECRET")
            ]);
            $config->endpoint = "alidns.cn-hangzhou.aliyuncs.com";
            $client = new Alidns($config);
            $describeSubDomainRecordsRequest = new DescribeSubDomainRecordsRequest([
                "subDomain" => Request::param("sub") . "." . $domain["dom"],
            ]);
            $runtime = new RuntimeOptions([]);
            try {
                // 复制代码运行请自行打印 API 的返回值
                $res = $client->describeSubDomainRecordsWithOptions($describeSubDomainRecordsRequest, $runtime);
                if ($res->statusCode != 200) {
                    return json(["code" => 300, "msg" => "上游服务器错误"]);
                }
                $res = $res->body;
            } catch (Exception $error) {
                return json(["code" => 300, "msg" => $error->getMessage()]);
            }
            if ($res->totalCount > 0) {
                return json(["code" => 300, "msg" => "子域名已存在"]);
            }
            $type = ["A", "NS", "MX", "TXT", "CNAME", "SRV", "AAAA", "REDIRECT_URL", "FORWARD_URL"];
            if (!in_array(Request::param("type"), $type)) {
                return json(["code" => 300, "msg" => "解析类型不合法"]);
            }
            if (!Request::param("value")) {
                return json(["code" => 300, "msg" => "解析值不能为空"]);
            }
            if ($domain["is_record"] == 1) {
                //已备案,是三级域名
                $arrs = explode(".", $domain["dom"]);
                $addDomainRecordRequest = new AddDomainRecordRequest([
                    "domainName" => $arrs[1] . "." . $arrs[2],
                    "RR" => Request::param("sub") . "." . $arrs[0],
                    "type" => Request::param("type"),
                    "value" => Request::param("value"),
                ]);
            } else {
                //非备案,是二级域名
                $addDomainRecordRequest = new AddDomainRecordRequest([
                    "domainName" => $domain["dom"],
                    "RR" => Request::param("sub"),
                    "type" => Request::param("type"),
                    "value" => Request::param("value"),
                ]);
            }
            $runtime = new RuntimeOptions([]);
            try {
                // 修改成功
                $res = $client->addDomainRecordWithOptions($addDomainRecordRequest, $runtime);
                if ($res->statusCode != 200) {
                    return json(["code" => 300, "msg" => "上游服务器错误"]);
                }
                $res = $res->body;
            } catch (Exception $error) {
                return json(["code" => 300, "msg" => $error->getMessage()]);
            }
            if ($domain["is_record"] == 1) {
                //已经备案
                Db::table("user")->where("id", $user["id"])->dec("record_num")->update();
            } else {
                //未备案
                Db::table("user")->where("id", $user["id"])->dec("domain_num")->update();
            }
            $data = [
                "sub" => Request::param("sub"),
                "dom_id" => Request::param("domain"),
                "type" => Request::param("type"),
                "RecordId" => $res->recordId,
                "user_id" => $user["id"],
                "value" => Request::param("value"),
                "create_time" => date("Y-m-d H:i:s", time())
            ];
            $body = $this->MailBody($user["usernick"], 2, Request::param("sub") . "." . $domain["dom"], Request::param("type"), Request::param("value"), "");
            $this->SendEmail($user["email"], $body, '你的dns解析改变,请查收');
            Db::table("records")->insert($data); //更新数据
            return json(["code" => 00, "msg" => "添加成功"]);

            return json(["code" => 0, "msg" => "添加成功"]);
        } else if (Request::param("act") == "del") {
            //删除
            $user = Db::table("user")->where("ukey", cookie("ukey"))->find();
            $records = Db::table("records")->where("id", Request::param("id"))->where("is_delect", 0)->find();
            if (!$records) {
                return json(["code" => 300, "msg" => "解析记录不存在"]);
            }
            $domain = Db::table("domain")->where("id", $records["dom_id"])->where("state", 0)->find();
            $config = new Config([
                'accessKeyId' => Env::get("ALIYUN.ALIBABA_CLOUD_ACCESS_KEY_ID"),
                'accessKeySecret' => Env::get("ALIYUN.ALIBABA_CLOUD_ACCESS_KEY_SECRET")
            ]);
            $config->endpoint = "alidns.cn-hangzhou.aliyuncs.com";
            $client = new Alidns($config);
            $deleteDomainRecordRequest = new DeleteDomainRecordRequest([
                "recordId" => $records["RecordId"],
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
            if ($domain["is_record"] == 1) {
                //已经备案
                Db::table("user")->where("id", $user["id"])->inc("record_num")->update();
            } else {
                //未备案
                Db::table("user")->where("id", $user["id"])->inc("domain_num")->update();
            }
            Db::table("records")->where("id", Request::param("id"))->update(["is_delect" => 1]);
            $body = $this->MailBody($user["usernick"], 1, $records["sub"] . "." . $domain["dom"], $records["type"], $records["value"], "");
            $this->SendEmail($user["email"], $body, '你的dns解析改变,请查收');
            return json(["code" => 0, "msg" => "删除成功"]);
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
        Db::table("user")->where("id", $sql["id"])->update(["update_time" => date("Y-m-d H:i:s", time())]);
        return true;
    }

    private function ReplyEmail($user, $msg, $from)
    {
        $body = "<div><h4>尊敬的管理员 :</h4>";
        $body = $body . "<p>用户 " . $user["usernick"] . " 回复 " . $from["title"] . ":</p>";
        $body = $body . "<p>" . $msg . "</p></div>";
        return $body;
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

    private function SendEmail($target, $body, $title)
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
            $mail->Subject = $title;
            $mail->Body    = $body;

            $mail->send();
            return true;
        } catch (Exception $e) {
            echo '邮件发送失败: ', $mail->ErrorInfo;
        }
    }
}

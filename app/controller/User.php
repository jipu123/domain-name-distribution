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
        View::assign("domain",Db::table("domain")->select()->each(function ($item, $key) {
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
                    "RR" => Request::param("sub").".".$arrs[0],
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
            Db::table("records")->insert($data); //更新数据
            return json(["code" => 0, "msg" => "添加成功"]);
        }else if(Request::param("act") == "del"){
            //删除
            $user = Db::table("user")->where("ukey", cookie("ukey"))->find();
            $records = Db::table("records")->where("id", Request::param("id"))->where("is_delect", 0)->find();
            if(!$records){
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
            if($domain["is_record"] == 1){
                //已经备案
                Db::table("user")->where("id", $user["id"])->inc("record_num")->update();
            }else{
                //未备案
                Db::table("user")->where("id", $user["id"])->inc("domain_num")->update();
            }
            Db::table("records")->where("id", Request::param("id"))->update(["is_delect" => 1]);
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
}

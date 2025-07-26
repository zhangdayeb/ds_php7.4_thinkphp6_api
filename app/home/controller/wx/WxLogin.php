<?php

namespace app\home\controller\wx;


use app\BaseController;
use app\common\model\HomeToken;
use app\common\model\UserModel;
use think\App;

class WxLogin extends BaseController
{
    public static $wxurl = '';
    public static $appid = ''; //AppID
    public static $url = '';
    public static $response_type = 'code';
    public static $scope = 'snsapi_userinfo'; //snsapi_base
    public static $secret = ''; //AppSecret
    public static $str = '#wechat_redirect';

    public function __construct(App $app)
    {
        self::$appid=config('ToConfig.wx.appid');
        self::$url=config('ToConfig.wx.url');
        self::$wxurl=config('ToConfig.wx.wxurl');
        self::$secret=config('ToConfig.wx.secret');
        parent::__construct($app);
    }

    //第一步前端实现  拿到code
    //第二步  通过code 获得access_token
    public function access_token()
    {
        $code = $this->request->param('code');

        if (empty($code)) return $this->failed('code不存在');
        $url = 'https://api.weixin.qq.com/sns/oauth2/access_token?appid=' . self::$appid . '&secret=' . self::$secret . '&code=' . $code . '&grant_type=authorization_code';
        $oauth2 = $this->get_json($url);
        if (isset($oauth2['errcode']))
            return $this->failed('授权失败', 0, $oauth2);
        $wxuser = $this->get_open($oauth2['access_token'], $oauth2['openid']);
        $find= $this->user_insert($wxuser);
        return $find;
    }

    //第三步 获得access_token换取个人信息
    protected function get_open($access_token, $openid)
    {
        $url = 'https://api.weixin.qq.com/sns/userinfo?access_token=' . $access_token . '&openid=' . $openid . '&lang=zh_CN';
        return $this->get_json($url);
    }

    //强制验签是否是微信请求
    public function wx_token()
    {
        $post = $this->request->param();
        echo $post['echostr'];
        die;
    }
    //app返回前端使用
    public function wx_appid()
    {
        $codes = $this->request->param('codes','');
        if (!empty($codes)) cache('codes'.$_SERVER['REMOTE_ADDR'],$codes,3600);
        return $this->success(['app'=>self::$appid,'url'=>self::$url]);
    }

    protected function get_json($url)
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, FALSE);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        $output = curl_exec($ch);
        curl_close($ch);
        return json_decode($output, true);
    }

    //个人信息写入数据库
    public function user_insert($wxuser)
    {
        $codes=cache('codes'.$_SERVER['REMOTE_ADDR']);
        $user = new UserModel();
        $find = $user->where('user_name', $wxuser['openid'])->find();
        if ($find) {
            $find = $find->toArray();
            if ($find['status'] != 1) return $this->failed('该用户被禁用');
        }
        $avatar = [
            'https://p3.toutiaoimg.com/tos-cn-i-qvj2lq49k0/1bd5a1d898c64f96b44d26860c434a95~tplv-tt-large.image',
            'https://p3.toutiaoimg.com/tos-cn-i-qvj2lq49k0/840a5b3bcc03471c9cdbfaf5eb482936~tplv-tt-large.image',
            'https://p3.toutiaoimg.com/tos-cn-i-qvj2lq49k0/1d57712b1552412dbac609d3fc2beb2e~tplv-tt-large.image',
            'https://p3.toutiaoimg.com/tos-cn-i-qvj2lq49k0/d26474e1d0a94317a7a7d34c175f06d1~tplv-tt-large.image',
        ];
        shuffle($avatar);

        //用户不存在时，写入数据库
        if (!$find) {
            $data = [
                'nickname' => $wxuser['nickname'],
                'user_name' => $wxuser['openid'],
                'pwd' => pwdEncryption(home_Initial_pwd()),
                'create_time' => date('Y-m-d H:i:s'),
                'invitation_code'=>generateCode(),
                'type'=>2,
                'withdraw_pwd'=>home_tx_pwd(),
                'avatar' => $avatar[0],
            ];
            //推广用户查询 //查询推广代理商
            //$post = $this->request->param();
            if (isset($codes) && !empty($codes)) {
                $agent= $user->where('invitation_code', $codes)->find();
                if ($agent){
                    $data['agent_id_1']=$agent->id;
                    $data['agent_id_2']=$agent->agent_id_1;
                    $data['agent_id_3']=$agent->agent_id_2;
                    $data['market_uid']=$agent->market_uid;
                }

            }
            //插入数据
            $find = $user->save($data);
            if (!$find) return $this->failed('该用户登陆失败');
            //写入成功 查询当前数据
            $find = $user->find($user->id)->toArray();
        }

        $token = home_api_token($find['id']);
        $find['token'] =$token;

        //查询是否存在这条token的用户
        $update = (new HomeToken())->where('user_id', $find['id'])->update(['token' => $token, 'create_time' => date('Y-m-d H:i:s')]);

        //数据不存在时插入
        if ($update == 0) {
            (new HomeToken)->insert(['token' => $token, 'user_id' => $find['id'], 'create_time' => date('Y-m-d H:i:s')]);
        }

        //登陆成功后存入 session
        session('home_user', $find);
        (new \app\common\service\LoginLog())->login(2);//登陆日志
        return $this->success($find);
    }
}
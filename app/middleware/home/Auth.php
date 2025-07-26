<?php
declare (strict_types=1);

namespace app\middleware\home;


use app\common\model\HomeToken;
use app\common\model\UserModel;
use app\common\traites\ApiResponseTrait;

class Auth
{
    use ApiResponseTrait;
    public function handle($request, \Closure $next)
    {
        //校验token
        $token = $request->header('x-csrf-token');
        if (empty($token)) return $this->failed('token不存在');
        //查询token
        $res = (new HomeToken())->where('token', $token)->find();
        if (empty($res)) return $this->failed('无效token');

        //校验是否过期的token
        $expiration_time = time() - strtotime($res['create_time']);
        if ($expiration_time >= env('token.home_token_time', 10)) return $this->failed('token过期');

        //校验成功处理逻辑
        //查询用户数据并存入session
        $res = (new UserModel())->with(['vip'])->find($res['user_id']);
        if (empty($res)) return $this->failed('无效token');
        //session 登陆写入日志
        //if(empty(session())) (new \app\common\service\LoginLog())->login();
        $res=$res->toArray();
        $res['token'] = $token;
        session('home_user',$res);
        // 添加中间件执行代码
        return $next($request);

    }

}

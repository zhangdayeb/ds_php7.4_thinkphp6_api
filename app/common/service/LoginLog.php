<?php


namespace app\common\service;


use app\common\model\AdminModel;
use app\common\model\LoginLog as LoginLogModel;

class LoginLog
{
    //登陆日志  普通用户 登录日志
    public function login($type = 1)
    {
        $log['unique'] = session('admin_user.id') ?: session('home_user.id');
        $log['login_type'] = $type;
        $log['login_time'] = date('Y-m-d H:i:s');
        $log['login_ip'] = $_SERVER['REMOTE_ADDR'];
        $log['login_equipment'] = isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : '';
        if (strlen($log['login_equipment']) > 200) $log['login_equipment'] = mb_substr($log['login_equipment'], 0, 200, 'utf-8');
        $loginLogModel = new LoginLogModel();
        $loginLogModel->save($log);

    }
}
<?php

namespace app\home\controller\pay;

use app\BaseController;

class TDEasy extends BaseController
{
    public static function  done(array $params)
    {
        $data  = [
            'pid'          => $params[ 'mid' ],  // 配置  商户ID
            'type'         => $params[ 'channel_tag' ], // 支付方式 目前只有   alipay 支付宝 wxpay 微信支付
            'out_trade_no' => $params[ 'pay_no' ], // 服务器异步通知地址
            'notify_url'   => (string) url('home/pay/asyncback3',[],false,$params['notifyUrl']),
            'name'         => $params[ 'describe_order' ], // 如超过127个字节会自动截取
            'money'        => $params[ 'pay_price' ], // 单位：元，最大2位小数
            'clientip'     => request ()->ip (), // 用户发起支付的IP地址
            'device'       => 'mobile',
        ];
        if (isset($params['return_url'])){
            // $data['return_url'] = urldecode($params['return_url']) . '&id=' . $params['order_id']; // 支付落地页
            $data['return_url'] = urldecode($params['return_url']); // 支付落地页
        }
        $data['sign'] = self::sign($data,$params['app_key']);
        $data['sign_type'] = 'MD5';// 默认为MD5
        return ['data'=>$data,'url'=>$params['gateway'].'/mapi.php'];
    }

    /**
     * 支付签名
     * @param array $data
     * @param string $key
     * @return string
     */
    public static function sign (array $data,string $key)
    {
        ksort($data);
        $signStr = http_build_query($data);
        $signStr = urldecode($signStr).$key;
        return md5($signStr);
    }
}
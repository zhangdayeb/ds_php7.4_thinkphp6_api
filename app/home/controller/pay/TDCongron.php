<?php

namespace app\home\controller\pay;

use app\BaseController;

class TDCongron extends BaseController
{
    public static function  done(array $params)
    {
        $data  = [
            'mchId'        => $params[ 'mid' ],  // 分配的商户号
            'mchOrderNo'   => $params[ 'pay_no' ], // 商户生成的订单号
            'amount'       => $params[ 'pay_price' ]*100, // 支付金额,单位分
            'notifyUrl'   => (string) url('home/pay/asyncback4',[],false,$params['notifyUrl']),
            'clientIp'     => request ()->ip (), // 用户发起支付的IP地址
            'productId'    => $params[ 'channel_tag' ], // 支付产品ID   8000=支付宝	  8001=微信
        ];
        if (isset($params['return_url'])){
            // $data['returnUrl'] = urldecode($params['return_url']) . '&id=' . $params['order_id']; // 支付落地页
            $data['returnUrl'] = urldecode($params['return_url']); // 支付落地页
        }
        $data['sign'] = self::sign($data,$params['app_key']);
        return ['data'=>$data,'url'=>$params['gateway'].'/api/pay/create_order'];
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
        $data['key'] = $key;
        $signStr = http_build_query($data);
        $signStr = urldecode($signStr);
        return strtoupper(md5($signStr));
    }
}
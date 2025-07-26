<?php

namespace app\home\controller\pay;

use app\BaseController;

class TDJinZi extends BaseController
{
    public static function  done(array $params)
    {
        $data  = [
            'mchId'    => $params[ 'mid' ],  // 平台分配商户号
            'mchOrderNo'     => $params[ 'pay_no' ], // 上送订单号唯一, 字符长度20
            'productId'    => $params['channel_tag'], // 参考后续说明
            'notifyUrl'   => str_replace('HTTPS','https',(string) url ( 'home/pay/asyncback8',[],false,$params[ 'notifyUrl' ] )),// 服务端返回地址.（POST 返回数据）
            'amount'      => $params[ 'pay_price' ] * 100, // 支付金额
        ];
        if (isset($params['return_url'])){
            // $data['returnUrl'] = urldecode($params['return_url']) . '&id=' . $params['order_id']; // 支付落地页
            $data['returnUrl'] = urldecode($params['return_url']); // 支付落地页
        }
        $data['sign'] = self::sign($data,$params['app_key']);
        return ['data'=>$data,'url'=>$params['gateway']];
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
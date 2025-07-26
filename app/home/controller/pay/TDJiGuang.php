<?php

namespace app\home\controller\pay;

use app\BaseController;

class TDJiGuang extends BaseController
{
    public static function  done(array $params)
    {
        $data  = [
            'mchKey'            => $params[ 'mid' ],  // 平台分配商户号
            'mchOrderNo'        => $params[ 'pay_no' ], // 上送订单号唯一, 字符长度20
            'timestamp'         => self::microsecond(), // 时间格式：2016-12-26 18:18:18
            'product'           => $params['channel_tag'], // 参考后续说明
            'notifyUrl'         => str_replace('HTTPS','https',(string) url ( 'home/pay/asyncbackjiguang',[],false,$params[ 'notifyUrl' ] )),// 服务端返回地址.（POST 返回数据）
            'amount'            => $params[ 'pay_price' ] * 100, // 支付金额
            'nonce'             => rand(),  
        ];
        if (isset($params['return_url'])){
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
    public static function sign (array $returnArray,string $md5key)
    {
        ksort($returnArray);
        reset($returnArray);
        $md5str = "";
        foreach ($returnArray as $key => $val) {
            $md5str = $md5str . $key . "=" . $val . "&";
        }
        $md5str = rtrim ( $md5str ,  "&" );
        $str = $md5str . $md5key;
        $sign = md5($str);
        return $sign;
    }

    //获取毫秒时间
    public static function microsecond()
    {
        $t = explode(" ", microtime());
        $microsecond = round(round($t[1] . substr($t[0], 2, 3)));
        return $microsecond;
    }
}
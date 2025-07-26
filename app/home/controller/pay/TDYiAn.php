<?php

namespace app\home\controller\pay;

use app\BaseController;

class TDYiAn extends BaseController
{
    public static function  done(array $params)
    {
        $data  = [
            'pay_memberid'    => $params[ 'mid' ],  // 平台分配商户号
            'pay_orderid'     => $params[ 'pay_no' ], // 上送订单号唯一, 字符长度20
            'pay_applydate'   => date ( 'Y-m-d H:i:s' ), // 时间格式：2016-12-26 18:18:18
            'pay_bankcode'    => $params['channel_tag'], // 参考后续说明
            'pay_notifyurl'   => str_replace('HTTPS','https',(string) url ( 'home/pay/asyncbackyian',[],false,$params[ 'notifyUrl' ] )),// 服务端返回地址.（POST 返回数据）
            'pay_callbackurl' => (string) url ( 'home/pay/asyncback5',[],false,$params[ 'notifyUrl' ] ), // 页面跳转返回地址（POST 返回数据）
            'pay_amount'      => $params[ 'pay_price' ], // 支付金额
        ];
        if (isset($params['return_url'])){
            // $data['pay_callbackurl'] = urldecode($params['return_url']) . '&id=' . $params['order_id']; // 支付落地页
            // $data['pay_callbackurl'] = urldecode('https://skin3.tzds992.com/code/a057280f/#/play?id=353'); // 支付落地页
            $data['pay_callbackurl'] = urldecode($params['return_url']); // 支付落地页
        }
        $data['pay_md5sign'] = self::sign($data,$params['app_key']);
        $data['pay_productname'] = '商品购买';
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
        $sign = strtoupper(md5($md5str . "key=" . $md5key));
        return $sign;
    }
}
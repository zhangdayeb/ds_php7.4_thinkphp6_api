<?php

namespace app\home\controller\pay;

use think\facade\Log;

use app\BaseController;
use app\common\model\Channel;
use app\common\model\OrderModel;
use app\common\model\OrderVideo;
use app\common\model\UserModel;
use app\common\model\VideoBuyUserVideo;

use app\home\controller\service\AgentService;
use app\home\controller\service\BranchService;



class Back extends BaseController
{
    /**
     * Summary of async_back_test
     * 仅供测试回调
     * @return mixed
     */
    public function async_back_test()
    {
        $payno = $this->request->param('payno');
        if (true === self::order_back($payno, '{"action":"test"}')) {
           return json(['code' => 200, 'msg' => 'success']);
        }
        return json(['code' => 400, 'msg' => 'fail']);
    }
    
    public function async_passage2 ()
    {
        
        $tongDaoName = '小语支付';
        // 返回数据 获取 日志准备 开始
        Log::write ($tongDaoName.'异步通知结果');
        $post = $this->request->post();
        $get = $this->request->get();
        $params = $this->request->param();
        Log::write ($post,$tongDaoName.'异步通知结果post');
        Log::write ($get,$tongDaoName.'异步通知结果get');
        Log::write ($params,$tongDaoName.'异步通知结果params');
        // 返回数据 获取 日志准备 结束

        $out_trade_no = $params['out_trade_no']??false;
        $sign = $params['sign']??'';
        $pid = $params['pid']??'';
        unset($params['sign']);
        unset($params['sign_type']);
        $info = Channel::where('mid',$pid)->field (['app_key','type'])->findOrEmpty ();
        if ($info->isEmpty ()){
            echo 'error';exit();
        }
        $app_key = $info['app_key'];
        $type = $info['type'];
        switch ($type) {
            case 2:
                if ($sign !=  TDGather::sign($params,$app_key)){
                    echo 'error';exit();
                }
                break;
            default:
                echo 'error';exit();
                break;
        }
        if (true === self::order_back ($out_trade_no,$params)) {
            echo 'success';exit();
        }
        echo 'error';exit();
    }


    public function async_passage3 ()
    {
        $tongDaoName = '巅峰支付';
        // 返回数据 获取 日志准备 开始
        Log::write ($tongDaoName.'异步通知结果');
        $post = $this->request->post();
        $get = $this->request->get();
        $params = $this->request->param();
        Log::write ($post,$tongDaoName.'异步通知结果post');
        Log::write ($get,$tongDaoName.'异步通知结果get');
        Log::write ($params,$tongDaoName.'异步通知结果params');
        // 返回数据 获取 日志准备 结束

        $out_trade_no = $params['out_trade_no']??false;
        $sign = $params['sign']??'';
        $pid = $params['pid']??'';
        unset($params['sign']);
        unset($params['sign_type']);
        $info = Channel::where('mid',$pid)->field (['app_key','type'])->findOrEmpty ();
        if ($info->isEmpty ()){
            echo 'error';exit();
        }
        $app_key = $info['app_key'];
        $type = $info['type'];
        switch ($type) {
            case 3:
                if ($sign !=  TDEasy::sign($params,$app_key)){
                    echo 'error';exit();
                }
                break;
            default:
                echo 'error';exit();
                break;
        }
        if (true === self::order_back ($out_trade_no,$params)) {
            echo 'success';exit();
        }
        echo 'error';exit();
    }


    public function async_passage4 ()
    {
        $tongDaoName = '苍龙支付';
        // 返回数据 获取 日志准备 开始
        Log::write ($tongDaoName.'异步通知结果');
        $post = $this->request->post();
        $get = $this->request->get();
        $params = $this->request->param();
        Log::write ($post,$tongDaoName.'异步通知结果post');
        Log::write ($get,$tongDaoName.'异步通知结果get');
        Log::write ($params,$tongDaoName.'异步通知结果params');
        // 返回数据 获取 日志准备 结束


        $mchOrderNo = $params['mchOrderNo']??'';
        $mchId = $params['mchId']??'';
        $productId = $params['productId']??'';
        $sign = $params['sign']??'';
        unset($params['sign']);
        $info = Channel::where(['mid'=>$mchId,'channel_tag'=>$productId])->field (['app_key','type'])->findOrEmpty ();
        if ($info->isEmpty ()){
            echo 'error';exit();
        }
        $app_key = $info['app_key'];
        $type = $info['type'];
        switch ($type) {
            case 4:
                if ($sign !=  TDCongron::sign($params,$app_key)){
                    echo 'error';exit();
                }
                break;
            default:
                echo 'error';exit();
                break;
        }
        if (true === self::order_back ($mchOrderNo,$params)) {
            echo 'success';exit();
        }
        echo 'error';exit();
    }

    public function async_passage5 ()
    {
        $tongDaoName = '山河支付';
        // 返回数据 获取 日志准备 开始
        Log::write ($tongDaoName.'异步通知结果');
        $post = $this->request->post();
        $get = $this->request->get();
        $params = $this->request->param();
        Log::write ($post,$tongDaoName.'异步通知结果post');
        Log::write ($get,$tongDaoName.'异步通知结果get');
        Log::write ($params,$tongDaoName.'异步通知结果params');
        // 返回数据 获取 日志准备 结束


        $orderid = $params['orderid']??'';
        $mchId = $params['memberid']??'';
        $sign = $params['sign']??'';
        $params = array_filter($params);// 默认行为，移除所有等同于FALSE的值
        unset($params['sign']);
        $app_key = Channel::where(['type'=>5,'mid'=>$mchId])->value('app_key');
        if (!$app_key){
            echo 'error';exit();
        }
        if ($sign !=  TDChannel5::sign($params,$app_key)){
            echo 'error';exit();
        }
        if (true === self::order_back ($orderid,$params)) {
            echo 'OK';exit();
        }
        echo 'error';exit();
    }


    public function async_passage8 ()
    {
        $tongDaoName = '金子支付';
        // 返回数据 获取 日志准备 开始
        Log::write ($tongDaoName.'异步通知结果');
        $post = $this->request->post();
        $get = $this->request->get();
        $params = $this->request->param();
        Log::write ($post,$tongDaoName.'异步通知结果post');
        Log::write ($get,$tongDaoName.'异步通知结果get');
        Log::write ($params,$tongDaoName.'异步通知结果params');
        // 返回数据 获取 日志准备 结束


        $orderid = $params['mchOrderNo']??'';
        $mchId = $params['mchId']??'';
        $sign = $params['sign']??'';
        $params = array_filter($params);// 默认行为，移除所有等同于FALSE的值
        unset($params['sign']);
        $app_key = Channel::where(['type'=>8,'mid'=>$mchId])->value('app_key');
        if (!$app_key){
            echo 'error';exit();
        }
        if ($sign !=  TDJinZi::sign($params,$app_key)){
            echo 'error';exit();
        }
        if (true === self::order_back ($orderid,$params)) {
            echo 'SUCCESS';exit();
        }
        echo 'error';exit();
    }


    public function asyncbackyian ()
    {
        $tongDaoName = '易安支付';
        $pay_channel_type = 'yian';

        // 返回数据 获取 日志准备 开始
        Log::write ($tongDaoName.'异步通知结果');
        $post = $this->request->post();
        $get = $this->request->get();
        $params = $this->request->param();
        Log::write ($post,$tongDaoName.'异步通知结果post');
        Log::write ($get,$tongDaoName.'异步通知结果get');
        Log::write ($params,$tongDaoName.'异步通知结果params');
        // 返回数据 获取 日志准备 结束

        
        $orderid = $params['orderid']??'';
        $mchId = $params['memberid']??'';
        $sign = $params['sign']??'';
        $params = array_filter($params);// 默认行为，移除所有等同于FALSE的值
        unset($params['sign']);
        $app_key = Channel::where(['type'=>$pay_channel_type,'mid'=>$mchId])->value('app_key');
        if (!$app_key){
            echo 'error';exit();
        }
        if ($sign !=  TDYiAn::sign($params,$app_key)){
            echo 'error';exit();
        }
        if (true === self::order_back ($orderid,$params)) {
            echo 'OK';exit();
        }
        echo 'error';exit();
    }



    public function asyncbackjiguang ()
    {
        $tongDaoName = '极光支付';
        $pay_channel_type = 'jiguang';

        // 返回数据 获取 日志准备 开始
        Log::write ($tongDaoName.'异步通知结果');
        $post = $this->request->post();
        $get = $this->request->get();
        $params = $this->request->param();
        Log::write ($post,$tongDaoName.'异步通知结果post');
        Log::write ($get,$tongDaoName.'异步通知结果get');
        Log::write ($params,$tongDaoName.'异步通知结果params');
        // 返回数据 获取 日志准备 结束

        
        $orderid = $params['mchOrderNo']??'';
        $mchId = $params['mchKey']??'';
        $sign = $params['sign']??'';
        $params = array_filter($params);// 默认行为，移除所有等同于FALSE的值
        unset($params['sign']);
        $app_key = Channel::where(['type'=>$pay_channel_type,'mid'=>$mchId])->value('app_key');
        if (!$app_key){
            echo 'error';exit();
        }
        if ($sign !=  TDJiGuang::sign($params,$app_key)){
            echo 'error';exit();
        }
        if (true === self::order_back ($orderid,$params)) {
            echo 'SUCCESS';exit();
        }
        echo 'error';exit();
    }














































    /**
     * Summary of order_back
     * @param mixed $mchOrderNo 订单编号
     * @param mixed $params 回调传回来的参数
     * @return bool
     */
    private function order_back ($mchOrderNo,$params)
    {
        // 远程回到日志
        Log::write ($mchOrderNo,'远程回调');
        Log::write ($params,'远程回调');

        // 订单为空
        $order_info = OrderModel::where('pay_no',$mchOrderNo)->findOrEmpty();
        if ($order_info->isEmpty ()){
            Log::emergency('订单号:'.$mchOrderNo . '：' . '购买成功，订单未找到,联系管理员');
            return false;
        }

        // 订单状态已经更新
        $order_info = $order_info->toArray ();
        if ($order_info['pay_status'] == 1){
            return true;
        }

        // 更新订单状态
        $status = OrderModel::withoutGlobalScope ([])->where('pay_no',$mchOrderNo)->save(['pay_status' => 1,'pay_remark'=>$params, 'pay_time' => date('Y-m-d H:i:s')]);
        if (!$status) {
            Log::emergency('订单号:'.$mchOrderNo . '：' . '购买成功，订单状态修改失败,联系管理员');
        }

        // 用户信息
        $orderUsser = UserModel::where('id',$order_info['uid'])->findOrEmpty();
        if (isset($order_info['package_type']) && !is_numeric ($order_info['package_type'])){
            // 只有购买套餐才会记录  | 例如 包天 套餐 price_day  | 但是如果购买 单独影片 此处存放的就是影片的ID
            $find = getPackageName($order_info['package_type']);
            //增加到 视频订单表
            $vip = $this->order_vip($order_info['id'], $find,$orderUsser);
        }else{
            //增加到用户单独购买视频表
            $vip = (new VideoBuyUserVideo())->insert([
                'video_id'   => $order_info['goods_id'],
                'uid'        => $order_info['uid'],
                'start_time' => date ( 'Y-m-d H:i:s' ),
                'end_time'   => date ('Y-m-d H:i:s',strtotime("+1 day")),
            ]);
        }

        // 写入到相关数据  
        if (!$vip) {
            Log::emergency('订单号:'.$mchOrderNo . '：' . '购买成功，视频订单表写入失败,联系管理员');
        }

        // 代理商分润
        $AgentService = new AgentService();
        $agent_money_log = $AgentService->branch ($orderUsser, ['price' => $order_info['pay_price'], 'order_id' => $order_info['id'],'goods_id'=>$order_info['package_type'],'vid'=>$order_info['goods_id'],'pay_no'=>$order_info['pay_no']]);
        Log::write ($agent_money_log,'代理商分润');

        //现在是不管是充值还是购买都能得到分销奖励  | 暂时 放弃
        // $BranchService = new BranchService();
        // $money_log = $BranchService->branch($orderUsser, ['price' => $order_info['pay_price'], 'order_id' => $order_info['id']]);
        // Log::write ($money_log,'普通代理分销');
        return true;
    }
    private function order_vip($order_id, $find,$user)
    {
        //写入vip订单信息
        $status = (new OrderVideo())->save([
            'uid'            => $user['id'],
            'market_uid'     => $user['market_uid'],
            'status'         => 1,
            'order_id'       => $order_id,
            'package_type'   => $find[ 'id' ],
            'vip_level'      => $find[ 'goods_id' ],
            'vip_start_time' => date ( 'Y-m-d H:i:s' ),
            'vip_end_time'   => date('Y-m-d H:i:s', time() + ($find['validity_time'])),//当前时间+可观看秒
        ]);
        if ($status) return true;
        return false;
    }
}
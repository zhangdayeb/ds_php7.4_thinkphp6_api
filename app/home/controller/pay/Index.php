<?php

namespace app\home\controller\pay;

use app\common\model\Channel;
use app\common\model\OrderModel;
use app\home\controller\Base;
use think\db\exception\DataNotFoundException;
use think\db\exception\DbException;
use think\db\exception\ModelNotFoundException;
use think\facade\Request;
use think\response\Json;
use think\facade\Log;
class Index extends Base
{
    /**
     * 用户选择支付方式
     * @return Json
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function choice()
    {
        $price = $this->request->post('price', 0);  // 获取支付价格
        $agentid = session ('home_user.agentid');   // 获取当前用户代理ID
        // 确定开启的支付通道
        $channelsToOpenIds = $this->checkOpenPayChannel();
        // 搜索条件
        $where = [
            ['status', '=', 1],
            ['max_amount', '>=', $price],
            ['mini_amount', '<=', $price],
        ];
        // 获取当前渠道今日已经支付的次数
        // withoutGlobalScope 关闭全局动态范围
        // 此处的pay_type 其实是对应 pay_channel 里面的pay id
        $payment_info = OrderModel::withoutGlobalScope ([])
            ->whereDay('create_time', date ('Y-m-d')) // 当日订单
            ->where ('pay_status',1) // 已经支付
            ->whereNotNull ('pay_type') // 支付方式不为空
            ->group('pay_type') // 合并支付方式
            ->column ('count(1) as num','pay_type'); // 获取支付次数 为了防止超过今日支付次数的
            
        // 获取当前代理可用支付通道 
        // if ($agentid>0){
        //     $channel_id = AdminModel::withoutGlobalScope ([])->where(['id'=>$agentid])->value('channel_id');
        //     if(!empty($channel_id)){
        //         $channel_id =  explode (',',$channel_id);
        //         $where[] = ['id','in',$channel_id];
        //     }
        // }
        // dd($channelsToOpenIds);
        // 获取 符合当前代理 范围内的 支付通道
        $list = Channel::withoutGlobalScope ([])
            ->where ($where)
            ->whereIn('id', $channelsToOpenIds)
            ->field (['id','channel_name','pay_channel','type','max_number', 'pay_user_tag', 'show_sort','day_start_time', 'day_end_time', 'ontime_is_open_or_close'])
            ->order('show_sort asc')
            ->select()
            ->toArray();
        // dd($list);
        // 循环两次
        $pay_count = [];
        foreach ($list as  $v){
            if(isset($payment_info[$v['id']])){
                if (isset($pay_count[$v['type']])){
                    $pay_count[$v['type']] +=$payment_info[$v['id']];
                }else{
                    $pay_count[$v['type']] = $payment_info[$v['id']];
                }
            }
        }
        // 删除超过的 支付标准的通道
        foreach ($list as $k=>$v){
            if(isset($pay_count[$v['type']])){
                $num = $pay_count[$v['type']];
                if ($v['max_number']<=$num){
                    unset($list[$k]);
                }
            }
        }
        return show($list);
    }

    protected function checkOpenPayChannel()
    {
        $nowTime = new \DateTime();
        $channelsToClose = [];
        $channelsToOpen = [];
        // 获取所有 存活的通道
        $list = Channel::withoutGlobalScope([])->where('status', 1)->select()->toArray();
        foreach ($list as $v) {
            $startTime = new \DateTime($v['day_start_time']);
            $endTime = new \DateTime($v['day_end_time']);

            // 选定范围内开启
            if ($v['ontime_is_open_or_close'] == 'open') {
                if ( $startTime <= $nowTime && $nowTime <= $endTime) {
                    // 在设定范围内
                    $channelsToOpen[] = $v['id'];
                } else {
                    // 不在设定范围内
                    $channelsToClose[] = $v['id'];
                }
            }

            // 选定范围内关闭
            if ($v['ontime_is_open_or_close'] == 'close') {
                // 选定时间范围内 关闭
                if ( $startTime <= $nowTime && $nowTime <= $endTime) {
                    // 在设定范围内
                    $channelsToClose[] = $v['id'];
                } else {
                    // 不在设定范围内
                    $channelsToOpen[] = $v['id'];
                }
            }
   
        }
        // 关闭非开放的渠道
        // if (!empty($channelsToClose)) {
        //     Channel::whereIn('id', $channelsToClose)->update([
        //         'ontime_is_open_or_close' => 'close',
        //         'update_time' => time()
        //     ]);
        // }
        // if (!empty($channelsToOpen)) {
        //     Channel::whereIn('id', $channelsToClose)->update([
        //         'ontime_is_open_or_close' => 'open',
        //         'update_time' => time()
        //     ]);
        // }

        // return true;
        return $channelsToOpen;
    }

    /**
     * 提交支付
     */
    public static function submit($channel_id,array $order_info)
    {
        Log::write ('提交支付');
        $chanel_info = Channel::withoutGlobalScope ([])->findOrEmpty ($channel_id);
        if($chanel_info->isEmpty ()){
            return ['code' => 0, 'msg' => '未找到相应支付方式'];
        }
        $done_data = $chanel_info->toArray ();
        if (!isset($order_info['pay_no'])){
            return ['code' => 0, 'msg' => '请提供订单号'];
        }
        if (!isset($order_info['describe_order'])){
            return ['code' => 0, 'msg' => '请提供商品名称'];
        }
        if (!isset($order_info['pay_price']) && !is_numeric ($order_info['pay_price']) && $order_info['pay_price']>0){
            return ['code' => 0, 'msg' => '支付金额不对'];
        }
        $done_data['order_id'] = $order_info['order_id'] ?? '';
        $done_data['return_url'] = $order_info['return_url'] ?? (Request::header('origin', ''));;
        $done_data['pay_no'] = $order_info['pay_no'];
        $done_data['describe_order'] = $order_info['describe_order'];
        $done_data['pay_price'] = $order_info['pay_price'];
        $type = $done_data['type']??0;
        $isHttps = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on';
        if ($isHttps) {
            $url = 'https://'.$_SERVER ['HTTP_HOST'];
        } else {
            $url = 'http://'.$_SERVER ['HTTP_HOST'];
        }
        $done_data['notifyUrl'] = $url;
        Log::write (json_encode($done_data),"提交支付前准备的数据");
        switch ($type){
            // 对应 不同支付通道 具体的支付情况 
            case 2: // 小语
                $tongDaoName = '小语支付';
                $data = TDGather::done ($done_data);
                Log::write (json_encode($data),"打包后的数据===".$tongDaoName);
                $result = self::postData ($data['url'],$data['data']);
                Log::write (($result),"支付端返回的数据===".$tongDaoName);
                $result = json_decode ($result,true);
                $code = $result['code']??0;
                if ($code != 1){
                    return ['code' => 0, 'msg' => $result['msg']??'支付错误'];
                }
                break;
            case 3: //巅峰
                $tongDaoName = '巅峰支付';
                $data = TDEasy::done ($done_data);
                Log::write (json_encode($data),"打包后的数据===".$tongDaoName);
                $result = self::postData ($data['url'],$data['data']);
                Log::write (($result),"支付端返回的数据===".$tongDaoName);
                $result = json_decode ($result,true);
                $code = $result['code']??0;
                if ($code != 1){
                    return ['code' => 0, 'msg' => $result['msg']??'支付错误'];
                }
                break;
            case 4: // 苍龙
                $tongDaoName = '苍龙支付';
                $data = TDCongron::done ($done_data);
                Log::write (json_encode($data),"打包后的数据===".$tongDaoName);
                $result = self::postData  ($data['url'],$data['data']);
                Log::write (($result),"支付端返回的数据===".$tongDaoName);
                $result = json_decode ($result,true);
                if (isset($result['payParams'])){
                    $result['payurl'] = $result['payParams']['payUrl'];
                }
                $code = $result['retCode']??'FAIL';
                if ($code != 'SUCCESS'){
                    return ['code' => 0, 'msg' => $result['errDes']??'支付错误'];
                }
                $result['code'] = 1;
                break;
            case 5: // 山河
                $tongDaoName = '山河支付';
                $data = TDChannel5::done ($done_data);
                Log::write (json_encode($data),"打包后的数据===".$tongDaoName);
                $result = self::postData  ($data['url'], ($data['data']));
                Log::write (($result),"支付端返回的数据===".$tongDaoName);
                $result = json_decode ($result,true);
                $status = $result['status']??0;
                if ($status != 1){
                    return ['code' => 0, 'msg' => $result['msg']??'支付错误'];
                }
                if (isset($result['pay_url'])){
                    $result['payurl'] = $result['pay_url'];
                }
                $result['code'] = 1;
                break;
            case 8: // 金子支付
                $tongDaoName = '金子支付';
                $data = TDJinZi::done ($done_data);
                Log::write (json_encode($data),"打包后的数据===".$tongDaoName);
                $result = self::postData  ($data['url'], ($data['data']));
                Log::write (($result),"支付端返回的数据===".$tongDaoName);
                $result = json_decode ($result,true);
                $status = $result['retCode']??0;
                if ($status != 'SUCCESS'){
                    return ['code' => 0, 'msg' => $result['errDes']??'支付错误'];
                }
                if (isset($result['payParams']['payUrl'])){
                    $result['payurl'] = $result['payParams']['payUrl'];
                }
                $result['code'] = 1;
                break;
            case 'yian': // 易安支付
                $tongDaoName = '易安支付';
                $data = TDYiAn::done ($done_data);
                Log::write (json_encode($data),"打包后的数据===".$tongDaoName);
                $result = self::postData  ($data['url'], ($data['data']));
                Log::write (($result),"支付端返回的数据===".$tongDaoName);
                $result = json_decode ($result,true);
                $status = $result['status']??0;
                if ($status != 'success'){
                    return ['code' => 0, 'msg' => $result['msg']??'支付错误'];
                }
                if (isset($result['payurl'])){
                    $result['payurl'] = $result['payurl']??'';
                }
                $result['code'] = 1;
                break;
            case 'jiguang': // 极光支付
                $tongDaoName = '极光支付';
                $data = TDJiGuang::done ($done_data);
                Log::write (json_encode($data),"打包后的数据===".$tongDaoName);
                $result = self::http_post_json_jiguang($data['url'], json_encode($data['data']));
                Log::write (($result),"支付端返回的数据===".$tongDaoName);
                $result = json_decode ($result,true);
                $status = $result['data']['payStatus']??0;
                if ($status != 'PROCESSING'){
                    return ['code' => 0, 'msg' => $result['msg']??'支付错误'];
                }
                if (isset($result['data']['url']['payUrl'])){
                    $result['payurl'] = $result['data']['url']['payUrl']??'';
                }
                $result['code'] = 1;
                break;
            
            // 默认分类执行完成 
            default:
                return ['code' => 0, 'msg' => '支付没有选择类型'];
                break;
        }
        return $result;
    }




    // 极光专用发送函数
    private static function http_post_json_jiguang($url, $jsonStr)
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonStr);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
                'Content-Type: application/json; charset=utf-8',
                'Content-Length: ' . strlen($jsonStr)
            )
        );
        $response = curl_exec($ch);
        curl_close($ch);
        return $response;
    }














    /**
     * 支付提交
     * @param $c_url
     * @param $data
     * @param array $header
     * @return bool|string
     */
    private static function postData($c_url, $data,array $header = []) {
        $curl = curl_init (); // 启动一个CURL会话
        curl_setopt ( $curl, CURLOPT_URL, $c_url ); // 要访问的地址
        curl_setopt ( $curl, CURLOPT_SSL_VERIFYPEER, 0 ); // 对认证证书来源的检查
        curl_setopt ( $curl, CURLOPT_SSL_VERIFYHOST, 2 ); // 从证书中检查SSL加密算法是否存在
        curl_setopt ( $curl, CURLOPT_USERAGENT, $_SERVER ['HTTP_USER_AGENT'] ); // 模拟用户使用的浏览器
        curl_setopt ( $curl, CURLOPT_FOLLOWLOCATION, 1 ); // 使用自动跳转
        curl_setopt ( $curl, CURLOPT_AUTOREFERER, 1 ); // 自动设置Referer
        curl_setopt ( $curl, CURLOPT_POST, 1 ); // 发送一个常规的Post请求
        curl_setopt ( $curl, CURLOPT_POSTFIELDS, $data ); // Post提交的数据包
        curl_setopt ( $curl, CURLOPT_HTTPHEADER, $header);
        curl_setopt ( $curl, CURLOPT_TIMEOUT, 15 ); // 设置超时限制防止死循环
        curl_setopt ( $curl, CURLOPT_HEADER, 0 ); // 显示返回的Header区域内容
        curl_setopt ( $curl, CURLOPT_RETURNTRANSFER, 1 ); // 获取的信息以文件流的形式返回
        $tmpInfo = curl_exec ( $curl ); // 执行操作
        // $info = curl_getinfo($curl);
        // Log::write (json_encode($info),"提交执行的数据格式");
        if (curl_errno ( $curl )) {
            echo 'Errno' . curl_error ( $curl ); // 捕抓异常
        }
        curl_close ( $curl ); // 关闭CURL会话
        return $tmpInfo; // 返回数据
    }


// 类结束了
}
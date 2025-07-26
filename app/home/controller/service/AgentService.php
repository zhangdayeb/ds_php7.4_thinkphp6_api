<?php

namespace app\home\controller\service;

use app\common\model\AdminModel;
use app\common\model\MoneyLog;
use app\common\model\OrderModel;
use think\db\exception\DataNotFoundException;
use think\db\exception\DbException;
use think\db\exception\ModelNotFoundException;
use think\Exception;
use think\facade\Cache;
use think\facade\Db;
use think\response\Json;
use think\facade\Log;

class AgentService
{
    public $allAgents = [];
    public function __construct()
    {
    }
    /**
     * 代理商分润
     * @param $user  订单表内的 生成订单的用户
     * @param array $goods 返回商品信息 ['price' => $order_info['pay_price'], 'order_id' => $order_info['id'],'goods_id'=>$order_info['package_type'],'vid'=>$order_info['goods_id']])
     * @param $status 订单状态
     * @return array
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function branch($user, $goods, $status = 501)
    {
        Log::write ('开始计算代理利润');
        if (!$user['agentid'] || $user['agentid'] <= 0) {
            Log::write ('没有上级代理商，直接返回');
            return ['code' => 3, 'msg' => '不参与分润'];
        }
        // 扣单开始
        Log::write ('评估是否进行暗扣');
        $bool = $this->user_count($user, $goods);
        if ($bool){
            Log::write ('很不幸，此单被暗扣了，设置订单状态为504');
            return ['code' => 2, 'msg' => '扣单成功'];
        }else{
            Log::write ('没有暗扣，流程继续');
        }
        // 扣单结束

        // 获取全部代理
        $pay_money = $goods['price']; // 订单支付价格(金额)
        $agentUserAllList = AdminModel::withoutGlobalScope([])  // 清除其他预设条件
            ->where('status', 1)    // 正常用户
            ->where('role', 2)  //代理用户
            ->field(['id', 'pid', 'profit_rate', 'money', 'total_money', 'income_today', 'user_name', 'order_today_pay', 'order_today_nopay'])
            ->select()->toArray();

        // 重新梳理一下数据 结构  变成 ID 为key 值的形式
        $newAgentList = [];
        foreach ($agentUserAllList as $item){
            $newAgentList[$item['id']] = $item;
        }
        // 首先添加自己进入 当前用户的 父级代理 
        $newAgentList[$user['agentid']]['temp_rate_this_order'] = $newAgentList[$user['agentid']]['profit_rate'];
        $newAgentList[$user['agentid']]['get_money_this_order'] = round($newAgentList[$user['agentid']]['temp_rate_this_order']* $pay_money / 100 , 2); // 当前订单 当前父代理 分润金额
        $this->allAgents[] = $newAgentList[$user['agentid']];
        Log::write ('当前计划分润人员：1');
        Log::write ($this->allAgents);
        // 查找父级进入
        self::getRateAndMoney($newAgentList, $user['agentid'],floatval($pay_money));  // 查账父级分润比例
        $AllParentsNotSelf = $this->allAgents;
        Log::write ('当前计划分润人员：2');
        Log::write ($this->allAgents);

        // 遍历所有需要分润的父级代理 
        $MoneyLogData = [];
        $AgentUpdateData = [];
        foreach ($AllParentsNotSelf as $key => $singleAgentInfo) {
            // 准备所有 父级代理信息 更新 
            $tempAgentData = [];
            $tempAgentData['id'] = $singleAgentInfo['id'];
            $tempAgentData['money'] = $singleAgentInfo['money'] + $singleAgentInfo['get_money_this_order']; // 用户余额
            $tempAgentData['total_money'] = $singleAgentInfo['total_money'] + $singleAgentInfo['get_money_this_order']; // 用户累计余额
            $tempAgentData['income_today'] = $singleAgentInfo['income_today'] + $singleAgentInfo['get_money_this_order']; // 用户今日收入
            $tempAgentData['order_today_pay'] = $singleAgentInfo['order_today_pay'] + 1; // 今日支付单
            $tempAgentData['order_today_nopay'] = $this->refreshTodayNotPayNum($singleAgentInfo['id']); // 今日未支付单
            $AgentUpdateData[] = $tempAgentData;

            // 分润日志记录
            $MoneyLogData[] = [
                'money' => $singleAgentInfo['get_money_this_order'], // //获得金额
                'money_before' => $singleAgentInfo['money'], //变化前金额
                'money_end' => $singleAgentInfo['money'] + $singleAgentInfo['get_money_this_order'],//代理商总余额
                'uid' => $user['id'],//下单用户
                'type' => 1, // 1收入 2支出 3后台修改金额 4提现退款
                'tip_number' => $goods['pay_no'], // 打赏单号 / 支付订单号
                'video' => (int)($goods['vid'] ?? (is_numeric($goods['goods_id']) ? $goods['goods_id'] : 0)), // 购买视频的iD传递
                'price' => $goods['price'],
                'status' => $status, // 此刻为 501 充值
                'income_type' => $user['agentid'] == $singleAgentInfo['id'] ? 1 : 2, //  收入类型  第一级是打赏  其他都是返佣
                'order_type' => is_numeric($goods['goods_id']) ? $goods['goods_id'] : getPackageName($goods['goods_id'])['goods_id'],
                'agent_uid' => $singleAgentInfo['id'],
                'source_id' => $goods['order_id'], //订单ID
                'mark' => $user['nickname'] . '(ID：' . ($user['id']) . ')购买价值' . $goods['price'] . '元商品;'. '代理商商：' . $singleAgentInfo['user_name'] . '(ID：' . $singleAgentInfo['id'] . ')获得奖励' . $singleAgentInfo['get_money_this_order'] . '元',
            ];
        }
       // 启动事务
        Db::startTrans();
        try {
            (new AdminModel)->saveAll($AgentUpdateData);    // 更新代理钱数
            (new MoneyLog())->insertAll($MoneyLogData);     // 更新统计日志
            (new OrderModel())->where('id', $goods['order_id'])->save(['agent_remark' => json_encode($AllParentsNotSelf)]); // 更新订单备注

            Db::commit();
        } catch (\Exception $e) {
            // 回滚事务
            Db::rollback();
            throw new Exception($e->getMessage(). ' ' . $e->getFile() . ' ' . $e->getLine());
        }
        
        return ['code' => 1, 'msg' => '分润工作完成'];
    }

    /**
     * 寻找父级节点所有分润比例 | 真的是不知道那个 大神写的代码................好开心
     * Summary of getRateAndMoney
     * @param mixed $agentUserAllList
     * @param mixed $search_agent_id
     * @param mixed $pay_money
     * @return array
     */
    public function getRateAndMoney($agentUserAllList, $search_agent_id, $pay_money)
    {
        // 如果当前代理 有上级 并且这个 上级代理依然存在 修复那种删除 上级代理的bug
        if($agentUserAllList[$search_agent_id]['pid']>0 && isset($agentUserAllList[$agentUserAllList[$search_agent_id]['pid']])){
            $pid = $agentUserAllList[$search_agent_id]['pid'];
            // 首先添加自己进入
            $agentUserAllList[$pid]['temp_rate_this_order'] = $agentUserAllList[$pid]['profit_rate'] - $agentUserAllList[$search_agent_id]['profit_rate'];
            $agentUserAllList[$pid]['get_money_this_order'] = round($agentUserAllList[$pid]['temp_rate_this_order'] * $pay_money / 100 , 2); // 当前订单 当前父代理 分润金额
            $this->allAgents[] = $agentUserAllList[$pid];

            // 递归调用
            if($agentUserAllList[$pid]['pid']>0){
                self::getRateAndMoney($agentUserAllList,$pid,$pay_money);
            }
        }
    }

    /**
     * 暗扣的逻辑：1 针对代理商进行的统计 2 有启扣量，超过才执行， 3 扣单比例为百分之多少
     * @param $user
     * @param $goods
     * @return bool
     */
    public function user_count($user, $goods)
    {
        // 代理商ID大于0 才会有代理商计算规则
        if ($user['agentid'] <= 0) return false;

        // 获取当前代理信息
        $agent_uid = $user['agentid'];
        $agentInfo = (new AdminModel())->find($agent_uid);
        $ankou_start = $agentInfo['ankou_start'];
        $ankou_rate = $agentInfo['kou_rate'];

        // 获取当前代理的总订单量
        $countAllOrder = Db::name('common_order')
            ->where(['agent_uid'=>$agent_uid,'pay_status'=>1])
            ->count();
        // 如果还没有打到扣除的起步量
        if($ankou_start > $countAllOrder){
            return false;
        }

        // 设置当前订单 是否扣除        
        if (rand(1, 100) <= $ankou_rate) {
            //修改忽略订单状态 
            (new OrderModel())->where('id', $goods['order_id'])->save(['status' => 504, 'pay_time' => date('Y-m-d H:i:s')]);
            return true;
        }
        return false;
    }

    public function refreshTodayNotPayNum($agentUserId)
    {
        $today = date('Y-m-d');
        return OrderModel::where('agent_uid', $agentUserId)
            ->where('status', '<>', 504)
            ->whereDay('create_time', $today)
            ->where('pay_status', 0)
            ->count();
    }
}
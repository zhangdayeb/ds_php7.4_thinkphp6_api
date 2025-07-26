<?php
declare (strict_types=1);

namespace app\command;

use app\common\model\AccessLog;
use app\common\model\AdminModel;
use app\common\model\MoneyLog;
use app\common\model\LoginLog as LoginLogModel;
use app\common\model\OrderModel;
use think\console\Command;
use think\console\Input;
use think\console\Output;
use think\facade\Cache;

class StatisticsAgentUserCommand extends Command
{
    protected function configure()
    {
        // 指令配置
        $this->setName('statisticsagentuser')
            ->setDescription('初始化代理商统计数据');
    }

    /**
     * @param Input $input
     * @param Output $output
     * @return void
     * 总收入 total_money
     * 昨日收入 income_yesterday
     * 昨日总单 order_yesterday_pay + order_yesterday_nopay
     * 昨日未支付单 order_yesterday_nopay
     * 昨日支付单 order_yesterday_pay
     * 昨日 IP 量 access_yesterday
     * 昨日打开数 open_yesterday
     *
     * 总收入 total_money
     * 今日收入 income_today
     * 今日总单 order_today_pay + order_today_nopay
     * 今日未支付单 order_today_nopay
     * 今日支付单 order_today_pay
     * 今日 IP 量 access_today
     * 今日打开 open_today
     */
    protected function execute(Input $input, Output $output)
    {
        echo '[*] 清理信息' . PHP_EOL;
        // 增加一个清理的工作
        $clean = [
            'income_today'      => 0, // 今日收入
            'order_today_pay'   => 0, // 今日支付订单
            'order_today_nopay' => 0, // 今日未支付订单
            'access_today'      => 0, // 今日IP数
            'access_yesterday'  => 0, // 昨日IP数
            'open_today'        => 0, // 今日打开数
            'open_yesterday'    => 0, // 昨日打开数
            ];
        (new AdminModel)->where('1 = 1')->update ($clean);

        echo '[*] 初始化代理商统计数据开始' . PHP_EOL;
        echo '======================================================' . PHP_EOL;
        $adminModel = new AdminModel();
        $fields = 'id,user_name,total_money,income_today,access_today,order_today_pay,order_today_nopay,open_today,income_yesterday,
        access_yesterday,order_yesterday_pay,order_yesterday_nopay,open_yesterday';
        $agentUsers = $adminModel->where('role', 2)->field($fields)->select();
        $redis = Cache::store('redis');
        foreach ($agentUsers as $agentUser) {
            echo $agentUser['user_name'] . ' start' . PHP_EOL;
            $yesterdayStatistics = $this->getYesterdayStatistics($agentUser['id']);
            $todayStatistics = $this->getTodayStatistics($agentUser['id']);
            $update = [
                'income_yesterday' => $yesterdayStatistics['income_yesterday'],
                'order_yesterday_pay' => $yesterdayStatistics['order_yesterday_pay'],
                'order_yesterday_nopay' => $yesterdayStatistics['order_yesterday_nopay'],
                'access_yesterday' => $yesterdayStatistics['access_yesterday'],
                'open_yesterday' => $yesterdayStatistics['open_yesterday'],
                
                'income_today' => $todayStatistics['income_today'],
                'order_today_pay' => $todayStatistics['order_today_pay'],
                'order_today_nopay' => $todayStatistics['order_today_nopay'],
                // 'order_today_total' =>$todayStatistics['order_today_total'],
                'access_today' => $todayStatistics['access_today'],
                'open_today' => $todayStatistics['open_today'],
            ];

            $adminModel->where('id', $agentUser['id'])->update($update);
            echo $agentUser['user_name'] . ' end' . PHP_EOL;
            echo '-----------------------------------------------------------------' . PHP_EOL;
        }
        echo '[!] 初始化代理商统计数据完成' . PHP_EOL;
        echo '======================================================' . PHP_EOL;
        // 指令输出
        $output->writeln(date('Y-m-d H:i:s') . ' 初始化完成');
    }

    protected function getYesterdayStatistics($agentUserId)
    {
        $yesterday = date('Y-m-d', strtotime('-1 day'));
        $agentUserResult = [];
        
        // $r = OrderModel::where('agent_uid', $agentUserId)
        //     ->where('status', '<>', 504)
        //     ->whereDay('create_time', $yesterday)
        //     ->where('pay_status', 1)
        //     ->fetchSql(true)
        //     ->sum('pay_price');
        // echo $r;
        // 单量
        $agentUserResult['order_total'] = OrderModel::where('agent_uid', $agentUserId)
            ->where('status', '<>', 504)
            ->whereDay('create_time', $yesterday)
            ->count();
        echo '昨日总订单:'. $agentUserResult['order_total'] . PHP_EOL;
        
        // 支付单
        $agentUserResult['order_yesterday_pay'] = OrderModel::where('agent_uid', $agentUserId)
            ->where('status', '<>', 504)
            ->whereDay('create_time', $yesterday)
            ->where('pay_status', 1)
            ->count();
        echo '昨日支付订单:'. $agentUserResult['order_yesterday_pay'] . PHP_EOL;
        
        // 未支付单
        $agentUserResult['order_yesterday_nopay'] = OrderModel::where('agent_uid', $agentUserId)
            ->where('status', '<>', 504)
            ->whereDay('create_time', $yesterday)
            ->where('pay_status', 0)
            ->count();
        echo '昨日未支付订单:'. $agentUserResult['order_yesterday_nopay'] . PHP_EOL;
        
        // 昨日收入 | 统计方法错误 
        // $agentUserResult['income_yesterday'] = OrderModel::where('agent_uid', $agentUserId)
        //     ->where('status', '<>', 504)
        //     ->whereDay('create_time', $yesterday)
        //     ->where('pay_status', 1)
        //     ->sum('pay_price');
        // echo '昨日收入:'. $agentUserResult['income_yesterday'] . PHP_EOL;
        $agentUserResult['income_yesterday'] = (new MoneyLog())->where('agent_uid', $agentUserId)
            ->where('status', 501)
            ->whereDay('create_time', $yesterday)
            ->sum('money');
        echo '昨日收入:'. $agentUserResult['income_yesterday'] . PHP_EOL;
        
        // 昨日 IP 数量
        $agentUserResult['access_yesterday'] = LoginLogModel::where('login_type', 2)
            ->where('unique', $agentUserId)
            ->whereDay('login_time', $yesterday)
            ->count('DISTINCT login_ip');
        echo '昨日IP数:'. $agentUserResult['access_yesterday'] . PHP_EOL;
        
        // 昨日打开数
        $agentUserResult['open_yesterday'] = AccessLog::where('agent_uid', $agentUserId)
            ->whereDay('create_time', $yesterday)
            ->count();
        echo '昨日打开数:'. $agentUserResult['open_yesterday'] . PHP_EOL;

        return $agentUserResult;
    }

    protected function getTodayStatistics($agentUserId)
    {
        $today = date('Y-m-d');
        $agentUserResult = [];
        // 单量
        $agentUserResult['order_today_total'] = OrderModel::where('agent_uid', $agentUserId)
            ->where('status', '<>', 504)
            ->whereDay('create_time', $today)
            ->count();
        echo '今日总订单:'. $agentUserResult['order_today_total'] . PHP_EOL;
        
        // 支付单
        $agentUserResult['order_today_pay'] = OrderModel::where('agent_uid', $agentUserId)
            ->where('status', '<>', 504)
            ->whereDay('create_time', $today)
            ->where('pay_status', 1)
            ->count();
        echo '今日支付订单:'. $agentUserResult['order_today_pay'] . PHP_EOL;
        
        // 未支付单
        $agentUserResult['order_today_nopay'] = OrderModel::where('agent_uid', $agentUserId)
            ->where('status', '<>', 504)
            ->whereDay('create_time', $today)
            ->where('pay_status', 0)
            ->count();
        echo '今日未支付订单:'. $agentUserResult['order_today_nopay'] . PHP_EOL;
        
        // 今日收入
        // $agentUserResult['income_today'] = OrderModel::where('agent_uid', $agentUserId)
        //     ->where('status', '<>', 504)
        //     ->whereDay('create_time', $today)
        //     ->where('pay_status', 1)
        //     ->sum('pay_price');
        // echo '今日总收入:'. $agentUserResult['income_today'] . PHP_EOL;
        $agentUserResult['income_today'] = (new MoneyLog())->where('agent_uid', $agentUserId)
            ->where('status', 501)
            ->whereDay('create_time', $today)
            ->sum('money');
        echo '昨日收入:'. $agentUserResult['income_today'] . PHP_EOL;
        
        // 今日 IP 数量
        $agentUserResult['access_today'] = LoginLogModel::where('login_type', 2)
            ->where('unique', $agentUserId)
            ->whereDay('login_time', $today)
            ->count('DISTINCT login_ip');
        echo '今日IP数:'. $agentUserResult['access_today'] . PHP_EOL;
        
        // 今日打开数
        $agentUserResult['open_today'] = AccessLog::where('agent_uid', $agentUserId)
            ->whereDay('create_time', $today)
            ->count();
        echo '今日打开数:'. $agentUserResult['open_today'] . PHP_EOL;
        

        return $agentUserResult;
    }

}

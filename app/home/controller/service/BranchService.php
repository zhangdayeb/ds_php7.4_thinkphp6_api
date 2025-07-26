<?php

namespace app\home\controller\service;


use app\common\model\MoneyLog;
use app\common\model\OrderModel;
use app\common\model\UserModel;
use  \think\facade\Db;

class BranchService
{

    public $model;

    /**
     * @param $user /用户信息
     * @param $goods /购买金额
     * $goods['price'] 购买金额。‘order_id’ 订单ID
     * @param  $status /状态 401 分销奖励 403充值分销 404忽略订单
     */
    public function branch($user,array $goods,$status=401)
    {
        //当前用户是会员，不是代理， 当前会员的上一级超过 * 单时 每多少单扣一单
        #扣单开始
        $bool = $this->user_count($user,$goods);
        if ($bool) return ['code'=>2,'msg'=>'扣单成功'];
        #扣单结束

        $model = new UserModel();
        //计算拿出来分销的金额,20%拿出来分享
        //$price = $goods['price'] * 0.2;
        //全部拿出来分配
        $price = $goods['price'];
        //当前用户三级代理
        $agent[0] = $user['agent_id_1'];
        $agent[1] = $user['agent_id_2'];
        $agent[2] = $user['agent_id_3'];
        $agent = array_filter($agent);
        if (empty($agent)) return ['code'=>1,'msg'=>'该用户没有上级'];
        //获取当前用户的上级
        //查询三级代理每个用户的分销比例
        $res = $model->field('nickname,id,type,agent_rate,agent_id_1,agent_id_2,agent_id_3,money_total_agent,market_uid')
            ->whereIn('id', $agent)
            ->select()
            ->toArray();
        //按照分销比例分配用户获得金额
        $user_price = [];//用户ID id  代理商总余额 money_total_agent
        //时间create_time  类型1 type  详细类型 301 status 服务商消费提成  money_before money_end money uid market_uid mark
        $user_log = [];
        $total_price=0;
        foreach ($res as $key => $value) {
            if ($value['type'] != 1) continue;//不是代理直接不给
            $user_price[$key]['id'] = $value['id'];
            //本金加上获得金额
            $user_price[$key]['money_total_agent'] = $value['money_total_agent'] + $value['agent_rate'] * $price * 0.01;;//代理商总余额
            //写入日志信息
            $user_log[$key]['money'] = $value['agent_rate'] * $price * 0.01;//获得金额
            $user_log[$key]['money_before'] = $value['money_total_agent'];//变化前金额
            $user_log[$key]['money_end']= $value['money_total_agent'] + $value['agent_rate'] * $price * 0.01;;//代理商总余额
            $user_log[$key]['uid'] =$value['id'];
            $user_log[$key]['type'] =1;
            $user_log[$key]['status'] =$status;
            $user_log[$key]['market_uid'] =$value['market_uid'];
            $user_log[$key]['source_id'] =$goods['order_id']; //订单ID
            $user_log[$key]['mark'] =$user['nickname'].'(ID：'.$user['id'].')购买价值'.$goods['price'].'元商品;'
                .'服务商：'.$value['nickname'].'(ID：'.$value['id'].')获得奖励'.$value['agent_rate'] * $price * 0.01.'元';
            $total_price +=$user_log[$key]['money'];//所有分出去的金额。获得金额
        }

        //分配金额超过 消费金额
        if ($total_price > $price) return ['code'=>1,'msg'=>'分销比例配置错误'];

        //写入数据到数据库
       $money = new MoneyLog();

        $save = false;
        // 启动事务
       Db::startTrans();
        try {
            $model->saveAll($user_price);
            //批量新增 资金日志
            $money->saveAll($user_log);
            // 提交事务
            Db::commit();
            $save=true;
        } catch (\Exception $e) {
            // 回滚事务
            Db::rollback();
        }

        if ($save) return ['code'=>1,'msg'=>'分配金额成功'];
        return ['code'=>0,'msg'=>'分配金额失败'];
    }

    public function user_count($user,$goods)
    {
        //当前用户必须是会员才会产生 扣除分销订单
        if ($user['type'] != 2) return false;
        //是会员时 统计上一级分销的单子数量,上一级ID 必须大于0
        $count = Db::name('common_pay_recharge')//由 order 修改为 recharge
            ->whereTime('create_time', 'today')
            ->where('uid', 'IN', function ($query) use ($user) {
                //查询上一级 分销人分销的所有会员
                $query->name('common_user')
                    ->where('agent_id_1|agent_id_2|agent_id_3', $user['agent_id_1'])
                    ->where('agent_id_1', '>', 0)
                    ->field('id');
            })->count();

        //忽略订单
        if (orderIgnore($count)){
            //修改忽略订单状态
           (new OrderModel())->where('id', $goods['order_id'])->save(['status' => 404, 'pay_time' => date('Y-m-d H:i:s')]);
            return true;
        };
        return false;
    }
}
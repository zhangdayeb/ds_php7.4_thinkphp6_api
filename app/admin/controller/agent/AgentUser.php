<?php

namespace app\admin\controller\agent;

use app\admin\controller\Base;
use app\common\model\AdminModel;
use think\facade\Cache;

class AgentUser extends Base
{
    public function initialize()
    {
        parent::initialize();
    }
    public function subordinateAgent()
    {
        $agentName = $this->request->post('agent_name');
        $invitationCode = $this->request->post('invitation_code');
        $page = $this->request->post('page/d', 1);
        $limit = $this->request->post('limit/d', 10);
        $res = [
            'total' => 0,
            'per_page' => $limit,
            'current_page' => $page,
            'last_page' => 0,
            'data' => []
        ];

        $map = [];
        if ($agentName) {
            $map[] = ['user_name', 'like', '%' . $agentName . '%'];
        }
        if ($invitationCode) {
            $map[] = ['invitation_code', '=', $invitationCode];
        }
        $applyServiceCharge = get_config('apply_service_charge');
        $applyServiceChargeRate = 1;
        if ($applyServiceCharge) {
            $applyServiceChargeRate = round($applyServiceCharge->value / 100, 2);

        }
        $fields = 'id,user_name,invitation_code,income_today,income_yesterday,pid,
        (income_today * '.$applyServiceChargeRate.') as charge_today,
        (income_yesterday * '.$applyServiceChargeRate.') as charge_yesterday,profit_rate,withdrawal_rate';
        $agentUsers = AdminModel::where($map)->field($fields)->select();

        $agentUsers = $agentUsers->toArray();
        $pid = session('admin_user.id');
        $children = $this->getChildren($agentUsers, $pid);
        $totalCount = count($children);
        $lastPage = ceil($totalCount / $limit);
        $dataLimit = array_slice($children, ($page - 1) * $limit, $limit);
        $res['total'] = $totalCount;
        $res['last_page'] = $lastPage;
        $res['data'] = $dataLimit;

        if ($children) {
            return $this->success(['apply_charge' => $applyServiceChargeRate, 'data' => $res]);
        } else {
            return $this->success(['apply_charge' => 0, 'data' => []]);
        }
    }

    public function changeProfitRate()
    {
        $data = $this->request->post();
        $id = $data['id'];
        $profitRate = $data['profit_rate'];
        // 当前用户的抽成比例
        $sessionUserProfitRate = session('admin_user.profit_rate');
        // 代理商可以设置的最大抽成比例
        $configProfitRate = getSystemConfig('profit_rate');
        $rateToInt = 100 - $sessionUserProfitRate - $configProfitRate;
        if ($profitRate > $rateToInt) {
            return $this->failed('抽成比例不能超过' . $rateToInt . '%');
        }
        $res = AdminModel::where('id', $id)->update(['profit_rate' => $profitRate]);
        if ($res) {
            return $this->success('修改成功');
        } else {
            return $this->failed('修改失败');
        }
    }


    /**
     * 获取统计数据，与登录返回值一致
     * @return mixed
     */
    public function getStatisticsAgent()
    {
        $agent = session('admin_user');
        $res = $agent->getData();
        $token = $res['token'];
        return $this->success(['token' => $token, 'user' => $res]);
    }

    protected function getChildren($data, $pid = 0) {
        // 存储结果的数组
        $result = [];

        // 遍历数据集
        foreach ($data as $item) {
            // 如果当前项的父ID等于给定的PID，则是目标节点的直接子节点
            if ($item['pid'] == $pid) {

                // 递归调用，获取当前项的所有子节点，并添加到结果数组
                $item['children'] = $this->getChildren($data, $item['id']);
                // 将当前项添加到结果数组
                $result[] = $item;
            }
        }

        return $result;
    }

    public function getAgentUsers()
    {
        $keywords = $this->request->post('keywords', '');
        $where['role'] = 2;
        $where['status'] = 1;
        if ($keywords) {
            $where['user_name'] = ['like', '%' . $keywords . '%'];
        }
        $list = AdminModel::where($where)->field('id,user_name')->select();
        return $this->success($list);
    }
}
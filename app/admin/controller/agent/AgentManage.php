<?php

namespace app\admin\controller\agent;

use app\admin\controller\Base;
use app\common\model\AdminModel as models;
use app\common\model\AdminRole;
use app\common\model\Channel;
use app\common\model\SpreadAgent;
use app\common\service\DwzkrService;
use app\common\traites\PublicCrudTrait;
use app\validate\AgentManage as validates;
use think\exception\ValidateException;

class AgentManage extends Base
{
    protected $model;
    use PublicCrudTrait;

    /**
     *配置控制器
     */
    public function initialize()
    {
        $this->model = new models();
        parent::initialize();
    }
    public function index()
    {
        $page = $this->request->post('page', 1);
        $limit = $this->request->post('limit', 10);
        //查询搜索条件
        $post = array_filter($this->request->post());
        $map = [];
        isset($post['user_name']) && $map[] = ['a.user_name', 'like', '%' . $post['user_name'] . '%'];
        $map[] = ['a.role', '=', 2];
        $list = $this->model->agent_manage_page_list($map, $limit, $page);
        return $this->success($list);
    }

    public function agent_list()
    {
        $map = [];
        isset($post['user_name']) && $map[] = ['user_name', 'like', '%' . $post['user_name'] . '%'];
        $map[] = ['role', '=', 2];
        $lists = $this->model->where($map)->field('id,user_name')->select();
        return $this->success($lists);
    }


    public function add()
    {
        //过滤数据
        $postField = 'user_name,pwd,role,kou_start,withdraw_pwd,pid,kou_rate,profit_rate,channel_id';
        $post = $this->request->only(explode(',', $postField), 'post', null);
        //验证数据
        try {
            validate(validates::class)->scene('add')->check($post);
        } catch (ValidateException $e) {
            // 验证失败 输出错误信息
            return $this->failed($e->getError());
        }
        $checkProfitRate = $this->profitRateSetting($post['pid'], $post['profit_rate']);
        if ($checkProfitRate['bool'] === false) {
            return $this->failed($checkProfitRate['msg']);
        }
        //加密密码
        $post['pwd'] = (isset($post['pwd']) && $post['pwd'] != '') ? pwdEncryption($post['pwd']) : pwdEncryption(admin_Initial_pwd());

        $post['create_time'] = date('Y-m-d H:i:s');
        $post['invitation_code'] = substr(uniqid(),-8);
        $post['google_code'] =generateCode(32,40); // 谷歌验证器
        !isset($post['market_level']) && $post['market_level'] = 0;
        $save = $this->model->save($post);
        $id = $this->model->id;
        if ($save) {
            // 为当前代理商创建推广链接
            (new DwzkrService())->add_agent_create($id);
            return $this->success(['save' => $save]);
        }
        return $this->failed('新增失败');
    }

    public function edit()
    {
        //过滤数据
        $postField = 'user_name,money,profit_rate,role,kou_start,pwd,channel_id,status,id,withdraw_pwd,pid,kou_rate,duan_url';
        $post = $this->request->only(explode(',', $postField), 'post', null);
        //验证数据
        try {
            validate(validates::class)->scene('edit')->check($post);
        } catch (ValidateException $e) {
            // 验证失败 输出错误信息
            return $this->failed($e->getError());
        }
        $checkProfitRate = $this->profitRateSetting($post['pid'], $post['profit_rate']);
        if ($checkProfitRate['bool'] === false) {
            return $this->failed($checkProfitRate['msg']);
        }
        //加密密码
        if (isset($post['pwd']) && $post['pwd'] != '') {
            $post['pwd'] = pwdEncryption($post['pwd']);
        } else {
            unset($post['pwd']);
        }

        if (isset($post['withdraw_pwd']) && $post['withdraw_pwd'] == '') {
            unset($post['withdraw_pwd']);
        }
        $id = $post['id'];
        unset($post['id']);
        $save = $this->model->where('id', $id)->update($post);
        if ($save) {
            // 为当前代理商创更新广链接
            return $this->success(['save' => $save]);
        }
        return $this->failed('修改失败');
    }

    public function del()
    {
        //过滤数据
        $postField = 'id';
        $post = $this->request->only(explode(',', $postField), 'post', null);
        //验证数据
        try {
            validate(validates::class)->scene('detail')->check($post);
        } catch (ValidateException $e) {
            // 验证失败 输出错误信息
            return $this->failed($e->getError());
        }
        $del = $this->model->where('id', $post['id'])->update(['status' => -1]);
        if ($del) {
            (new DwzkrService())->delete_agent_user($post['id']);
            return $this->success(['del' => $del]);
        }
        return $this->failed('删除失败');
    }

    public function roleGroup()
    {
        $list = AdminRole::where('status', 1)->field('id,name')->select();
        return $this->success($list);
    }

    public function channel()
    {
        $list = Channel::where('status', 1)->field('id,channel_name,pay_channel')->select();
        return $this->success($list);
    }

    public function channelChange()
    {
        //过滤数据
        $postField = 'id,channel_id';
        $post = $this->request->only(explode(',', $postField), 'post', null);
        //验证数据
        try {
            validate(validates::class)->scene('chanel')->check($post);
        } catch (ValidateException $e) {
            // 验证失败 输出错误信息
            return $this->failed($e->getError());
        }
        $update = $this->model->where('id', $post['id'])->update(['channel_id' => $post['channel_id']]);
        if ($update) {
            return $this->success(['update' => $update]);
        }
        return $this->failed('修改失败');
    }

    public function profitRateSetting($pid, $profitRate)
    {
        if ($pid == 0) {
            // 当前用户的抽成比例
            $parentProfitRate = 0;
            // 代理商可以设置的最大抽成比例
            $configProfitRate = getSystemConfig('profit_rate');
            $rateToInt = 100 - $parentProfitRate - $configProfitRate;
            if ($profitRate > $rateToInt) {
                return ['bool' => false, 'maxRate' => $rateToInt, 'msg' => '系统设置'. $configProfitRate . '%,最大不能超过' . $rateToInt . '%'];
            } else {
                return ['bool' => true, 'maxRate' => $rateToInt, 'msg' => '设置成功'];
            }
        } else {
            $parentRate = $this->model->where('id', $pid)->value('profit_rate');
            if ($profitRate > $parentRate) {
                return ['bool' => false, 'maxRate' => $parentRate, 'msg' => '上级设置'. $parentRate . '%,不能超过上级设置的' . $parentRate . '%'];
            } else {
                return ['bool' => true, 'maxRate' => $parentRate, 'msg' => '设置成功'];
            }
        }
    }
}
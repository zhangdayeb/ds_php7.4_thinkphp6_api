<?php

namespace app\admin\controller\fangfeng;

use app\admin\controller\Base;
use app\common\model\AdminModel;
use app\common\model\SpreadAgent;
use think\response\Json;
use think\facade\Db;

class FFYiYai extends Base
{
    protected $key = 'kxP04QoUsLjcl4AabzjhfRa2qHXRGXdI';
    /**
     * 后台控制器
     */
    public function initialize()
    {
        parent::initialize();
    }

    protected function getUserUrl($baseUrlArray,$invitation_code){
        $url =  $baseUrlArray['http'].GetRandStr(6).'.'.$baseUrlArray['domain'].'/?code='.$invitation_code;
        $type = 118;
        $fangfengKeys = getSystemConfig('wechat_fangfeng');
        $fangfengKeys = str_replace('，',',',$fangfengKeys);
        $fangfengKeys = str_replace(' ',',',$fangfengKeys);
        $fangfengKeys = trim($fangfengKeys);
        $type_arr = explode(',',$fangfengKeys);
        $l = count($type_arr);
        $index = rand(0,($l-1));
        $type = $type_arr[$index];
        $t_url = 'https://cdn.yiyai.top/api/?method=add&type=0&key=kxP04QoUsLjcl4AabzjhfRa2qHXRGXdI&domain='.$type.'&vip=1&url='.urlencode($url);
        $r =  curl_get($t_url);
        $r_obj = json_decode($r);
        return $r_obj->short;
    }
    /**
     * 更新所有的 
     * @return mixed
     */
    public function newUrl_all()
    {

        $urls = [];
        $urls['wx'] = [
            'http'=>'https://',
            'domain'=>'jhhqds.com'
        ];
        $map = [];
        $map['status'] = 1;
        $map['role'] = 2;
        $agents = (new AdminModel())->where($map)->select();
        if(!$agents){
           return $this->failed('没有代理');
        }
        // 清空所有
        Db::name('common_spread_agent')->delete(true);
        foreach($agents as $key => $agentInfo){
            $invitation_code = $agentInfo['invitation_code'];
            $data_spread = [];
            $data_spread['agent_uid'] = $agentInfo['id'];
            $data_spread['invitation_code'] = $invitation_code;

            $data_spread['url_key_web'] = 'n';
            $data_spread['url_key_wx'] = 'n';
            $data_spread['url_key_qq'] = 'n';
            $data_spread['url_key_dy'] = 'n';
            $data_spread['url_key_wb'] = 'n';

            $data_spread['sms_url'] = $this->getUserUrl($urls['wx'],$invitation_code);
            $data_spread['custom_url'] = $this->getUserUrl($urls['wx'],$invitation_code);
            $data_spread['du_url'] = $this->getUserUrl($urls['wx'],$invitation_code);
            $data_spread['du1_url'] = $this->getUserUrl($urls['wx'],$invitation_code);
            $data_spread['dy_url'] = $this->getUserUrl($urls['wx'],$invitation_code);
            $data_spread['dy1_url'] = $this->getUserUrl($urls['wx'],$invitation_code);
            $data_spread['wb_url'] = $this->getUserUrl($urls['wx'],$invitation_code);
            $data_spread['wx_url'] = $this->getUserUrl($urls['wx'],$invitation_code);
            $data_spread['wx1_url'] = $this->getUserUrl($urls['wx'],$invitation_code);
            $data_spread['wx2_url'] = $this->getUserUrl($urls['wx'],$invitation_code);
            $data_spread['wx3_url'] = $this->getUserUrl($urls['wx'],$invitation_code);
            $data_spread['qq_url'] = $this->getUserUrl($urls['wx'],$invitation_code);

            $data_spread['status'] = '1';
            $data_spread['update_time'] = time();
            $data_spread['create_time'] = time();

            (new SpreadAgent())->insert($data_spread);
        }
        $this->success('更新完成');
    }

        /**
     * 更新所有的 
     * @return mixed
     */
    public function newUrl_wx()
    {

        $urls = [];
        $urls['wx'] = [
            'http'=>'https://',
            'domain'=>'jhhqds.com'
        ];
        $map = [];
        $map['status'] = 1;
        $map['role'] = 2;
        $agents = (new AdminModel())->where($map)->select();
        if(!$agents){
           return $this->failed('没有代理');
        }
        foreach($agents as $key => $agentInfo){
            // 搜索条件
            $map = [];
            $map['agent_uid'] = $agentInfo['id'];

            $invitation_code = $agentInfo['invitation_code'];
            $data_spread = [];
            $url_back = $this->getUserUrl($urls['wx'],$invitation_code);
            $data_spread['wx_url'] = $url_back;
            $data_spread['wx1_url'] = $url_back;
            $data_spread['wx2_url'] = $url_back;
            $data_spread['wx3_url'] = $url_back;
            $data_spread['create_time'] = time();

            (new SpreadAgent())->where($map)->update($data_spread);
        }
        $this->success('更新完成');
    }

    public function agentIndex()
    {
        $this->success();
    }

    /**
     * 新增
     * @return mixed|null
     */
    public function add(): Json
    {
        return $this->failed('禁止新增');
    }

    /**
     * 编辑
     * @return mixed|null
     */
    public function edit()
    {
        return $this->failed('禁止编辑');
    }

    /**
     * 禁止详情
     * @return void
     */
    public function detail()
    {
        return $this->failed('禁止详情');
    }

    /**
     * 禁止删除
     * @return void
     */
    public function del()
    {
        return $this->failed('禁止删除');
    }

    // 类结束了
}
<?php

namespace app\common\service;

use app\common\model\AdminModel;
use app\common\model\SpreadAgent;
use app\common\model\SpreadModel;

/**
 * 这个是防封生成的api  把我们的地址 变成他们这个防封的地址 可以批量生成
 * GET|POST https://dwz.kr/api/v1/create?long_url=您要缩短的网址&api_token=b3df48a17d843fddb35389661089be5b57e2a27f6ad78d7f3badc1e34fa49043
 */
class DwzkrService
{
    protected $api_token;
    protected $create_url;
    protected $update_url;
    public function __construct()
    {
        $this->api_token = 'b3df48a17d843fddb35389661089be5b57e2a27f6ad78d7f3badc1e34fa49043';
        $this->create_url = 'https://dwz.kr/api/v1/create';
        $this->update_url = 'https://dwz.kr/api/v1/update';
    }

    public function get_dwzkr_urls($long_url)
    {
        $request_url = $this->create_url . '?long_url=' . $long_url . '&api_token=' . $this->api_token;
        $response = curl_post($request_url, []);
        if (json_decode($response) === null) {
            throw new \Exception('dwz.kr接口返回错误');
        }
        return json_decode($response, true);
    }

    public function update_dwzkr_urls($url_key, $long_url)
    {
        $request_url = $this->update_url. '/'.$url_key. '?long_url='. $long_url. '&api_token='. $this->api_token;
        $response = curl_post($request_url, []);
        if (json_decode($response) === null) {
            throw new \Exception('dwz.kr接口返回错误');
        }
        return json_decode($response, true);
    }

    /**
     * 添加推广链接  | 此功能基本废弃 不会添加新的 
     * @param $spread_id
     * @param $title
     * @param $long_url
     * @return true
     * @throws \Exception
     */
    public function add_spread($spread_id, $title, $long_url)
    {
        // 所有代理商都要生成关于这个推广链接的防封链接
        $agentUsers = $this->get_agent_users();
        $insert = [];
        foreach ($agentUsers as $agentUser) {
            $new_url = $long_url;
            $new_url = (strpos($new_url, '?') ? $new_url : $new_url . '?') . 'code=' . $agentUser['invitation_code'];
            $dwzUrls = $this->get_dwzkr_urls($new_url);
            $insert[] = [
                'spread_id' => $spread_id,
                'agent_uid' => $agentUser['id'],
                'invitation_code' => $agentUser['invitation_code'],
                'url_key' => $dwzUrls['url_key'] ?? '',
                'title' => $title,
                'tag' => 'dwz.kr',
                'origin_url' => $new_url,
                'sms_url' => $dwzUrls['sms_url'] ?? '',
                'custom_url' => $dwzUrls['custom_url'] ?? '',
                'du_url' => $dwzUrls['du_url'] ?? '',
                'du1_url' => $dwzUrls['du1_url'] ?? '',
                'dy_url' => $dwzUrls['dy_url'] ?? '',
                'dy1_url' => $dwzUrls['dy1_url'] ?? '',
                'wb_url' => $dwzUrls['wb_url'] ?? '',
                'wx_url' => $dwzUrls['wx_url'] ?? '',
                'wx1_url' => $dwzUrls['wx1_url'] ?? '',
                'wx2_url' => $dwzUrls['wx2_url'] ?? '',
                'wx3_url' => $dwzUrls['wx3_url'] ?? '',
                'qq_url' => $dwzUrls['qq_url'] ?? '',
            ];
        }
        $spreadAgentModel = new SpreadAgent();
        if (!empty($insert)) {
            $spreadAgentModel->saveAll($insert);
        }
        return true;
    }

    /**
     * 编辑推广链接
     * @param $spreadId
     * @param $title
     * @param $long_url
     * @return true
     * @throws \Exception
     */
    public function edit_spread($spreadId, $title, $long_url)
    {

        // 只修改目标推广链接
        $agentUsers = $this->get_agent_users(); // 获取全部代理用户

        // 遍历所有用户
        foreach ($agentUsers as $agentUser) {
            // 遍历循环 开始
            $agentUserUrlInfo = (new SpreadAgent())->where('agent_uid','=',$agentUser['id'])->find();
            // 只是更新 网页部分
            if($spreadId == 1 && $title == '网页'){
                $url_key = $agentUserUrlInfo->url_key_web;
            }

            // 只是更新 网页部分
            if($spreadId == 2 && $title == '微信'){
                $url_key = $agentUserUrlInfo->url_key_wx;
            }

            // 只是更新 网页部分
            if($spreadId == 3 && $title == 'QQ'){
                $url_key = $agentUserUrlInfo->url_key_qq;

            }

            // 只是更新 网页部分
            if($spreadId == 4 && $title == '抖音'){
                $url_key = $agentUserUrlInfo->url_key_dy;
            }

            // 只是更新 网页部分
            if($spreadId == 5 && $title == '微博'){
                $url_key = $agentUserUrlInfo->url_key_wb;
            }
            $send_url = $long_url.'/code/'.$agentUser['invitation_code'];
            $this->update_dwzkr_urls($url_key, $send_url);
            // 遍历循环结束
        }
        return true;
    }

    /**
     * 删除推广链接
     * @param $spreadId
     * @return bool
     */
    public function delete_spread($spreadId)
    {
        $spreadAgentModel = new SpreadAgent();
        return $spreadAgentModel->where('spread_id', $spreadId)->delete();
    }

    /**
     * 删除代理商
     * @param $agentUid
     * @return bool
     */
    public function delete_agent_user($agentUid)
    {
        $spreadAgentModel = new SpreadAgent();
        return $spreadAgentModel->where('agent_uid', $agentUid)->delete();
    }

    /**
     * 全部重新生成
     * @return true
     * @throws \Exception
     */
    public function re_create_all()
    {
        $spreadAgentModel = new SpreadAgent();
        // 清理之前的所有数据
        $spreadAgentModel->where('1=1')->delete();
        $agentUsers = $this->get_agent_users();
        foreach ($agentUsers as $agentUser) {
            addOneAgentLink($agentUser['id'],$agentUser['invitation_code']);
        }
        return true;
    }

    public function re_create_all_bak()
    {
        $spreadAgentModel = new SpreadAgent();
        // 清理之前的所有数据
        $spreadAgentModel->where('1=1')->delete();
        $agentUsers = $this->get_agent_users();
        $spreadList = $this->get_spread_list();
        $insert = [];
        foreach ($agentUsers as $agentUser) {
            foreach ($spreadList as $spread) {
                $long_url = (strpos($spread['url'], '?') ? $spread['url'] : $spread['url'] . '?') . 'code=' . $agentUser['invitation_code'];
                $dwzUrls = $this->get_dwzkr_urls($long_url);
                $insert[] = [
                    'spread_id' => $spread['id'],
                    'agent_uid' => $agentUser['id'],
                    'invitation_code' => $agentUser['invitation_code'],
                    'url_key' => $dwzUrls['url_key'] ?? '',
                    'title' => $spread['title'],
                    'tag' => 'dwz.kr',
                    'origin_url' => $long_url,
                    'sms_url' => $dwzUrls['sms_url'] ?? '',
                    'custom_url' => $dwzUrls['custom_url'] ?? '',
                    'du_url' => $dwzUrls['du_url'] ?? '',
                    'du1_url' => $dwzUrls['du1_url'] ?? '',
                    'dy_url' => $dwzUrls['dy_url'] ?? '',
                    'dy1_url' => $dwzUrls['dy1_url'] ?? '',
                    'wb_url' => $dwzUrls['wb_url'] ?? '',
                    'wx_url' => $dwzUrls['wx_url'] ?? '',
                    'wx1_url' => $dwzUrls['wx1_url'] ?? '',
                    'wx2_url' => $dwzUrls['wx2_url'] ?? '',
                    'wx3_url' => $dwzUrls['wx3_url'] ?? '',
                    'qq_url' => $dwzUrls['qq_url'] ?? '',
                ];
            }
        }
        if (!empty($insert)) {
            $spreadAgentModel->saveAll($insert);
        }
        return true;
    }

    /**
     * 全部重新修改落地链接
     * @return true
     * @throws \Exception
     */
    public function update_long_url_all()
    {
        $agentUsers = $this->get_agent_users();
        $spreadList = $this->get_spread_list();
        $spreadAgentModel = new SpreadAgent();
        foreach ($agentUsers as $agentUser) {
            foreach ($spreadList as $spread) {
                $long_url = (strpos($spread['url'], '?') ? $spread['url'] : $spread['url'] . '?') . 'code=' . $agentUser['invitation_code'];
                $spreadAgentInfo = $spreadAgentModel->where('spread_id', $spread['id'])->where('agent_uid', $agentUser['id'])->find();
                $url_key = $spreadAgentInfo['url_key'];
                $this->update_dwzkr_urls($url_key, $long_url);
                $update = [
                    'title' => $spread['title'],
                    'origin_url' => $long_url,
                ];
                $spreadAgentModel->where('id', $spreadAgentInfo['id'])->update($update);
            }
        }
        return true;
    }

    public function add_agent_create($agent_uid)
    {
        $spreadList = $this->get_spread_list();
        $agentUserInfo = AdminModel::where('id', $agent_uid)->field('id,user_name,invitation_code')->find();
        $insert = [];
        $spreadAgentModel = new SpreadAgent();
        foreach ($spreadList as $spread) {
            $long_url = (strpos($spread['url'], '?') ? $spread['url'] : $spread['url'] . '?') . 'code=' . $agentUserInfo['invitation_code'];
            $dwzUrls = $this->get_dwzkr_urls($long_url);
            $insert[] = [
                'spread_id' => $spread['id'],
                'agent_uid' => $agent_uid,
                'invitation_code' => $agentUserInfo['invitation_code'],
                'url_key' => $dwzUrls['url_key'] ?? '',
                'title' => $spread['title'],
                'tag' => 'dwz.kr',
                'origin_url' => $long_url,
                'sms_url' => $dwzUrls['sms_url'] ?? '',
                'custom_url' => $dwzUrls['custom_url'] ?? '',
                'du_url' => $dwzUrls['du_url'] ?? '',
                'du1_url' => $dwzUrls['du1_url'] ?? '',
                'dy_url' => $dwzUrls['dy_url'] ?? '',
                'dy1_url' => $dwzUrls['dy1_url'] ?? '',
                'wb_url' => $dwzUrls['wb_url'] ?? '',
                'fb_url' => $dwzUrls['fb_url'] ?? '',
                'wx_url' => $dwzUrls['wx_url'] ?? '',
                'wx1_url' => $dwzUrls['wx1_url'] ?? '',
                'wx2_url' => $dwzUrls['wx2_url'] ?? '',
                'wx3_url' => $dwzUrls['wx3_url'] ?? '',
                'br_url' => $dwzUrls['br_url'] ?? '',
                'qq_url' => $dwzUrls['qq_url'] ?? '',
            ];
        }
        if (!empty($insert)) {
            $spreadAgentModel->saveAll($insert);
        }
        return true;
    }

    protected function get_agent_users()
    {
        return AdminModel::where('role', 2)->select()->toArray();
    }

    protected function get_spread_list()
    {
        return SpreadModel::field('id,title,url')->select()->toArray();
    }
}
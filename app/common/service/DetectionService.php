<?php

namespace app\common\service;

use app\common\model\AutoDetection;
use app\common\model\SpreadAgent;
use app\common\model\SpreadModel;
use app\common\model\SysConfig;
use GuzzleHttp\Client;
use think\facade\Db;

class DetectionService
{
    public function detection($channels = [])
    {
        $this->geUrlSource();
        if (in_array(1, $channels)) {
            $this->jiqiangCheck();
        }
        if (in_array(2, $channels)) {
            $this->qilinCheck();
        }
        $this->openStatus();
        return true;
    }

    /**
     * 获取待检测URL
     * @return mixed
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public function geUrlSource()
    {
        $result = [];
        // spread
        $spreadList = SpreadModel::field('id,url,title')->select();
        // config
        $configList = SysConfig::whereIn('name', ['yongjiu_url', 'channel_list'])->field('id,name,value')->select();
        // spread_agent
        $spreadAgentList = SpreadAgent::field('id,sms_url,custom_url,du_url,du1_url,dy_url,dy1_url,wb_url,wx_url,wx1_url,wx2_url,wx3_url,qq_url')->order('id desc')->limit(1)->find();
        if ($spreadList) {
            foreach ($spreadList as $item) {
                $result[] = [
                    'url_obj_id' => $item['id'],
                    'url_jiance' => $this->getDomain($item['url']),
                    'url_laiyuan' => 'spread表 id: ' . $item['id'] . ' 名称：' . $item['title'],
                ];
            }
        }
        if ($configList) {
            foreach ($configList as $config) {
                if ($config['name'] == 'yongjiu_url') {
                    $result[] = [
                        'url_obj_id' => $config['id'],
                        'url_jiance' => $this->getDomain($config['value']),
                        'url_laiyuan' => 'common_sys_config表 id: ' . $config['id'] . ' 名称：' . $config['name'],
                    ];
                }
                if ($config['name'] == 'channel_list') {
                    $values = json_decode($config['value'], true);
                    foreach ($values as $value) {
                        $result[] = [
                            'url_obj_id' => $config['id'],
                            'url_jiance' => $this->getDomain($value['url']),
                            'url_laiyuan' => 'common_sys_config表 id: ' . $config['id'] . '名称：' . $config['name'] . ' ' . $value['name'],
                        ];
                    }
                }
            }
        }
        if ($spreadAgentList) {
            $objId = $spreadAgentList['id'];
            unset( $spreadAgentList['id']);
            foreach ($spreadAgentList->getData() as $field => $url) {
                if ($field != 'id') {
                    $result[] = [
                        'url_obj_id' => $objId,
                        'url_jiance' => $this->getDomain($url),
                        'url_laiyuan' => 'common_spread_agent id: ' . $objId . ' 字段：' . $field,
                    ];
                }
            }
        }
        $uniqueResult = array_reduce($result, function ($carry, $item) {
            if (!isset($carry[$item['url_jiance']])) {
                $carry[$item['url_jiance']] = $item;
            }
            return $carry;
        }, []);
        $model = new AutoDetection();
        foreach ($uniqueResult as $item) {
            $where = [
                'url_jiance' => $item['url_jiance'],
            ];
            $hasDomain = $model->where($where)->find();
            if (!$hasDomain) {
                $item['create_time'] = time();
                $item['update_time'] = time();
                $model->insert($item);
            } else {
                $item['update_time'] = time();
                $model->where('id', $hasDomain['id'])->update($item);
            }
        }
        // 将结果转换回数组格式
        return true;
    }

    /**
     * @return true
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     * @link https://user.urlzt.com/login
     */
    public function jiqiangCheck()
    {
        $lists = AutoDetection::select()->toArray();
        $model = new AutoDetection();
        foreach ($lists as $item) {
            $update = [
                'status' => 1,
                'abnormal_memo' => '',
                'jiqiang_response' => null
            ];
            $jiqiangResult = $this->jiqiangCheckDomain($item['url_jiance']);
            if (!empty($jiqiangResult)) {
                foreach ($jiqiangResult as $check => $vo) {
                    if ($vo['code'] != 200) {
                        $update['status'] = 0;
                        $update['abnormal_memo'] .= $check . ':' . $vo['msg'] . PHP_EOL;
                    }
                }
            }
            $update['jiqiang_response'] = json_encode($jiqiangResult, JSON_UNESCAPED_UNICODE);
            $update['jiqiang_time'] = date('Y-m-d H:i:s');
            $model->where('id', $item['id'])->update($update);
        }
        return true;
    }

    public function openStatus()
    {
        $lists = AutoDetection::select()->toArray();
        $model = new AutoDetection();
        foreach ($lists as $item) {
            $openUrl = check_domain_accessible($item['url_jiance']);
            $openUrlStatusText = ($openUrl === true) ? '是' : '否';
            $model->where('id', $item['id'])->update(['open_status' => $openUrlStatusText]);
        }
        return true;
    }

    /**
     * @return true
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     * @link
     */
    public function qilinCheck()
    {
        $lists = AutoDetection::select()->toArray();
        $model = new AutoDetection();
        foreach ($lists as $item) {
            $update = [
                'status' => 1,
                'qilin_abnormal_memo' => '',
                'qilin_response' => null
            ];
            $qilinResult = $this->qilinCheckDomain($item['url_jiance']);
            if (!empty($qilinResult)) {
                foreach ($qilinResult as $check => $vo) {
                    if ($vo['code'] != 1001) {
                        $update['status'] = 0;
                        $update['qilin_abnormal_memo'] .= $check . ':' . $vo['msg'] . PHP_EOL;
                    }
                }
            }
            $update['qilin_response'] = json_encode($qilinResult, JSON_UNESCAPED_UNICODE);
            $update['qilin_time'] = date('Y-m-d H:i:s');
            $model->where('id', $item['id'])->update($update);
        }
        return true;
    }

    public function cxbCheck()
    {
        // todo 查小宝
    }

    protected function jiqiangCheckDomain($domain)
    {
        $token = config('ToConfig.jiqiang_token');
        $client = new Client([
            'base_uri' => 'https://api.new.urlzt.com',
        ]);
        $checkLists = [
            'qq' => '/api/qq',
            'wx' => '/api/vx',
            'weibo' => '/api/wbjc',
            'douyin' => '/api/dyjc',
        ];
        $params = [
            'url' => $domain,
            'token' => $token,
            'format' => 'json'
        ];
        $params = http_build_query($params);
        $result = [];
        foreach ($checkLists as $k => $v) {
            try {
                $response = $client->request('POST', $v . '?' . $params, []);
                $res = json_decode($response->getBody()->getContents(), true);
                $result[$k] = $res;
            } catch (\Throwable $exception) {
                $result[$k] = ['code' => 201, 'msg' => $exception->getMessage()];
            }

        }
        return $result;
    }

    protected function qilinCheckDomain($domain)
    {
        $userName = config('ToConfig.qilin_username');
        $key = config('ToConfig.qilin_key');
        $client = new Client([
            'base_uri' => 'https://api.uouin.com',
        ]);
        $params = [
            'username' => $userName,
            'key' => $key,
            'url' => $domain,
        ];
        $params = http_build_query($params);
        $headers = [
            'Accept' => '*/*',
            'Accept-Language' => 'zh-CN,zh;q=0.8',
            'Connection' => 'close',
        ];
        $checkLists = [
            'qq' => '/app/qq',
            'wx' => '/app/wx',
            'weibo' => '/app/weibo',
            'douyin' => '/app/dyjc',
        ];
        $result = [];
        foreach ($checkLists as $k => $v) {
            try {
                $response = $client->request('POST', $v . '?' . $params, ['headers' => $headers]);
                $res = json_decode($response->getBody()->getContents(), true);
                $result[$k] = $res;
            } catch (\Throwable $exception) {
                $result[$k] = ['code' => 1002, 'msg' => $exception->getMessage()];
            }
            usleep(1500000);
        }
        return $result;
    }

    protected function getDomain($url)
    {
        // 使用 parse_url 解析 URL
        $parsedUrl = parse_url($url);

        // 获取协议和域名部分
        $scheme = $parsedUrl['scheme'];
        $host = $parsedUrl['host'];
        return $scheme . '://' . $host;
    }
}
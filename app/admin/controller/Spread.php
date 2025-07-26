<?php

namespace app\admin\controller;

use app\common\model\SpreadAgent;
use app\common\model\SpreadModel;
use app\common\model\AdminModel;
use app\common\service\DwzkrService;
use \app\validate\Spread as validates;
use think\exception\ValidateException;
use think\facade\Db;
use think\response\Json;

class Spread extends Base
{
    /**
     * 推广后台控制器
     */
    public function initialize()
    {
        parent::initialize();
    }

    /**
     * 列表
     * @return mixed
     */
    public function index()
    {
        //当前页
        $page = $this->request->post('page', 1);
        //每页显示数量
        $limit = $this->request->post('limit', 10);
        //查询搜索条件
        $post = array_filter($this->request->post());
        $map = [];
        isset($post['title']) && $map [] = ['title', 'like', '%' . $post['title'] . '%'];
        $role = session('admin_user.role');
        if ($role == 2) {
            $map[] = ['status', '=', 1];
        }
        $list = SpreadModel::page_list($map, $limit, $page);
        return $this->success($list);
    }

    public function agentIndex()
    {
        $agentUid = session('admin_user.id');

        // 获取防封地址
        $list = SpreadAgent::where('agent_uid', $agentUid)->select()->toArray();
        if(!isset($list[0])){
            return $this->failed('没找到他的代理链接请重新生成');
        }
        $listData = $list[0];

        // 获取配置短网址
        $agentUser = (new AdminModel)->where('id','=',$agentUid)->find();
        if(!is_null($agentUser->duan_url)){
            $listData['duan_url'] = $agentUser->duan_url;
        }else{
            $listData['duan_url'] = '请管理员添加';
        }        

        // 返回数据仓库定义
        $returnData = [];
        // 短网址
        $returnData[] = [
            'title'=>'短域名',
            'url'=>$listData['duan_url']
        ];
        // 普通
        $returnData[] = [
            'title'=>'浏览器',
            'url'=>$listData['sms_url']
        ];
        // 微信
        $returnData[] = [
            'title'=>'微信',
            'url'=>$listData['wx_url']
        ];
        // QQ
        $returnData[] = [
            'title'=>'QQ',
            'url'=>$listData['qq_url']
        ];
        // 抖音
        $returnData[] = [
            'title'=>'抖音',
            'url'=>$listData['dy_url']
        ];
        // 微博
        $returnData[] = [
            'title'=>'微博',
            'url'=>$listData['wb_url']
        ];
        return $this->success($returnData);
    }

    /**
     * @return mixed|null
     */
    public function add(): Json
    {
        return $this->failed('禁止新增');
        //过滤数据
        $postField = 'title,status,remarks,url';
        $post = $this->request->only(explode(',', $postField), 'post', null);

        //验证数据
        try {
            validate(validates::class)->scene('add')->check($post);
        } catch (ValidateException $e) {
            // 验证失败 输出错误信息
            return $this->failed($e->getMessage());
        }
        $check = strpos($post['url'], '?');
        //如果存在 ?
        if ($check !== false) {
            //如果 ? 后面没有参数，如 http://www.yitu.org/index.php?
            if (substr($post['url'], $check + 1) == '') {
                //可以直接加上附加参数
                $new_url = $post['url'];
            } else { //如果有参数，如：http://www.yitu.org/index.php?ID=12
                $new_url = $post['url'] . '&';
            }
        } else {//如果不存在 ?
            $new_url = $post['url'] . '?';
        }
        Db::startTrans();
        try {
            $create = [
                'title' => $post['title'],
                'status' => $post['status'],
                'url' => $new_url,
                'remarks' => $post['remarks'] ?? '',
            ];
            $res = SpreadModel::create($create);
            if ($res) {
                (new DwzkrService())->add_spread($res->id, $post['title'], $new_url);
            }
            Db::commit();
            return $this->success('操作成功');
        } catch (\Exception $e) {
            // 回滚事务
            Db::rollback();
            return $this->failed('新增失败'. $e->getMessage());
        }
    }

    /**
     * 编辑推广链接
     * @return mixed|null
     */
    public function edit()
    {
        $postField = 'id,title,status,remarks,url'; // 此处标题禁止修改
        $post = $this->request->only(explode(',', $postField), 'post', null);
        //验证数据
        try {
            validate(validates::class)->scene('edit')->check($post);
        } catch (ValidateException $e) {
            // 验证失败 输出错误信息
            return $this->failed($e->getMessage());
        }
        $new_url = $post['url'];
        Db::startTrans();
        try {
            $updateUrl = false;
            $spreadInfo = SpreadModel::find($post['id']);
            if ($spreadInfo['url'] != $new_url) {
                $updateUrl = true;
            }
            $update = [
                'id' => $post['id'],
                // 'title' => $post['title'],
                'status' => $post['status'],
                'url' => $new_url,
                'remarks' => $post['remarks'] ?? '',
            ];
            $up =  SpreadModel::update($update);
            Db::commit();

            // 如果 url 不一样 同步 修改 更新响应的内容
            if ($updateUrl && $up) {
                (new DwzkrService())->edit_spread($post['id'], $post['title'], $post['url']);
            }
            return $this->success('操作成功');
        } catch (\Exception $e) {
            // 回滚事务
            Db::rollback();
            return $this->failed('修改失败');
        }
    }

    public function detail()
    {
        $postField = 'id';
        $post = $this->request->only(explode(',', $postField), 'post', null);
        //验证数据
        try {
            validate(validates::class)->scene('detail')->check($post);
        } catch (ValidateException $e) {
            // 验证失败 输出错误信息
            return $this->failed($e->getMessage());
        }
        $res = SpreadModel::find($post['id']);
        if ($res) {
            return $this->success($res);
        }
        return $this->failed('获取失败');
    }

    public function del()
    {
        return $this->failed('禁止删除');
        // $postField = 'id';
        // $post = $this->request->only(explode(',', $postField), 'post', null);
        // //验证数据
        // try {
        //     validate(validates::class)->scene('detail')->check($post);
        // } catch (ValidateException $e) {
        //     // 验证失败 输出错误信息
        //     return $this->failed($e->getMessage());
        // }
        // $res = SpreadModel::where('id', $post['id'])->delete();
        // if ($res) {
        //     (new DwzkrService())->delete_spread($post['id']);
        //     return $this->success(['id' => $post['id']]);
        // }
        // return $this->failed('获取失败');
    }

    // 清理全部重建
    public function clearRebuildAll()
    {
        // 重新生成所有推广
        $service = new DwzkrService();
        $res = $service->re_create_all();
        if ($res) {
            return $this->success(['res' => $res]);
        }
        return $this->failed('生成失败');
    }
}
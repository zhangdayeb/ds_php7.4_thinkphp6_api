<?php

namespace app\admin\controller;

use app\common\model\AdminModel;
use app\common\model\SkinModel;
use think\db\exception\DataNotFoundException;
use think\db\exception\DbException;
use think\db\exception\ModelNotFoundException;
use think\exception\ValidateException;
use app\validate\SkinValidate;
use think\facade\Db;

class Skin extends Base
{
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
        $list = SkinModel::page_list($map, $limit, $page);
        return $this->success($list);
    }

    /**
     * 添加
     * @return mixed|null
     */
    public function add()
    {
        //过滤数据
        $postField = 'title,domain,img_url,status,remark';
        $post = $this->request->only(explode(',', $postField), 'post', null);

        //验证数据
        try {
            validate(SkinValidate::class)->scene('add')->check($post);
        } catch (ValidateException $e) {
            // 验证失败 输出错误信息
            return $this->failed($e->getMessage());
        }

        Db::startTrans();
        try {
            $create = [
                'title' => $post['title'],
                'domain' => $post['domain'],
                'img_url' => $post['img_url'],
                'status' => $post['status'],
                'remark' => $post['remark'],
            ];
            SkinModel::create($create);
            Db::commit();
            return $this->success('操作成功');
        } catch (\Exception $e) {
            // 回滚事务
            Db::rollback();
            return $this->failed($e->getMessage());
        }
    }

    /**
     * 修改
     * @return mixed|null
     */
    public function edit()
    {
        //过滤数据
        $postField = 'id,title,domain,img_url,status,remark';
        $post = $this->request->only(explode(',', $postField), 'post', null);

        //验证数据
        try {
            validate(SkinValidate::class)->scene('edit')->check($post);
        } catch (ValidateException $e) {
            // 验证失败 输出错误信息
            return $this->failed($e->getMessage());
        }

        Db::startTrans();
        try {
            $update = [
                'id' => $post['id'],
                'title' => $post['title'],
                'domain' => $post['domain'],
                'img_url' => $post['img_url'],
                'status' => $post['status'],
                'remark' => $post['remark'],
            ];
            SkinModel::update($update);
            Db::commit();
            return $this->success('操作成功');
        } catch (\Exception $e) {
            // 回滚事务
            Db::rollback();
            return $this->failed('修改失败');
        }
    }

    /**
     * 查看选中的
     * @return mixed
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function info()
    {
        $info = AdminModel::where('id', session('admin_user.id'))->field(['id', 'skin_id'])->find();
        return $this->success($info);
    }

    /**
     * 代理商设置皮肤
     * @return mixed|null
     */
    public function agentedit()
    {
        //过滤数据
        $postField = 'id';
        $post = $this->request->only(explode(',', $postField), 'post', null);
//        $role = session ('admin_user.role');
//        if ($role != 2){
//            return $this->failed('当前非代理商');
//        }
        //验证数据
        try {
            validate(SkinValidate::class)->scene('agentedit')->check($post);
        } catch (ValidateException $e) {
            // 验证失败 输出错误信息
            return $this->failed($e->getMessage());
        }

        Db::startTrans();
        try {
            $update = [
                'id' => session('admin_user.id'),
                'skin_id' => $post['id'],
            ];
            AdminModel::update($update);
            Db::commit();
            return $this->success('操作成功');
        } catch (\Exception $e) {
            // 回滚事务
            Db::rollback();
            return $this->failed('新增失败');
        }
    }

    public function detail()
    {
        //过滤数据
        $postField = 'id';
        $post = $this->request->only(explode(',', $postField), 'post', null);
        //验证数据
        try {
            validate(SkinValidate::class)->scene('agentedit')->check($post);
        } catch (ValidateException $e) {
            // 验证失败 输出错误信息
            return $this->failed($e->getMessage());
        }
        $detail  = SkinModel::where('id', $post['id'])->find();
        if ($detail) {
            return $this->success($detail);
        }
        return $this->failed('查询失败');
    }

    public function del()
    {
        //过滤数据
        $postField = 'id';
        $post = $this->request->only(explode(',', $postField), 'post', null);
        //验证数据
        try {
            validate(SkinValidate::class)->scene('agentedit')->check($post);
        } catch (ValidateException $e) {
            // 验证失败 输出错误信息
            return $this->failed($e->getMessage());
        }
        $del = SkinModel::where('id', $post['id'])->delete();
        if ($del) {
            return $this->success('删除成功');
        }
        return $this->failed('删除失败');
    }
}
<?php

namespace app\admin\controller;

use app\common\model\WebcardModel;
use app\validate\WebcardValidate;
use think\db\exception\DbException;
use think\exception\ValidateException;
use think\facade\Db;

class Webcard extends Base
{
    public function initialize ()
    {
        parent::initialize();
    }

    /**
     * 列表
     * @return mixed
     * @throws DbException
     */
    public function index()
    {
        //当前页
        $page = $this->request->post('page', 1);
        //每页显示数量
        $limit = $this->request->post('limit', 10);
        //查询搜索条件
        $post = array_filter($this->request->post());
        $map = [['agent_uid','=',session ( 'admin_user.id' ),]];
        isset($post['title']) && $map [] = ['title', 'like', '%' . $post['title'] . '%'];
        $list = WebcardModel::page_list($map, $limit, $page);
        return $this->success($list);
    }

    /**
     * 新增
     * @return mixed|null
     */
    public function add ()
    {
        //过滤数据
        $postField = 'title,img_url,remarks,url';
        $post = $this->request->only(explode(',', $postField), 'post', null);

        //验证数据
        try {
            validate(WebcardValidate::class)->scene('add')->check($post);
        } catch (ValidateException $e) {
            // 验证失败 输出错误信息
            return $this->failed($e->getMessage());
        }

        Db::startTrans();
        try {
            $create = [
                'title'     => $post[ 'title' ],
                'img_url'   => $post[ 'img_url' ],
                'remarks'   => $post[ 'remarks' ] ?? '',
                'url'       => $post[ 'url' ],
                'agent_uid' => session ( 'admin_user.id' ),
            ];
            WebcardModel::create ($create);
            Db::commit();
            return $this->success('操作成功');
        } catch (\Exception $e) {
            // 回滚事务
            Db::rollback();
            return $this->failed('新增失败');
        }
    }

    /**
     * 修改
     * @return mixed|null
     */
    public function edit ()
    {
        //过滤数据
        $postField = 'id,title,img_url,remarks,url';
        $post = $this->request->only(explode(',', $postField), 'post', null);

        //验证数据
        try {
            validate(WebcardValidate::class)->scene('edit')->check($post);
        } catch (ValidateException $e) {
            // 验证失败 输出错误信息
            return $this->failed($e->getMessage());
        }

        Db::startTrans();
        try {
            $update = [
                'id'      => $post[ 'id' ],
                'title'   => $post[ 'title' ],
                'img_url' => $post[ 'img_url' ],
                'remarks' => $post[ 'remarks' ]??'',
                'url'     => $post[ 'url' ]
            ];
            WebcardModel::update ($update);
            Db::commit();
            return $this->success('操作成功');
        } catch (\Exception $e) {
            // 回滚事务
            Db::rollback();
            return $this->failed('修改失败');
        }
    }
}
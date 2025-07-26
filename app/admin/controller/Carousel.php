<?php

namespace app\admin\controller;

use app\common\model\CarouselModel;
use app\validate\CarouselValidate;
use think\exception\ValidateException;
use think\facade\Db;

class Carousel extends Base
{
    public function initialize ()
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
        $list = CarouselModel::page_list($map, $limit, $page);
        return $this->success($list);
    }


    /**
     * 添加
     * @return mixed|null
     */
    public function add()
    {
        //过滤数据
        $postField = 'title,img_url,url,sort,status';
        $post = $this->request->only(explode(',', $postField), 'post', null);

        //验证数据
        try {
            validate(CarouselValidate::class)->scene('add')->check($post);
        } catch (ValidateException $e) {
            // 验证失败 输出错误信息
            return $this->failed($e->getMessage());
        }

        Db::startTrans();
        try {
            $create = [
                'title'   => $post[ 'title' ],
                'url'     => $post[ 'url' ],
                'img_url' => $post[ 'img_url' ],
                'status'  => $post[ 'status' ],
                'sort'    => $post[ 'sort' ] ?? 255,
            ];
            CarouselModel::create ($create);
            Db::commit();
            return $this->success('操作成功');
        } catch (\Exception $e) {
            // 回滚事务
            Db::rollback();
            return $this->failed($e->getMessage ());
        }
    }

    /**
     * 修改
     * @return mixed|null
     */
    public function edit()
    {
        //过滤数据
        $postField = 'id,title,img_url,url,sort,status';
        $post = $this->request->only(explode(',', $postField), 'post', null);

        //验证数据
        try {
            validate(CarouselValidate::class)->scene('edit')->check($post);
        } catch (ValidateException $e) {
            // 验证失败 输出错误信息
            return $this->failed($e->getMessage());
        }

        Db::startTrans();
        try {
            $update = [
                'id'      => $post[ 'id' ],
                'title'   => $post[ 'title' ],
                'url'     => $post[ 'url' ],
                'img_url' => $post[ 'img_url' ],
                'status'  => $post[ 'status' ],
                'sort'    => $post[ 'sort' ] ?? 255,
            ];
            CarouselModel::update ($update);
            Db::commit();
            return $this->success('操作成功');
        } catch (\Exception $e) {
            // 回滚事务
            Db::rollback();
            return $this->failed('修改失败');
        }
    }
}
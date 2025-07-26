<?php

namespace app\home\controller\common;

use app\BaseController;
use app\home\controller\service\GoodsService;

class Common extends BaseController
{
    /**
     * 获取系统名称
     * @return \think\response\Json
     */
    public function getsystitle()
    {
        return show(['title' => getSystemConfig('system_title'), 'share_modal' => getSystemConfig('share_modal'), 'share_banner' => getSystemConfig('share_banner'), 'pay_tips' => getSystemConfig('pay_tips')]);
    }

    public function getConfValue()
    {
        $name = $this->request->post('name', '');
        if ($name == '') {
            return show([]);
        }
        $value = getSystemConfig($name);

        return show([$name => $value]);
    }


    /**
     * 首页分类视频列表
     * @return \think\response\Json
     */
    public function popular_movies()
    {
        $service = new GoodsService();
        //获取套餐信息
        $find = $service->video_model()->popular_movies();
        if (!$find)  return show([],config('ToConfig.http_code.error'),'没有分类视频');
        //购买成功
        return show($find);
    }

    /**
     * 获取视频分类
     * @return \think\response\Json
     */
    public function video_type_list()
    {
        $service = new GoodsService();
        $find = $service->video_model()->video_type_list();
        if (!$find)  return show([],config('ToConfig.http_code.error'),'没有该分类哦！');
        return show($find);
    }

    /**
     * 获取视频标签
     * @return \think\response\Json
     */
    public function video_tag_list()
    {
        $service = new GoodsService();
        $find = $service->video_model()->video_tag_list();
        if (!$find)  return show([],config('ToConfig.http_code.error'),'没有该标签哦！');
        return show($find);
    }
}
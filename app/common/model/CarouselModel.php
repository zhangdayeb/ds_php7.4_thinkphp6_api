<?php

namespace app\common\model;

use think\Model;

class CarouselModel extends Model
{
    protected $name = 'common_carousel';

    public static function page_list ($map,$limit, $page)
    {
        return self::where($map)
            ->order('sort asc,id desc')
            ->paginate(['list_rows' => $limit, 'page' => $page]);
    }


    /**
     * 获取图片
     * @param $value
     * @return string
     */
    protected function getImgUrlAttr ($value)
    {
        return empty($value) ? '' : config('ToConfig.app_update.image_url').'/'.$value;
    }

    /**
     * 设置图片
     * @param $value
     * @return array|string|string[]
     */
    protected function setImgUrlAttr ($value)
    {
        return str_replace(config('ToConfig.app_update.image_url'),'',$value);
    }
}
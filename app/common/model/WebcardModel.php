<?php

namespace app\common\model;

use think\db\exception\DbException;
use think\Model;
use think\Paginator;

class WebcardModel extends Model
{
    protected $name = 'common_webcard';

    /**
     * 列表查看
     * @param $map
     * @param $limit
     * @param $page
     * @return Paginator
     * @throws DbException
     */
    public static function page_list ($map,$limit, $page)
    {
        return self::where($map)
            ->order('id desc')
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
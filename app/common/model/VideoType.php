<?php


namespace app\common\model;


use app\common\traites\TraitModel;
use think\Model;

class VideoType extends Model
{
    use TraitModel;

    public $name = 'video_type';

    /**
     * 两级菜单
     * @param $page /当前页
     * @param $limit /每页显示
     * @return \think\Paginator
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public static function page_list($page,$limit)
    {
//        return self::order('id desc')->select();
        return self::order('id desc')->where(['pid'=>0])
            ->paginate(['list_rows'=>$limit,'page'=>$page])->each(function ($item, $key) {
                $item->children = self::where(['pid' => $item['id']])->select();
            });
    }


}
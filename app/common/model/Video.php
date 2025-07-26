<?php


namespace app\common\model;


use app\common\traites\TraitModel;
use think\Model;

class Video extends Model
{
    use TraitModel;
    public $name = 'video';

    public static function page_list($map,$limit, $page)
    {
        return self::alias('a')
            ->where($map)
            ->join('video_type b', 'a.type = b.id', 'left')
            ->join('video_to_vip c', 'a.id = c.video_id', 'left')
            ->field('a.*,b.title name,c.types')
            ->order('type asc,id desc')
            ->paginate(['list_rows' => $limit, 'page' => $page], false)->each(function($item, $key){
               if (empty($item->types)) return ;
                $types=array_filter(explode(',',$item->types));
                foreach ($types as &$value){
                    $value= intval($value);
                }
                $item->types=$types;
            });
    }
}
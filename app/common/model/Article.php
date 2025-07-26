<?php


namespace app\common\model;


use app\common\traites\TraitModel;
use think\Model;

class Article extends Model
{
    use TraitModel;
    public $name = 'common_article';

    public static function page_list($map,$limit, $page)
    {
        return self::alias('a')
            ->where($map)
            ->join('common_article_type b', 'a.type = b.id', 'left')
            ->field('a.*,b.name')
            ->order('type asc,id desc')
            ->paginate(['list_rows' => $limit, 'page' => $page], false)->each(function($item, $key){
                return  $item->content = returnEditor($item->content);
                 //!empty($item->thumb_url) && $item->thumb_url=explode(',',$item->thumb_url);
            });
    }
}
<?php

namespace app\common\model;

use think\Model;

class SpreadModel extends Model
{
    public $name = 'common_spread';


    public static function page_list($map,$limit, $page)
    {
        return self::where($map)
            ->order('id desc')
            ->paginate(['list_rows' => $limit, 'page' => $page])->each(function($item, $key){
                if (empty($item->url)) return ;
                $check = strpos($item->url, '?');
                //如果存在 ?
                if ($check !== false) {
                    //如果 ? 后面没有参数，如 http://www.yitu.org/index.php?
                    if (substr($item->url, $check + 1) == '') {
                        //可以直接加上附加参数
                        $new_url = $item->url;
                    } else { //如果有参数，如：http://www.yitu.org/index.php?ID=12
                        $new_url = $item->url . '&';
                    }
                } else {//如果不存在 ?
                    $new_url = $item->url;
                }
                $item->url=$new_url;
            });
    }
}
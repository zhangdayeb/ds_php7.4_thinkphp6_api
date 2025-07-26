<?php


namespace app\common\model;


use app\common\traites\TraitModel;
use think\Model;

class Agent extends Model
{
    use TraitModel;

    public $name = 'common_agent';
    public $httptype=['http://','https://'];
    public function getAgentPwdAttr($value)
    {
        return '';
    }

    public static function page_list($where, $limit, $page)
    {
        $map=[];
        if (session('admin_user.agent')) $map=['id'=>session('admin_user.id')];

        $list = self::alias('a')
            ->where($where)
            ->where($map)
            ->order('id desc')
            ->paginate(['list_rows' => $limit, 'page' => $page], false)->each(function($item, $key){
                //推广地址组装
                $item->tg_url_txt= $item->httptype[$item->agreement].$item->tg_url.'.'.config('ToConfig.app_tg.tg_url').'?code='.$item->mask_code;
                $item->agreement=''.$item->agreement;
            });

        return $list->hidden(['agent_pwd']);
    }
}
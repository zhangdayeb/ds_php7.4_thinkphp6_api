<?php


namespace app\common\model;


use app\common\traites\TraitModel;
use think\Model;

class Notice extends Model
{
    use TraitModel;

    public $name = 'common_notice';
    public $positions =
        [
            1 => '首页',
            2 => '代理',
        ];

    public function getPositionAttr($value)
    {
        return $value;
//        return isset($this->position[$value]) ? ['key' => $value, 'value' => $this->position[$value]] : $value;
    }

    public static function page_list($where, $limit, $page)
    {


        return self::alias('a')
            ->where($where)
            ->order('id desc')
            ->paginate(['list_rows' => $limit, 'page' => $page])->each(function ($item, $key) {
                $item->value = isset($item->positions[$item->position]) ? $item->positions[$item->position] : '';
            });
    }


}
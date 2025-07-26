<?php


namespace app\common\model;


use app\common\traites\TraitModel;
use think\Model;

class VideoVipLevel extends Model
{
    use TraitModel;

    public $name = 'video_vip_level';

    public $typeName = [['id' => 1, 'name' => '视频购买套餐'], ['id' => 2, 'name' => '充值套餐'], ['id' => 3, 'name' => '赠送类型']];
}
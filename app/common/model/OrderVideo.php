<?php


namespace app\common\model;


use app\common\traites\TraitModel;
use think\Model;

class OrderVideo extends Model
{
    use TraitModel;
    public $name = 'video_user_level';

    public static function user_level($uid){
        //查询用户是购买的等级
        return self::where(['uid'=>$uid,'status'=>1])
            ->field('order_id,vip_level,vip_end_time,vip_start_time')
            ->where('vip_end_time','>=',date('Y-m-d :H:i:s'))
            ->order('vip_end_time desc')
            ->find();
    }
}
<?php


namespace app\common\model;


use app\common\traites\TraitModel;
use think\Model;

class VideoUserLevel extends Model
{
    use TraitModel;

    public $name = 'video_user_level';


    public static function getUserEndTime($uid)
    {
        return self::where('uid', $uid)->value('vip_end_time');
    }

    public static function getUserStartTime($uid)
    {
        return self::where('uid', $uid)->value('vip_start_time');
    }
}
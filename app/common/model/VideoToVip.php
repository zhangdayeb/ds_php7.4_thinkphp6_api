<?php


namespace app\common\model;


use app\common\traites\TraitModel;
use think\Model;

class VideoToVip extends Model
{
    use TraitModel;
    public $name = 'video_to_vip';

    /**
     * 查看当前等级是否可以看当前视频
     * @param $level /用户等级
     * @param $videoId /视频ID
     */
    public function qualifications($level,$videoId)
    {
        $find = $this->where('video_id',$videoId)->find();
        if (empty($find) || empty($find->types)) return false;//证明没有加入到套餐表
        //if ($find->types == null || $find->types == 0) return true;
        $types=explode(',',$find->types);
        $res = in_array($level,$types);
        return $res;
    }
}
<?php


namespace app\common\model;


use app\common\traites\TraitModel;
use think\Model;

class VideoBuyUserVideo extends Model
{
    use TraitModel;
    public $name = 'video_buy_user_video';

    //查看当前视频是否被单独购买了
    public function alone_purchase($userId,$videoId)
    {
        $find =$this->whereTime('end_time','>=',time())->where(['uid'=>$userId,'video_id'=>$videoId,'status'=>1])->find();
        if (empty($find)){
            return false;
        }
        return true;
    }

    //获取用户单独购买的视频
    public function user_list($id,$limit,$page,$map)
    {
        return $this->alias('a')
            ->field('b.*')
            ->where($map)
            ->whereTime('end_time','>=',date('Y-m-d H:i:s'))
            ->where(['uid'=>$id,'a.status'=>1])
            ->join('video b','a.video_id =b.id')
            ->paginate(['list_rows' => $limit, 'page' => $page]);
    }
}
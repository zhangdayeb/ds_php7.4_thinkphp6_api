<?php
namespace app\home\controller;


use app\common\model\AdminModel;
use app\common\model\Ads;
use app\common\model\CarouselModel;
use app\common\model\Video;
use app\home\controller\service\GoodsService;
use app\common\model\AccessLog;

use app\Request;

use hg\apidoc\annotation as Apidoc;
/**
 *
 * @Apidoc\Title("管理首页")
 * */
class Index extends Base{

    /**
     * @Apidoc\Title("首页广告轮播图")
     * @Apidoc\Method("GET")
     */
    public function ads(){
      $TouziAds= new Ads();
      $res=$TouziAds->field('id,img')->where('status',1)->order('sort asc')->select();
      return show($res);
    }

    /**
     * 轮播列表
     * @return \think\response\Json
     */
    public function carouse ()
    {//当前页
        $page = $this->request->post('page', 1);
        //每页显示数量
        $limit = $this->request->post('limit', 10);
        $list = CarouselModel::page_list([['status','=',1]], $limit, $page);
        return show($list);
    }

    /**
     * 读取首页开始播放的视频链接地址
     * @return \think\response\Json
     */
    public function getvideo ()
    {
        $video_url = Video::where('is_pop', 1)->value('video_url');     // is_pop是通过后台设置的一个标示，标示设置为前台的弹窗视频，从这个字段里去获取设置的视频链接
        //将视频地址切换为m3u8地址
        $localMp4Url = root_path() . 'public/storage' . $video_url;
        $localM3u8Url = root_path() . 'public/storage/hls';
        $video_url = '/hls/'.(new GoodsService())->convertMp4ToM3u8(9999,$localMp4Url,$localM3u8Url);
        return show(['free_play'=>config('ToConfig.app_update.image_url').$video_url]);
    }

    /**
     * tong
     * @return false|\think\response\Json
     */
    public function getvideoinfo ()
    {
        //查询视频
        $find = Video::where('is_pop', 1)->find();
        //没有该视频
        if (empty($find)) show([],config('ToConfig.http_code.error'),'没有该视频哦！');
        //is_purchase 1可观看  2需要单独购买 5不可观看
        if ($find->video_price<=0){
            $agentid = session ('home_user.agentid');
            if ($agentid>0){
//            $list = (new VideoVipLevel())->where(['status'=>1,'type'=>$type])->select();\
                $find->video_price = AdminModel::where('id',$agentid)->value ('price_single_low');
            }else{
                // 读取大后天设置的套装
                $data_info = getSystemConfig ('reward_allocation');
                $data_info = json_decode  ($data_info,true);
                $find->video_price = $data_info['price_single']??0;
            }
        }
        $find->is_purchase=5;
        #开始  查看当前用户是否具备查看该视频的资格
        $find = (new GoodsService)->alone_purchase($find);
        #结束
        //弹窗预览时间
        $data_info = getSystemConfig ('reward_allocation');
        $data_info = json_decode($data_info,true);
        $find->index_time = (int)$data_info['index_time'] ?? 0;

        //将视频地址切换为m3u8地址
        $localMp4Url = str_replace(config('app.app_host'),config('app.root_path').'/public',$find->video_url);
        $localMp4Url = root_path() . str_replace(config('ToConfig.app_update.image_url'), 'public/storage', $localMp4Url);
        $localM3u8Url = root_path() . 'public/storage/hls';
        $find->video_url = '/hls/'.(new GoodsService)->convertMp4ToM3u8($localMp4Url,$localM3u8Url);
        return show($find);

    }

    // 获取打开请求 记录
    public function accessRecord(){
        //过滤数据
        $postField = 'url';
        $post = $this->request->only(explode(',', $postField), 'post', null);
        $userId = session('home_user.id');
        $agent = $this->request->header('user-agent'); 
        $referer = $this->request->header('referer'); 
        $agent_uid = session('home_user.agentid');

        $data = [];
        $data['agent_uid'] =  $agent_uid;
        $data['user_ip'] = request ()->ip ();
        $data['user_agent'] = $agent;
        $data['open_page'] = $post['url'];;
        $data['user_id'] = $userId;
        $data['come_url'] = $referer;
        (new AccessLog())->insert($data);

        if($agent_uid >0){
            // 更新代理的 访问 统计
            (new AdminModel)->where('id',$agent_uid)->inc('open_today')->update();
        }

        return show();
    }

// 类结束了
}
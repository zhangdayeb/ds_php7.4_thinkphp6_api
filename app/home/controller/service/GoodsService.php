<?php

namespace app\home\controller\service;

use app\common\model\AdminModel;
use app\common\model\OrderModel;
use app\common\model\PayRecharge;
use app\common\model\UserModel;
use app\common\model\VideoBuyUserVideo;
use app\common\model\VideoTag;
use app\common\model\VideoToVip;
use app\common\model\VideoType;
use app\common\model\VideoVipLevel;
use app\common\model\OrderVideo;
use app\common\model\Video;
use app\common\traites\GetTreeTrait;
use app\home\controller\pay\Index as PayIndex;
use think\exception\ValidateException;
use think\facade\Db;
use think\facade\Log;
use \think\facade\Request;
use think\facade\App;
use think\facade\Cache;

class GoodsService
{
    use GetTreeTrait;

    public $model;

    public function video_model()
    {
        $this->model = new VideoType();
        return $this;
    }

    public function level_model()
    {
        $this->model = new VideoVipLevel();
        return $this;
    }

    //查询所有套餐
    public function video_level(int $type = 1)
    {
        $agentid = session ('home_user.agentid');
        if ($agentid>0){
            $data_list = AdminModel::where('id',$agentid)
                ->field (['price_single_low','price_single_high','free_time','price_hour','price_day','price_week','price_month','price_quarter','price_year','price_forever'])
                ->find ()->toArray ();
        }else{
            // 读取大后台设置的套装
            $data_list = getSystemConfig ('reward_allocation');
            $data_list = json_decode  ($data_list,true);
        }
        $list = [];
        foreach ($data_list as $k=>$v){
            //打赏配置price_single:单片最低price_single_max:单片最高price_day:包天price_week:包周price_month:包月price_quarter:包季度price_year:包年price_forever:终身会员price_hour:1小时会员free_time:免费时长
            // 如果为空 继续
            if (is_null ($v)){
                continue;
            }
            $price_vip = $v;    // 各种价格  此刻的 v 是价格
            $duration = 0;      // 每种价格的 免费时长
            $info = getPackageName($k); // 各种价格的 名称

            // 如果没有获取到这个 标题 继续
            if (is_null ($info['title'])){
                continue;
            }

            $info[ 'price_vip' ] = $price_vip;      // 购买价格
            $info[ 'duration' ]  = (int)$duration;  // 免费时长
            $info['agentID'] = $agentid;    // 执行的那个代理的ID

            // 如果 价格 大于0  ===> 这里有一个 bug 免费时间永远出不来
            if ($info['price_vip'] > 0) {
                $list[] = $info;
            }
        }
        // 提取需要排序的键值到一个单独的数组
        $sortValues = array_column($list, 'sort');

        // 使用array_multisort()函数对键值数组和原始数组进行排序
        array_multisort($sortValues, $list);
        return $list;
    }

    //获取已经购买的单独视频列表
    public function alone_list()
    {
        //当前页
        $page = Request::post('page/d', 1);
        //每页显示数量
        $limit = Request::post('limit/d', 10);

        $type = Request::post('type/d', 0);
        //查询条件
        $map=[];
        if ($type > 0) $map['type'] = $type;

        $home_user = session('home_user');


        return (new VideoBuyUserVideo())->user_list($home_user['id'],$limit,$page,$map);
    }

    //查询用户已经购买的额视频套餐
    public function user_video_level()
    {
        $home_user = session('home_user');
        //查询用户购买的套餐
        $order_video = (new OrderVideo())->user_level($home_user['id']);
        if (empty($order_video)) return false;
        return $order_video->toArray();
        //$order_video=$order_video->toArray();
    }
    //获取视频列表
    public function common_video_list($user_video_level = 0)
    {
        //搜索条件
        $search = Request::post('search/s', 1);
        //当前页
        $page = Request::post('page/d', 1);
        //每页显示数量
        $limit = Request::post('limit/d', 10);
        //视频分类
        $type = Request::post('type/d', 0);
        //视频标签
        $istag = Request::post('is_tag/d', 0);
        //查询条件
        $model = (new Video());
        $map['status'] = 1;
        if ($istag == 1) {
            // 获取tag标签
            $tagName = VideoTag::where('id', $type)->value('name');
            if ($tagName) {
                $model = $model->whereLike('tags','%'.$tagName.'%');
            }
        } else {
            if ($type > 0) {
                $map['type'] = $type;
            }
        }
        if ($search) {
            $model = $model->whereLike('title','%'.$search.'%');
        }

        $list = $model
            ->where($map)
            ->paginate(['list_rows' => $limit, 'page' => $page])->each(function($item){
                $item->play_num = $item->play_num + rand(3000,20000);
                $item->play_time = rand(1000,100000);
                // 修改标题
                $titleArray = explode('_',$item->title);
                $title =  $titleArray[0];
                $item->title = $title;
            });
        // dd($list);
        if (empty($list)) return false;
        return $list;
    }
    //获取视频列表
    public function user_video_list($user_video_level = 0)
    {
        //搜索条件
        $search = Request::post('search/s', 1);
        //当前页
        $page = Request::post('page/d', 1);
        //每页显示数量
        $limit = Request::post('limit/d', 10);
        //视频分类
        $type = Request::post('type/d', 0);
        //视频标签
        $istag = Request::post('is_tag/d', 0);
        //查询条件
        $map['status'] = 1;
        if ($istag == 1) {
            // 获取tag标签
            $tagName = VideoTag::where('id', $type)->value('name');
            if ($tagName) {
                $map['tags'] = ['like', '%'.$tagName.'%'];
            }
        } else {
            if ($type > 0) {
                $map['type'] = $type;
            }
        }
        $list = (new VideoToVip())->alias('a')
            ->field('b.*')
            ->where($map)
            ->join('video b', 'a.video_id=b.id','right');
        if (!empty($user_video_level) || $user_video_level!=0) {
            $list = $list->whereLike('types', '%' . $user_video_level['vip_level'] . '%')->whereOr('types',null);
        }
        if ($search) {
            $list = $list->whereLike('title', '%' . $search. '%');
        }

        $list = $list->order('b.update_time desc,b.id desc')
            ->paginate(['list_rows' => $limit, 'page' => $page])->each(function($item){
                $item->play_num = $item->play_num + rand(3000,20000);
                $item->play_time = rand(1000,100000);
            });
        if (empty($list)) return false;
        return $list;
    }

    //视频详情
    public function user_video_details()
    {
        $id = Request::post('id/d', 0);
        //视频ID错误
        if ($id <= 0) return false;
        //查询视频
        $find = (new Video())->find($id);
        //没有该视频
        if (empty($find)) return false;
        
        // 开始执行正常的逻辑
        $agentid = session('home_user.agentid'); // 获取当前代理ID 
        $userID = session('home_user.id'); // 获取当前代理ID 
        
        $price_key = 'agentid'.$agentid.'videoid'.$id.'userid'.$userID;
        $show_video_price_redis = Cache::store('redis')->get($price_key) ?? 0;
        
        $show_video_price = 100;    // 初始单片支付展示价格
        $show_video_price_low = 0;  // 默认单片最低价格
        $show_video_price_high = 500;   // 默认单片最高价格
        
        // 如果代理的价格变化  或者系统的价格有变化
        if ($agentid>0){
            $agentSetPrice = AdminModel::where('id',$agentid)->field('price_single_low,price_single_high')->find();
            $show_video_price_low = $agentSetPrice['price_single_low'];
            $show_video_price_high = $agentSetPrice['price_single_high'];
            $find->price_come = '代理价格';
            
        }else{
            // 读取大后台设置的套装
            $data_info = getSystemConfig('reward_allocation'); //"price_single":"10","price_single_max":"99",
            $data_info = json_decode($data_info,true);
            $show_video_price_low = $data_info['price_single'];
            $show_video_price_high = $data_info['price_single_max'];
            $find->price_come = '总后台价格';
        }
        
        if($show_video_price_redis < $show_video_price_low || $show_video_price_redis > $show_video_price_high){
                // 超出新设定的范围
                $show_video_price = mt_rand($show_video_price_low, $show_video_price_high);
                Cache::store('redis')->set($price_key,$show_video_price);
                $find->is_huancun = '新出价格';
        }else{
            // 否则维持之前的不变
            $show_video_price = $show_video_price_redis;
            $find->is_huancun = '缓存价格';
        }
        

        $find->video_price = $show_video_price;
        //is_purchase 1可观看  2需要单独购买 5不可观看
        $find->is_purchase=5;
        #开始  查看当前用户是否具备查看该视频的资格
        $find = $this->alone_purchase($find);
        #结束
        $find->agent_uid = $agentid;
        //将视频地址切换为m3u8地址
        $localMp4Url = str_replace(config('app.app_host'),config('app.root_path').'/public',$find->video_url);
        $localM3u8Url = config('app.root_path').'/public/storage/hls';
        $localMp4Url = root_path() . str_replace(config('ToConfig.app_update.image_url'), 'public/storage', $localMp4Url);
        $localM3u8Url = root_path() . 'public/storage/hls';
        $find->video_url = '/hls/'.$this->convertMp4ToM3u8($id,$localMp4Url,$localM3u8Url);
        return $find;
    }


    //查询视频分类
    public function video_type_list()
    {

        // type 1 是分类，type 2是标签
        $find = $this->model->field('id,pid,title as name1,ifnull(name,title) as title,thumb_url,0 as is_tag')
            ->where('is_show', 1)
            ->order('sort desc,id desc')
            ->select()->each(function ($item, $key) {
                $urlstr = config('ToConfig.app_update.image_url') .'/';
                $strCount = substr_count($item->thumb_url, $urlstr);
                if ($strCount > 1) {
                    $item->thumb_url = str_replace($urlstr, '', $item->thumb_url);
                }
            });
        if (empty($find)) return false;
        $find = $find->toArray();
        array_unshift($find, ['id' => 0, 'pid' => 0, 'title' => '全部', 'name' => '全部', 'is_tag' => 0]);
        $keyIndex = 0;
        foreach ($find as &$item) {
            $item['key'] = ++$keyIndex;
        }
        return $this->fillModelBackends($find);
    }
    //查询视频标签
    public function video_tag_list()
    {
        // 获取标签
        $tagList = VideoTag::where('status', 1)
            ->order('id desc')
            ->field('id,0 as pid,name as title,name,"" as thumb_url,1 as is_tag')
            ->select();
        $tagListArr = $tagList->toArray();
        $keyIndex = 0;
        foreach ($tagListArr as &$item) {
            $item['key'] = ++$keyIndex;
        }
        return $this->fillModelBackends($tagListArr);
    }
    /**
     * 充值
     * @param int $rechargeId
     * @param int $price
     * @return array
     */
    public function recharge_price(int $rechargeId,int $price)
    {
        //事务操作。  充值成功   充值订单，支付成功写入充值列表，并写入用户金额,写入用户累计充值
        //充值订单，支付成功写入充值列表
        $data['sys_bank_id'] = '收款账号';
        $data['u_bank_name'] = '打款银行名';
        $data['u_bank_user_name'] = '打款用户名';
        $data['u_bank_card'] = '打款银行卡号';
        $data['success_time'] = date('Y-m-d H:i:s');


        $update =false;
        Db::startTrans();
        try {
            //写入充值订单  //写入用户累计充值
            (new PayRecharge())->where('id',$rechargeId)->save($data);
            //用户金额修改
            (new UserModel())->where('id',session('home_user.id'))
                ->save(['money_balance' => session('home_user.money_balance') + $price]);
            $update=true;
            // 提交事务
            Db::commit();
        } catch (\Exception $e) {
            // 回滚事务
            Db::rollback();
        }
        if ($update) return ['code' => 1, 'msg' => '支付成功','order_id'=>$rechargeId];

        $msg = '支付成功。写入充值订单信息失败，请联系管理员';
        Log::critical('订单ID：'.$rechargeId.':'.$msg);
        return ['code' => 1, 'msg' => $msg,'order_id'=>$rechargeId];
    }

    //充值订单写入
    public function recharge_user($admin, $price)
    {
        event('RepeatPurchase',['repeat'=>true,'id'=>$admin['id']]);
        $model = (new PayRecharge());
        $order = [
            'create_time' => date('Y-m-d H:i:s'),
            'money' => $price,
            //'admin_uid' => $admin['admin_uid'],
            'uid' => $admin['id'],
            'u_ip' => $_SERVER['REMOTE_ADDR'],
            'market_uid' => $admin['market_uid'],
            'u_city' => '',
            'sys_bank_id' => '',
            'u_bank_name' => '',
            'u_bank_user_name' => '',
            'u_bank_card' => '',
        ];

        $save = $model->save($order);
        if ($save) return ['code'=>1,'购买成功','recharge_id'=>$model->id];;
        return ['code'=>0,'msg'=>'订单生成失败'];
    }

    //购买套餐
    public function purchase_video_level()
    {
        // 触发重复购买事件
        event('RepeatPurchase',['repeat'=>true,'id'=>session('home_user.id')]);// 如果是 重复购买

        $id = Request::post('id', 0);   // 此处ID 为支付商品类型 ID 比如 包天 包片 包时间 这种 price_day
        $vid = Request::post('vid', null); // 对应的 视频ID
        $returnUrl = Request::post('return_url', ''); // 对应的 回调的 web 地址
        $channel_id = Request::post('channel_id/d', 0); // 对应的支付通道 ID

        //查看余额是否够
        $admin = session('home_user');  // 获取用户信息
        $agentid = $admin['agentid'];   // 获取代理ID
        // 获取代理 套餐配置 价格
        if ($agentid>0){
            $data_info = AdminModel::where('id',$agentid)
                ->field (['price_single_low','price_single_high','price_hour','price_day','price_week','price_month','price_quarter','price_year','price_forever'])
                ->find ()->toArray ();
        }else{
            // 读取总后台系统默认设置的 套餐价格
            $data_list = getSystemConfig ('reward_allocation');
            $data_info = json_decode  ($data_list,true);
        }
        // 如果 代理配置 与 总后台系统都没有获取当期 产品类型 提示不存在
        if (!isset($data_info[$id])) return ['code' => 0, 'msg' => '商品不存在'];

        $find = getPackageName($id); // 根据套餐 描述 获取 套餐类型名称
        $find['price_vip'] = $data_info[$id]; // 存入 vip 价格
        $find['video_price'] = $data_info[$id]; // 存入 单片价格
        $find['vid'] = $vid;

        //生成订单号。
        $code = orderCode();
        //购买成功后直接写入订单

        //添加订单
        $save = $this->order_user($id, $find, $code,$channel_id);
        if (!$save->id) return ['code' => 0, 'msg' => '订单写入失败'];//订单写入失败


        //余额够直接扣除
//        暂时没有余额  这里屏蔽掉
//        $money_balance = $admin['money_balance'] - $find['price_vip'];
//        if ($money_balance >= 0) {
//            $is_true = $this->user_price($admin, $find['price_vip'], $save->id);
//            if (!$is_true) return ['code' => 0, 'msg' => '余额扣除失败'];//购买失败
//        } else {
            //不够在发送到支付平台
            $order_code = [
                'name' => $find['title'],
                'price' => $find['price_vip'],//价格
                'order' => $code,
            ];
            //发送失败
            //if (false){}
            $result = PayIndex::submit ($channel_id,['pay_no'=>$code,'pay_price'=>$find['price_vip'],'describe_order'=>$find['title'],'return_url'=>$returnUrl,'order_id' =>$save->id]);
            $result['id'] = $save->id;
            $result['order_no'] = $code;
            return $result;
//        }

        //支付成功。修改订单状态.并写入到期时间到 video 订单表
        //修改订单状态
        $status = $save->where('id', $save->id)->save(['pay_status' => 1, 'pay_time' => date('Y-m-d H:i:s')]);

        if (!$status) {
            Log::emergency('订单号:'.$code . '：' . '购买成功，订单状态修改失败,联系管理员');
        }
        //增加到 视频订单表
        $vip = $this->order_vip($save->id, $find);
        if (!$vip) {
            Log::emergency('订单号:'.$code . '：' . '购买成功，视频订单表写入失败,联系管理员');
        }

        //分销奖励分配 小于0 表示是走支付平台来的购买套餐金额
        if ($money_balance < 0) {
            //现在是不管是充值还是购买都能得到分销奖励
            $BranchService = new BranchService();
            $money_log = $BranchService->branch($admin, ['price' => $find['price_vip'], 'order_id' => $save->id]);
            //if ($money_log['code'] == 0) return $money_log;

            // 代理商分润
            $AgentService = new AgentService();
            $agent_money_log = $AgentService->branch ($admin, ['price' => $find['price_vip'], 'order_id' => $save->id,'goods_id'=>$id,'vid'=>$vid]);
        }
        return ['code' => 1, 'msg' => '购买成功'];
    }
    //视频单独购买
    public function alone_video_purchase()
    {
        $id = Request::post('id/d',0);
        $channel_id = Request::post('channel_id/d', 0);
        $buy_price = Request::post('price/d', 0);
        $returnUrl = Request::post('return_url', '');

        if ($id == 0) return ['code'=>0,'ID错误'];
        //查询当前商品价格。并生成订单号。发送支付 到订单平台
        $find = (new Video())->find($id);
        if (!$find) return ['code' => 0, 'msg' => '商品不存在'];

        $agentid = session('home_user.agentid'); // 获取当前代理ID 
        $userID = session('home_user.id'); // 获取当前代理ID 
        $price_key = 'agentid'.$agentid.'videoid'.$id.'userid'.$userID;
        $price = Cache::store('redis')->get($price_key) ?? 0;

        if ($price <= 0 ){
            if($buy_price <= 0){
                return ['code' => 0, 'msg' => '商品价格不存在'.$price_key];
            }else{
                $price = $buy_price;
                Cache::store('redis')->set($price_key,$price);
            }
        } 
        $find->video_price = $price ;
        $find->confirm_price = $price ;
        //生成订单号
        $code = orderCode();
        //添加订单
        $save = $this->order_user($id, $find, $code,$channel_id);
        if (!$save->id) return ['code' => 0, 'msg' => '订单写入失败'];//订单写入失败

        // 执行支付 提交
        $result = PayIndex::submit ($channel_id,['pay_no'=>$code,'pay_price'=>$price,'describe_order'=>$find['title'],'return_url'=>$returnUrl,'order_id' =>$save->id]);
        $result['id'] = $save->id;
        $result['order_no'] = $code;
        return $result;

    }
    public function user_price($admin, $price, $order)
    {
        $save = false;
        //写入消费日志
        Db::startTrans();
        try {
            (new UserModel())->where('id', $admin['id'])->save(['money_balance' => $admin['money_balance'] - $price]);
            //写操作日志
            (new \app\common\model\MoneyLog())->insert([
                'create_time' => date('Y-m-d H:i:s'),
                'type' => 3,
                'status' => 101,
                'money_before' => $admin['money_balance'],
                'money_end' => $admin['money_balance'] - $price,
                'money' => $price,
                'uid' => $admin['id'],
                'market_uid' => $admin['market_uid'],
                'source_id' => $order,
                'mark' => '购买会员'
            ]);
            $save = true;
            Db::commit();
        } catch (ValidateException $e) {
            Db::rollback();
            // 验证失败 输出错误信息
            return $e->getError();

        }

        return $save;
    }

    //写入订单
    private function order_user($id, $find, $code,$pay_type)
    {
        //组装订单数据
        $order_user = [
            'uid'            => session ( 'home_user.id' ),
            'market_uid'     => session ( 'home_user.market_uid' ),
            'admin_uid'      => 0,
            'create_time'    => date ( 'Y-m-d H:i:s' ),
            'update_time'    => date ( 'Y-m-d H:i:s' ),
            'status'         => 0,
            'goods_id'       => is_numeric ($id)?$id:($find['vid']??0),
            'package_type'   => $id, // 购买套餐变化
            //'pay_time' => date('Y-m-d H:i:s'),
            'pay_status'     => 0,
            'pay_type'       => $pay_type,
            'channel_id'     => $pay_type,
            'pay_price'      => isset( $find[ 'price_vip' ] ) ? $find[ 'price_vip' ] : $find[ 'video_price' ],
            'pay_no'         => $code,
            'describe_order' => '购买商品' . $find[ 'title' ],
            'goods_info'     => json ( $find ),
            'pay_channel'    => '前台购买',
            'agent_uid'      => session ('home_user.agentid'), // 增加当前订单是哪个代理商的
            'ip'             => $_SERVER[ 'REMOTE_ADDR' ]
        ];
        $model = new OrderModel();
        $save = $model->save($order_user);
        if ($save) return $model;
        return false;
    }

    //订单成功 写入视频vip表
    private function order_vip($order_id, $find)
    {
        //写入vip订单信息
        $status = (new OrderVideo())->save([
            'uid'            => session ( 'home_user.id' ),
            'market_uid'     => session ( 'home_user.market_uid' ),
            'status'         => 1,
            'order_id'       => $order_id,
            'package_type'   => $find[ 'id' ],
            'vip_level'      => $find[ 'goods_id' ],
            'vip_start_time' => date ( 'Y-m-d H:i:s' ),
            'vip_end_time'   => date('Y-m-d H:i:s', time() + ($find['validity_time'] * 60)),//当前时间+可观看秒
        ]);
        if ($status) return true;
        return false;
    }

    //查看是否购买当前视频的权限
    public function alone_purchase($find)
    {
        //查看是否开通了会员
        $home_user = session('home_user');
        //查询用户购买的套餐

        $order_video = (new OrderVideo())->user_level($home_user['id']);
        //购买了套餐，查看是否该套餐是否具备查看该视频资格
        // $find->video_price 说明不是单片购买的视频
        if (!empty($order_video)){
//            $res = (new VideoToVip())->qualifications($order_video->vip_level,$find->id);
//            if ($res) {
                $find->is_purchase=1;
                return $find;
//            }//没有该套餐，并且该视频没有价格。表示可免费观看
//            elseif ($find->video_price == 0){
//                $find->is_purchase=1;
//                return $find;
//            }

        }

        //需要单独购买的视频
        if ($find->video_price >0 ) $find->is_purchase=2;

        //查看用户是否对当前视频单独购买了
        $alone_video = (new VideoBuyUserVideo())->alone_purchase($home_user['id'],$find->id);
        if ($alone_video) $find->is_purchase = 1;

        return $find;
    }

    /**
     * 首页分类视频列表
     * @return false
     */
    public function popular_movies()
    {
            // 获取分类
            $find = $this->model->field('id,pid,title as name1,ifnull(name,title) as title,thumb_url,0 as is_tag')
                ->where('is_show', 1)
                ->order('sort desc,id desc')
                ->select();
            if (empty($find)) return false;
            $find = $find->toArray();
            array_unshift($find,['id' => 0, 'pid' => 0, 'name1' => '精品', 'title' => '精品', 'thumb_url' => '', 'is_tag' => 0]);
            // 获取分类下设计的7个视频
            $video = new Video();
            foreach ($find as &$v) {
                if($v['id'] == 0){
                    // 版本 调整 之前的方案
                    // $data = $video->where('is_best', 1)->where('status', 1)->orderRaw('RAND()')->limit(7)->select()->each(function($item){
                    //     $item->play_num = $item->play_num + rand(3000, 20000);
                    //     $item->play_time = rand(1000,100000);
                    // });
                    // 版本调整
                    $data = $video->where('type', 9)->where('status', 1)->orderRaw('RAND()')->limit(7)->select()->each(function($item){
                        $item->play_num = $item->play_num + rand(3000, 20000);
                        $item->play_time = rand(1000,100000);
                        // 修改标题
                        $titleArray = explode('_',$item->title);
                        $title =  $titleArray[0];
                        $item->title = $title;
                    });
                }else{
                    $data = $video->where('type', $v['id'])->where('status', 1)->orderRaw('RAND()')->limit(7)->select()->each(function($item){
                        $item->play_num = $item->play_num + rand(3000, 20000);
                        $item->play_time = rand(1000,100000);
                        // 修改标题
                        $titleArray = explode('_',$item->title);
                        $title =  $titleArray[0];
                        $item->title = $title;
                    });
                }

                $v['datas'] = empty($data) ? [] : $data->toArray();
            }

            return $find;
    }

    /**
     * Convert MP4 to M3U8 format.
     * mp4 转 m3u8
     * @param string $inputFile Path to the input MP4 file.
     * @param string $outputDir Path to the output directory.
     * @return string|bool The path to the generated M3U8 file or false on failure.
     */
    public function convertMp4ToM3u8($videoId,$inputFile, $outputDir)
    {
        $name = uniqid();
        $name = 'videoid'.$videoId;
        if (!file_exists($inputFile)) {
            return false;
        }
        if (!is_dir($outputDir.'/'.$name)) {
            mkdir($outputDir.'/'.$name, 0777, true);
        }
        $fileName = $name. '/play.m3u8';
        $outputFile = $outputDir . '/' . $fileName;
        $command = "ffmpeg -i " . escapeshellarg($inputFile) . " -codec: copy -start_number 0 -hls_time 10 -hls_list_size 0 -f hls " . escapeshellarg($outputFile);
        exec($command, $output, $return_var);
        if ($return_var === 0) {
            return $fileName;
        } else {
            return false;
        }
    }

}
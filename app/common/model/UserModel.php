<?php


namespace app\common\model;


use app\common\traites\TraitModel;
use think\Model;

class UserModel extends Model
{
    use TraitModel;

    public $name = 'common_user';

//    public function getPwdAttr($value)
//    {
//        return '';
//    }

    public function getwithdrawPwdAttr($value)
    {
        return '';
    }
    
     public function vip()
    {
        return $this->hasOne(VideoUserLevel::class, 'uid', 'id');
    }

    public static function page_list($where, $limit, $page, $date = [])
    {
        $map = self::whereMapUser();
        $fields = 'a.id,a.user_name,a.nickname,a.phone,a.money_total_recharge,a.create_time,a.is_fictitious,a.pwd,a.status';
        $res = self::alias('a')->where($where)->where($map)
            ->where(function ($query) use ($date) {
                if (isset($date['start'])) {
                    $query->whereTime('a.create_time', '>=', $date['start']);
                }
                if (isset($date['end'])) {
                    $query->whereTime('a.create_time', '<=', $date['end']);
                }
            })
            ->field($fields)
            ->order('a.id desc');
        return $res->paginate(['list_rows' => $limit, 'page' => $page], false)
            ->each(function ($item, $key) {
                $item->tg_url_txt = tg_url().$item->invitation_code;
                $item->tg_url_google='';
                !empty($item->invitation_code) && $item->tg_url_google = captchaUrl($item->invitation_code);
                $lastRecharge = PayRecharge::getUserLastRecharge($item->id);
                $item->last_recharge_time = $lastRecharge ? $lastRecharge->create_time : '-';
                $item->vip_end_time = VideoUserLevel::getUserEndTime($item->id) ?: '-';
                $item->vip_start_time = VideoUserLevel::getUserStartTime($item->id) ?: '-';
                $item->pwd = pwdDecryption($item->pwd);
            });
    }
    //代理商个人信息
    public static function page_one($limit,$page)
    {
//        $map = self::whereMap();
        //if (empty($map)) return false;
        $map = [];
        if (session('admin_user.agent')){
            $map['a.id'] = session('admin_user.id');
        }else{
            $map['a.type'] =1;
        }

        return self::alias('a')
            ->where($map)
            ->join('common_admin b', 'a.market_uid = b.id', 'left')
            ->field('a.*,b.user_name admin')
            ->paginate(['list_rows' => $limit, 'page' => $page], false)
            ->each(function ($item, $key) {
                $item->tg_url_txt = $_SERVER['REQUEST_SCHEME'] . '://' . randomkey(5) .'.'. config('ToConfig.app_tg.tg_url') . '?code=' . $item->invitation_code;
            });
    }
    //直接删除
    public function del($id)
    {
        $find = $this->find($id);
        if (empty($find))
            return false;
        return $find->delete();
    }
}
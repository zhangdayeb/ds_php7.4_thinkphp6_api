<?php


namespace app\common\model;


use app\common\traites\TraitModel;
use think\Model;

class OrderModel extends Model
{
    use TraitModel;
    protected $json = ['agent_remark','pay_remark'];
    protected $jsonAssoc = true;

    CONST PLACE_AN_ORDER = 0; //已下单 与下面 $status对应
    CONST DELIVER_GOODS = 3; //已发货 与下面 $status对应
    CONST SIGN_FOR = 6; //已签收 与下面 $status对应

    CONST PAY_NOT = 0;  // 未支付  与下面 $pay_status
    CONST PAY_OK = 1;  // 已支付  与下面 $pay_status

    public $name = 'common_order';

//401 分销奖励 403充值分销 404忽略订单
    public $status = [
        0 => '已下单', 3 => '已发货', 6 => '已签收',401=>'分销奖励',403=>'充值分销',404 => '忽略订单',501 => '代理商分润奖励',504 => '代理商忽略订单'
    ];

    public $paystatus = [
        '未支付', '已支付'
    ];

    protected $append = [
        'status_text',
        'pay_status_str',
    ];

    public function getPayStatusStrAttr($value, $data)
    {
        $value = $value ?: (isset($data['pay_status']) ? $data['pay_status'] : '');
        return isset($this->paystatus[$value]) ? $this->paystatus[$value] : $value;
    }

    public function getStatusTextAttr($value, $data)
    {
        $value = $value ? $value : (isset($data['status']) ? $data['status'] : '');
        $list = $this->status;
        return isset($list[$value]) ? $list[$value] : '';
    }

    public static function page_list($where, $limit, $page)
    {
        $map=self::whereMap();
        if (session('admin_user.agent')) $where[]= ['a.status', '<', 404];
        return self::alias('a')
            ->where($where)
            ->where($map)
            ->join('common_admin b', 'a.agent_uid = b.id', 'left')
            ->join('common_admin c', 'a.admin_uid = c.id', 'left')
            ->join('common_user d', 'a.uid = d.id', 'left')
            ->join('common_channel e', 'a.channel_id = e.id', 'left')
            ->field('a.*,b.user_name as agent_name,c.user_name market_name,d.user_name,e.channel_name as channel_name_en, e.pay_channel as channel_name')
            ->order('id desc')->paginate(['list_rows' => $limit, 'page' => $page])
            ->each(function ($item, $key) {
                $item->agent_uname = getUnameById($item->agent_uid);
            });
    }

    public function get_user_order_list($where = [], $limit = 10, $page = 1, $fields = '*')
    {
        return self::where($where)
            ->field($fields)
            ->order('id desc')->paginate(['list_rows' => $limit, 'page' => $page]);
    }
}
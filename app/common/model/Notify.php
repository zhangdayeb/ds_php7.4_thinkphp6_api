<?php


namespace app\common\model;


use app\common\traites\TraitModel;
use think\Model;

class Notify extends Model
{
    use TraitModel;

    public $name = 'common_notify';
    public $notifys = [1 => '全体', 2 => '私人'];

    public static function page_list($where, $limit, $page)
    {
        $model = new AdminModel();
        return self::alias('a')
            ->where($where)
            ->order('id desc')
            ->paginate(['list_rows' => $limit, 'page' => $page])
            ->each(function ($item, $key) use ($model) {
                if (empty($item->unique)) return '';
                //非空时进行分割
//               $model = new UserModel();
//                $nickname =$model->whereIn('id',$item->unique)->column('nickname');
//                if (empty($nickname)) return '';
//                $nickname= implode(',',$nickname);
//                if (empty($nickname)) return '';
                $nickname = $model->whereIn('id', $item->unique)->column('user_name');
                if (empty($nickname)) return '';
                $nickname = implode(',', $nickname);
                $item->nickname = $nickname;
            });
    }

    public static function agent_user_list($agentUid, $page = 1, $limit = 10)
    {
        return self::where('type', 1)
            ->where('status', 1)
            ->whereOr(function ($query) use ($agentUid) {
                $query->where('type', 2)->whereRaw("FIND_IN_SET({$agentUid}, `unique`) > 0");
            })->order('id desc')
            ->paginate(['list_rows' => $limit, 'page' => $page]);
    }
}
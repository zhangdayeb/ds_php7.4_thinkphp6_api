<?php


namespace app\common\model;


use app\common\traites\TraitModel;
use think\Model;

class RealName extends Model
{
    use TraitModel;
    public $name = 'common_user_real_name';

    public function user()
    {
        return $this->hasOne(UserModel::class, 'id', 'u_id');
    }
}
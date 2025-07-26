<?php


namespace app\common\model;


use app\common\traites\TraitModel;
use think\Model;

class IpConfig extends Model
{
    use TraitModel;
    public $name = 'common_sys_ip_config';
}
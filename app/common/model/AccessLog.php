<?php


namespace app\common\model;


use app\common\traites\TraitModel;
use think\Model;

class AccessLog extends Model
{
    use TraitModel;
    public $name = 'common_access_log';
}
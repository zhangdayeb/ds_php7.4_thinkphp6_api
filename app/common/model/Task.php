<?php

namespace app\common\model;

use app\common\traites\TraitModel;
use think\Model;

class Task extends Model
{
    use TraitModel;

    public $name = 'task';

    public $append = [
        'status_text'
    ];

    public function statusList()
    {
        // 状态:created=创建,running=运行中,finished=完成,fail=失败
        $lists = [
            'created' => '创建',
            'running' => '运行中',
            'finished' => '完成',
            'fail' => '失败',
        ];
        return $lists;
    }

    public function getStatusTextAttr($value, $data)
    {
        $lists = $this->statusList();
        $value = $value ?: $data['status'];
        return $lists[$value];
    }

    public function getBackTimeAttr($value)
    {
        return $value ? date('Y-m-d H:i:s', $value) : '';
    }

//    public function getContentAttr($value, $data)
//    {
//        return $value ? explode(',', $value) : [];
//    }
}
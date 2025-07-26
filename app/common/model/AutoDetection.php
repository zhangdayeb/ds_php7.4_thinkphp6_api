<?php

namespace app\common\model;

use think\Model;

class AutoDetection extends Model
{
    public $name = 'auto_detection';

    protected $autoWriteTimestamp = true;

    protected $append = [
        'status_text'
    ];

    public function getJiqiangResponseAttr($value)
    {
        return $value ? json_decode($value, true) : [];
    }

    public function getAbnormalMemoAttr($value)
    {
        return $value ? explode(PHP_EOL, trim($value)) : [];
    }

    public function getQilinAbnormalMemoAttr($value)
    {
        return $value ? explode(PHP_EOL, trim($value)) : [];
    }

    public function getQilinResponseAttr($value)
    {
        return $value ? json_decode($value, true) : [];
    }

    public function getStatusList()
    {
        return [0 => '风险', 1 => '正常'];
    }
    public function getStatusTextAttr($value, $data)
    {
        $list = $this->getStatusList();
        return isset($data['status']) ? (isset($list[$data['status']]) ? $list[$data['status']] : '') : '';
    }
}
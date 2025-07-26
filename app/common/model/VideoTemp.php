<?php

namespace app\common\model;

use app\common\traites\TraitModel;
use think\Model;

class VideoTemp extends Model
{
    use TraitModel;
    public $name = 'video_temp';

    public function getPathAttr($value)
    {
        $path = root_path() . 'public/storage';
        return $value ? str_replace($path, '', $value) : '';
    }

    public function getImageAttr($value)
    {
        $path = root_path() . 'public/storage';
        return $value ? str_replace($path, '', $value) : '';
    }
}
<?php

namespace app\common\model;

use think\Model;

class VideoOssKey extends Model
{
    public $name = 'video_oss_key';

    protected $autoWriteTimestamp = true;
}
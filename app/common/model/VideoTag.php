<?php

namespace app\common\model;

use app\common\traites\TraitModel;
use think\Model;

class VideoTag extends Model
{
    use TraitModel;
    public $name = 'video_tag';

    protected $autoWriteTimestamp = true;
}
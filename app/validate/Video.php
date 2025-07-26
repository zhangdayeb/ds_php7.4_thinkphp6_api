<?php
declare (strict_types = 1);

namespace app\validate;

use think\Validate;

class Video extends Validate
{
    /**
     * 定义验证规则
     * 格式：'字段名' =>  ['规则1','规则2'...]
     *
     * @var array
     */
    protected $rule = [
        'id'=>  'require|integer',
        'type'=>  'require|integer',
        'des'=>'max:200',
        'title'=>'require|max:200',
        'url'=>'',
        'sort'=>'integer',
        'status'=>'integer',
        'description'=>'max:255',
        'video_price'=>'float',
        'is_pop'=>'integer|in:0,1',
        'is_best'=>'integer|in:0,1',
        'tags'=>'max:255',
    ];

    /**
     * 定义错误信息
     * 格式：'字段名.规则名' =>  '错误信息'
     *
     * @var array
     */
    protected $message  =   [
        'id.require' => 'ID必填',
        'id.integer' => 'ID必须是整数',
        'type.require' => '分类必填',
        'type.integer' => '分类ID必须是整数',
        'title.require' => '标题必填',
        'title.max' => '标题最多200字',
        'des.max' => '介绍内容最多200字',
        'sort.integer' => '排序必须是整数',
        'status.integer' => '排序必须是整数',
        'is_pop.integer' => '是否弹窗视频必须是整数',
        'is_pop.in' => '是否弹窗视频只能是0或1',
        'is_best.integer' => '是否精选是整数',
        'is_best.in' => '是否精选只能是0或1',
        'tags.max' => '标签最多255字',
    ];

    /**
     * 验证场景
     * @var \string[][]
     */
    protected $scene  = [
        'add'=>['type','title','des','sort','status','description','video_price','is_pop', 'tags', 'is_best'],
        'edit'=>['id','type','title','des','sort','status','description','video_price','is_pop', 'tags', 'is_best'],
        'detail'=>['id'],

    ];

}

<?php
declare (strict_types = 1);

namespace app\validate;

use think\Validate;

class VideoVipLevel extends Validate
{
    /**
     * 定义验证规则
     * 格式：'字段名' =>  ['规则1','规则2'...]
     *
     * @var array
     */
    protected $rule = [
        'id'=>  'require|integer',
        'price_single'=>  'require|float',
        'price_vip'=>  'require|float',
        'validity_time'=>  'require|integer',
        'status'=>  'require|integer',
        'title'=>'require|max:200',
        'types'=>'require',
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
        'title.require' => '标题必填',
        'title.max' => '标题最多200字',

    ];

    /**
     * 验证场景
     * @var \string[][]
     */
    protected $scene  = [
        'add'=>['price_single','price_vip','validity_time','status','title'],
        'edit'=>['id','price_single','price_vip','validity_time','status','title'],
        'type'=>['types','id'],
        'detail'=>['id'],

    ];

}

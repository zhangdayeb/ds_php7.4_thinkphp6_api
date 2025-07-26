<?php

namespace app\validate;

use app\common\model\WebcardModel;
use think\Validate;

class WebcardValidate extends Validate
{
    protected $rule = [
        'id'      => 'require',
        'title'   => 'require|length:1,30|unique:' . WebcardModel::class,
        'img_url' => 'require',
        'url'     => 'require',
        'remarks' => 'length:1,255',
    ];


    protected $message = [
        'id.require'      => '参数缺失',
        'title.require'   => '请填写名称',
        'title.length'    => '名称长度需为1~30个字符',
        'title.unique'    => '名称已存在',
        'url.require'     => '请填写地址',
        'img_url.require' => '请上传图片地址',
        'remarks.length'  => '备注长度需为1~255个字符',
    ];
    /**
     * 场景
     * @var array[]
     */
    protected $scene = [
        'add'       => [ 'title','url','img_url','remarks' ],
        'edit'      => [ 'id','title','url','img_url','remarks' ],
    ];
}
<?php


namespace app\common\model;


use app\common\traites\TraitModel;
use think\Model;

class ArticleType extends Model
{
    use TraitModel;
    public $name = 'common_article_type';
}
<?php


namespace app\common\traites;


trait TraitModel
{
    /**
     * 直接删除
     * @param $id /主键
     * @return bool
     */
    public function del($id)
    {
        $find = $this->find($id);
        if (empty($find)) return false;
        return $find->delete();
    }
    /**
     * 添加数据
     * @param $data /数据数组
     * @param bool $type /true 单条添加,false多条添加
     */
    public function add(array $data,bool $type=true)
    {
        //单条添加
        if ($type){
           return $this->insert($data);
        }
        //多条添加
        return $this->insertAll($data);
    }

    //软删除
    public function deletes($id)
    {
        return '';
    }

    public function setStatus($post)
    {
        $id = intval($post['id']);
        $status = intval($post['status']);
        //$status = $post['status'] == 1 ? 0 : 1;

        if ($id < 1) return false;
        $find = $this->find($id);
        return $find->save(['status' => $status]);
    }

    public function getThumbUrlAttr($value)
    {
        if (empty($value)) return '';
        if (is_array($value)) return '';
        $value = explode(',', $value);
        if (count($value) > 1) {
            foreach ($value as $key => $v) {
                $value[$key] = config('ToConfig.app_update.image_url') . $v;
            }
            return $value;
        }
        return config('ToConfig.app_update.image_url') .'/'. $value[0];
    }

    public function getVideoUrlAttr($value)
    {
        if (!empty($value)) return config('ToConfig.app_update.image_url').'/' . $value;
        return '';
    }

    //代理商查看代理商推广的用户充值等 不排除自己
    public static function whereMap()
    {
        $map = [];
        //代理商 推广列表
        if (session('admin_user.agent')) $map = ['agent_id_1|agent_id_2|agent_id_3|b.id' => session('admin_user.id')];
        return $map;
    }
    //代理商查看用户代理。排除自己
    public static function whereMapUser()
    {
        $map = [];
        //代理商 推广列表
        if (session('admin_user.agent')) $map = ['agent_id_1|agent_id_2|agent_id_3' => session('admin_user.id')];
        return $map;
    }
}
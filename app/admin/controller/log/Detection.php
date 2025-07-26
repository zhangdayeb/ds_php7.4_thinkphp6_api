<?php

namespace app\admin\controller\log;

use app\admin\controller\Base;
use app\common\model\AutoDetection;

class Detection extends Base
{
    public function autoDetectionList()
    {
        //当前页
        $page = $this->request->post('page', 1);
        //每页显示数量
        $limit = $this->request->post('limit', 10);

        $fields = 'id,url_jiance,url_obj_id,url_laiyuan,status,abnormal_memo,jiqiang_response,qilin_abnormal_memo,qilin_response,create_time,update_time,jiqiang_time,qilin_time,open_status';
        $list = (new AutoDetection())->order('id asc')->field($fields)->paginate(['list_rows'=>$limit,'page'=>$page]);

        return $this->success($list);
    }
}
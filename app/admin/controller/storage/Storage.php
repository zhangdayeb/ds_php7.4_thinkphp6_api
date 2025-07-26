<?php

namespace app\admin\controller\storage;

use app\admin\controller\Base;
use app\common\model\AdminModel as models;
use app\common\service\OssService;
use app\common\service\TaskService;
use app\common\service\VideoTempService;
use app\common\traites\PublicCrudTrait;
use think\Exception;

class Storage extends Base
{
    protected $model;
    use PublicCrudTrait;

    public function initialize()
    {
        $this->model = new models();
        parent::initialize();
    }

    /**
     * 列出文件列表，目录结构
     * @return mixed|null
     * @throws \think\Exception
     */
    public function pullStorage()
    {
        $service = new OssService();
        $files = $service->getOssProjectsFiles();
        if (! empty($files)) {
            return $this->success($files);
        }
        return $this->failed('获取失败');
    }

    public function localStorage()
    {
        $dir = $this->request->post('dir');
        $service = new OssService();
        $files = $service->getLocalProjectsFiles($dir);
        if (! empty($files)) {
            return $this->success($files);
        }
        return $this->failed('获取失败');
    }

    /**
     * 同步目录
     * @return mixed|null
     */
    public function sysDir()
    {
        $prefix = $this->request->post('prefix');
        $service = new OssService();
        $list = $service->sysDirectory($prefix);
        if (! empty($list)) {
            return $this->success($list);
        }
        return $this->failed('同步失败');
    }

    /**
     * 创建下载任务
     * @return mixed|null
     */
    public function downloadTask()
    {
        $fileList = $this->request->post('file_list', '');
        if ($fileList == '' || !$fileList) {
            return $this->failed('请选择下载文件');
        }
        $adminId = session('admin_user.id');
        $service = new OssService();
        $task = $service->createDownloadTask($fileList, $adminId);
        if (! empty($task)) {
            return $this->success($task);
        }
        return $this->failed('创建任务失败');
    }

    /**
     * 初始化文件列表从阿里云
     * @return mixed|null
     * @throws \think\Exception
     */
    public function downloadInitTask()
    {
        $adminId = session('admin_user.id');
        $service = new OssService();
        $task = $service->createDownloadInitTask($adminId);
        if (! empty($task)) {
            return $this->success($task);
        }
        return $this->failed('创建任务失败');
    }

    /**
     * @throws Exception
     */
    public function scanInTemp()
    {
        $service = new VideoTempService();
        try {
            $scan = $service->scanDirInVideoTemp();
        } catch (Exception $exception) {
            return $this->failed('入库失败' . $exception->getMessage());
        }
        return $this->success(['create_video_temp' => $scan]);
    }

    /**
     * 一键清空所有
     * @return mixed
     */
    public function clearAll()
    {
        $service = new VideoTempService();
        $res = $service->actionClearAll();
        return $this->success(['bool' => $res]);
    }

    public function taskList()
    {
        $service = new TaskService();
        $list = $service->getTaskList(session('admin_user.id'));
        return $this->success($list);
    }

    public function syncUpdate()
    {
        $ids = $this->request->post('ids');
        $service = new VideoTempService();
        $adminId = session('admin_user.id');
        $res = $service->syncUpdate($ids, $adminId);
        if ($res) {
            return $this->success(['sys' => $res]);
        } else {
            return $this->failed('同步失败');
        }
    }

    public function syncUpdateAll()
    {
        $service = new VideoTempService();
        $adminId = session('admin_user.id');
        $res = $service->syncUpdateAll($adminId);
        if ($res) {
            return $this->success(['sys' => $res]);
        } else {
            return $this->failed('同步失败');
        }
    }
    public function videoTempList()
    {
        $page = $this->request->post('page/d', 1);
        $limit = $this->request->post('limit/d', 10);
        $service = new VideoTempService();
        $where = [];
        $list = $service->getList($page, $limit, $where);
        if ($list) {
            return $this->success($list);
        } else {
            return $this->failed('获取失败或暂无数据');
        }
    }

    public function retask()
    {
        $taskId = $this->request->post('task_id', '');
        if ($taskId == '') {
            return $this->failed('请输入task_id');
        }
        $service = new TaskService();
        $res = $service->reBuildTask($taskId);
        return $this->success($res);
    }


    public function testPush()
    {
        $taskId = $this->request->post('task_id', '');
        $res = (new OssService())->runningOssDownloadTaskByOssutil($taskId);
        return $this->success($res);
    }

    public function compare()
    {
        $service = new OssService();
        $res = $service->getCompareInfo();
        return $this->success($res);
    }

    public function compareAction()
    {
        $adminId = session('admin_user.id');
        $service = new OssService();
        $result = $service->compareOss($adminId);
        return $this->success($result);
    }
}
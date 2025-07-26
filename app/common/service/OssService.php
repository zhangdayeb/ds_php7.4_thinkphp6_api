<?php

namespace app\common\service;

use app\common\model\Task;
use app\common\model\Video;
use app\common\model\VideoOssKey;
use OSS\Core\OssException;
use OSS\Credentials\EnvironmentVariableCredentialsProvider;
use OSS\OssClient;
use think\Exception;

class OssService
{
    protected $accessKeyId;
    protected $accessKeySecret;
    protected $endpoint;
    protected $bucket;

    // 系统构造函数
    /**
     * Summary of __construct
     */
    public function __construct()
    {
        $this->accessKeyId = config('oss.accessKeyId');
        $this->accessKeySecret = config('oss.accessKeySecret');
        $this->endpoint = config('oss.endpoint');
        $this->bucket = config('oss.bucket')['default'];
    }

    /**
     * 获取oss 文件列表
     * 获取 oss 文件目录 
     * 对应的视频源 视频源图片 
     * @throws \think\Exception
     * @return array
     */
    public function getOssProjectsFiles()
    {
        // 创建OSSClient实例
        try {
            $ossClient = new OssClient($this->accessKeyId, $this->accessKeySecret, $this->endpoint);
        } catch (OssException $e) {
            throw new Exception($e->getMessage());
        }
        // 列举文件。如果不设置Prefix，则列举根目录下的所有文件。如果设置Prefix，则列举包含指定前缀的文件。
        $delimiter = "/"; // 设置分隔符为根目录分隔符
        $nextMarker = ""; // 列举时的起始标记
        $maxkeys = 1000; // 每次列举的最大数量
        $options = array(
            "delimiter" => $delimiter,
            "prefix" => "",
            "max-keys" => $maxkeys,
            "marker" => $nextMarker
        );
        try {
            $listObjectInfo = $ossClient->listObjectsV2($this->bucket, $options);
            $allPrefix = $listObjectInfo->getPrefixList();
            $prefixs = [];
            foreach ($allPrefix as $prefixInfo) {
                $prefixs[] = trim(str_replace($options['prefix'], '', $prefixInfo->getPrefix()), '/');
            }
            $ossFiles = $this->listAllFiles($ossClient, $this->bucket, $options['prefix']);
        } catch (OssException $e) {
            throw new Exception($e->getMessage());
        }
        return [
            'prefix' => $prefixs,
            'files' => $ossFiles
        ];
    }

    /**
     * 当下载任务挂掉后，重启下载任务 重新发布队列
     * @throws \think\Exception
     * @return void
     */
    public function reStartTaskWhenDownFail(){
        $taskList = [];
        $taskModel = new Task();
        $taskListMySql = $taskModel->where('status','<>','finished')->select();
        foreach ($taskListMySql as $key => $item) {
            $taskList[] = $item->id;
        }

        if (!empty($taskList)) {
            // 投递消息
            $rabbitConf = config('rabbitmq.rabbitmq');
            $rabbitMQService = new RabbitMQService($rabbitConf);
            try {
                $message = join(',', $taskList);
                $rabbitMQService->publish('oss_download', $rabbitConf['queue'], $message);
            } catch (\Exception $exception) {
                throw new Exception($exception->getMessage());
            }
        }

        echo "重新启动未完成的下载" . json_encode($taskList) . PHP_EOL;
    }
    /**
     * 读取本地文件 
     * @param mixed $dir
     * @return array
     */
    public function getLocalProjectsFiles($dir = '')
    {
        $path = root_path() . 'public/storage/videotemp';
        $dir = $dir ? $path . '/' . $dir: $path;
        $localDir = scandir($dir);
        // 过滤掉 '.' 和 '..'
        $localDir = array_filter($localDir, function($file) {
            return $file !== '.' && $file !== '..';
        });
        $localDir = array_values($localDir);
        $localDirFiles = $this->recursiveScandir($path);
        $replace = root_path() . 'public/storage/';

        foreach ($localDirFiles as $key => $file) {
            $item = str_replace($replace, '', $file);
            $localDirFiles[$key] = $item;
        }
        return [
            'prefix' => $localDir,
            'files' => $localDirFiles
        ];
    }

    /**
     * 同步 OSS 目录 
     * @param $prefix
     * @return array
     */
    public function sysDirectory($prefix)
    {
        $ossPrefix = explode(',', $prefix);
        $path  = root_path() . '/public/storage/videotemp';
        $localDir = scandir($path);
        // 过滤掉 '.' 和 '..'
        $localDir = array_filter($localDir, function($file) {
            return $file !== '.' && $file !== '..';
        });
        // 需要同步的目录
        $needsys = [];
        foreach ($ossPrefix as $predir) {
            if (!in_array($predir, $localDir)) {
                $needsys[] = $predir;
            }
        }
        //创建 needsys中的目录
        foreach ($needsys as $mkpath) {
            if (!is_dir($path . '/' . $mkpath)) {
                mkdir($path . '/' . $mkpath, 0777, true);
            }
        }
        return $needsys;
    }

    /**
     * 创建下载任务  
     * 这个是 所有文件列表 创建一个 下载任务
     * @param mixed $fileList
     * @param mixed $adminId
     * @throws \think\Exception
     * @return array
     */
    public function createDownloadTask($fileList, $adminId)
    {
        $fileListArr = explode(',', $fileList);
        $totalFiles = count($fileListArr);
        $taskList = [];
        // 文件数量分片
        $taskSnPrefix = 'oss_download_' . uniqid();
        $taskSnList = [];
        $chunkArr = array_chunk($fileListArr, 2); // 数组重新分片 2个 图片为一个任务
        foreach ($chunkArr as $key => $itemArr) {
            // 创建任务记录
            $taskSn = $taskSnPrefix . '_' . $key;
            $task = [
                'task_sn' => $taskSn,
                'task_type' => 1,
                'remark' => 'OSS下载文件列表' . $key . '-' . count($itemArr) . '/' . $totalFiles,
                'total' => count($itemArr),
                'ack' => 0,
                'admin_id' => $adminId,
                'content' => join(',', $itemArr)
            ];
            $model = new Task();
            $model->save($task);
            $taskList[] = $model->id;
            $taskSnList[] = $taskSn;
        }
        if (!empty($taskSnList)) {
            // 投递消息
            $rabbitConf = config('rabbitmq.rabbitmq');
            $rabbitMQService = new RabbitMQService($rabbitConf);
            try {
                $message = join(',', $taskList);
                $rabbitMQService->publish('oss_download', $rabbitConf['queue'], $message);
            } catch (\Exception $exception) {
                throw new Exception($exception->getMessage());
            }
        }

        return [
            'taskSn' => $taskSnList,
            'taskId' => $taskList
        ];
    }

    /**
     * 创建初始化下载任务
     * @param mixed $adminId
     * @throws \think\Exception
     * @return array
     */
    public function createDownloadInitTask($adminId)
    {
        $taskSn = 'oss_download_' . uniqid() . '_init';
        $model = new Task();
        $task = [
            'task_sn' => $taskSn,
            'task_type' => 1,
            'remark' => '初始化中',
            'total' => 1,
            'ack' => 0,
            'admin_id' => $adminId,
            'content' => 'init'
        ];
        $model->save($task);
        $taskId = $model->id;
        if ($taskId) {
            // 投递消息
            $rabbitConf = config('rabbitmq.rabbitmq');
            $rabbitMQService = new RabbitMQService($rabbitConf);
            try {
                $rabbitMQService->publish('oss_download', $rabbitConf['queue'], $taskId);
            } catch (\Exception $exception) {
                throw new Exception($exception->getMessage());
            }
        }
        return [
            'taskSn' => $taskSn,
            'taskId' => $taskId
        ];
    }



    /**
     * Summary of testPush
     * @param mixed $taskSn
     * @return array
     */
    // public function testPush($taskSn)
    // {
    //     // 投递消息
    //     // $ossfile = '视频源/电影/分类logo/电影_默认.png';
    //     // $localfile = root_path() . 'public/storage/videotemp/' . str_replace('视频源/', '', $ossfile);
    //     // $res = $this->downloadFromOss($ossfile, $localfile);    // 从oss 下载到本地 
    //     // $videoFilePath = '/Users/devindun/workplace/code/video_dashang_api_thinkphp6/public/storage/videotemp/动漫/4K天然水资源物产丰富-src_hd_爱给网_aigei_com.mp4';
    //     // $filePath = '/Users/devindun/workplace/code/video_dashang_api_thinkphp6/public/storage/videotemp/电影/霸道总裁爱上我#大陆#悬疑#爱情.mp4';
    //     // $ext = (new VideoTempService())->getExtension($filePath); // 获取文件 后缀
    //     // dd($ext);
    //     (new VideoTempService())->taskToVideoTemp($taskSn);
    //     return [];
    // }

    /**
     * 执行 oss Task 里面的下载任务
     * @param mixed $message
     * @return bool
     */
    public function actionOssDownloadTask($message)
    {
        $ids = explode(',', $message);
        foreach ($ids as $id) {
            // OSS 文件下载API
            $this->runningOssDownloadTask($id);
            // OSS 文件同步工具 ossutil | 暂时 放弃 不记得为啥了 
            // $this->runningOssDownloadTaskByOssutil($id);
        }
        return true;
    }

    /**
     * 执行文件下载任务 
     * @param mixed $taskId
     * @throws \think\Exception
     * @return bool
     */
    protected function runningOssDownloadTask($taskId)
    {
        echo "启动任务下载 ID:".$taskId. PHP_EOL;
        $taskInfo = Task::where('id', $taskId)->find(); // 获取任务详情
        $taskInfo->status = 'running'; // 设置运行状态
        $taskInfo->save(); // 存储运行状态
        $fileList = explode(',', $taskInfo['content']);// 获取本次任务需要下载的文件列表
        if (empty($fileList)) {
            throw new Exception('文件列表不能为空');
        }
        $taskInfo->ack = 0; // 初始化 任务完成量 
        // 遍历任务列表 
        foreach ($fileList as $file) {
            echo "执行下载文件为：".$file. PHP_EOL;
            $newFileName = strpos($file, '#') ? str_replace('#', '_', $file) : $file; // 新文件名
            $localFilePath = root_path() . 'public/storage/videotemp/' . $newFileName; // 创建本地文件路径
            $this->ensureDirectoryExists($localFilePath);   // 确保本地文件目录存在
            try {
                // 检查是否已经下载
                $checkDownload = $this->checkFileIsDownload($file);
                if ($checkDownload === false) {
                    // 没有下载的情况下再下载
                    $this->downloadFromOss($file, $localFilePath);
                }
                $taskInfo->ack++;
                if ($taskInfo->ack >= $taskInfo->total) {
                    $taskInfo->status = 'finished';
                    $taskInfo->back_time = time();
                }
                $taskInfo->save();
            } catch (Exception $e) {
                $taskInfo->status = 'fail';
                $taskInfo->back_time = time();
                $taskInfo->remark = $e->getMessage();
                $taskInfo->save();
                break;
            }
            echo "下载完成：".$file. PHP_EOL;
        }
        echo "执行完成 任务ID:".$taskId. PHP_EOL;
        // 当前任务入库临时表
        $taskStatus = Task::where('id', $taskId)->value('status');
        if ($taskStatus == 'finished') {
            (new VideoTempService())->taskToVideoTemp($taskInfo['task_sn']);
        }
        echo "如果是视频 完成入口任务 任务ID:".$taskId. PHP_EOL;
        // 打印分隔符
        echo PHP_EOL. PHP_EOL. PHP_EOL. PHP_EOL. PHP_EOL. PHP_EOL;
        return true;
    }


//    public function runningOssDownloadTaskByOssutil($taskId)
//    {
//        $ossutilPath = '/Users/devindun/ossutil-cli/ossutil';
//        $configPath = '/Users/devindun/.ossutilconfig';
//        $localDir = root_path(). 'public/storage/videotemp/';
//        $taskFileList = Task::where('id', $taskId)->value('content');
//        $res = [];
//        $taskFileList = explode(',', $taskFileList);
//        foreach ($taskFileList as $file) {
//            $fileDirName = trim(dirname($file), '/');
//            if (! is_dir($localDir . $fileDirName)) {
//                mkdir($localDir . $fileDirName, 0777, true);
//            }
//            $fileDownDir = $localDir . $fileDirName;
//            $command = <<<EOL
//{$ossutilPath} cp oss://develop-test-dh/{$file} {$fileDownDir} -c {$configPath}
//EOL;
//            exec($command, $output, $return_var);
//
//// 检查命令是否执行成功
//            if ($return_var === 0) {
//                // 同步成功，可以进行其他自定义操作
//                // ...
////                echo '同步成功';
////                echo PHP_EOL;
//                $res[] = ['success', $return_var, $output];
//            } else {
//                // 同步失败，处理错误
////                echo '同步失败';
////                echo PHP_EOL;
//                $res[] = ['fail', $return_var, $output];
//            }
//        }
//        return $res;
//    }

    /**
     * 下载文件 具体执行的函数
     * @param $ossClient
     * @param $bucket
     * @param $ossFilePath
     * @param $localFilePath
     * @return bool
     * @throws Exception
     */
    public function downloadFromOss($ossFilePath, $localFilePath)
    {
        echo "准备下载oss文件：".$ossFilePath. PHP_EOL;
        if (file_exists($localFilePath)) {
            echo "文件已经存在： ".$ossFilePath. PHP_EOL;

            $ossKeyModel = new VideoOssKey();
            $data = [];
            $data['key'] = $ossFilePath;
            $data['video_temp_id'] = '888888';
            $data['create_time'] = time();
            $data['update_time'] = time();
            $sql = $ossKeyModel->fetchSql(true)->save($data);
            // echo ($sql);
            $ossKeyModel->save($data);

            return true;
        }
        // 创建OSSClient实例
        try {
            $ossClient = new OssClient($this->accessKeyId, $this->accessKeySecret, $this->endpoint);
        } catch (OssException $e) {
            throw new Exception($e->getMessage());
        }
        $options = array(
            OssClient::OSS_FILE_DOWNLOAD => $localFilePath
        );
        try {
            echo "执行重新下载： ".$ossFilePath. PHP_EOL;
            // 从OSS下载文件
            $ossClient->getObject($this->bucket, $ossFilePath, $options);
            // 下载完成 存入 已经完成的 key 表里面
            $ossKeyModel = new VideoOssKey();
            $data = [];
            $data['key'] = $ossFilePath;
            $data['video_temp_id'] = '99999';
            $data['create_time'] = time();
            $data['update_time'] = time();
            $sql = $ossKeyModel->fetchSql(true)->save($data);
            // echo ($sql);
            $ossKeyModel->save($data);

            // 防止太快
            $sleep = rand(10,30);
            echo "下载完成 休息 ".$sleep. '秒'. PHP_EOL;
            sleep($sleep);
        } catch (OssException $e) {
            throw new Exception("Failed to download file: %s\n" . $e->getMessage());
        }
        return true;
    }

    /**
     * 检测文件是否下载过
     * @param mixed $ossFilePath
     * @return bool
     */
    public function checkFileIsDownload($ossFilePath)
    {
        $model = new VideoOssKey();
        $hasFile = $model->where('key', $ossFilePath)->find();
        return $hasFile ? true : false;
    }
    /**
     * 线上文件 与 线下文件 进行 对比
     * 判断 那些需要增加 那些需要删除
     * 只是执行对比 获取差距 不执行任何操作
     * @return array
     */
    public function getCompareInfo()
    {
        $result = [];
        // 开始对比 OSS 视频源数据内容
        $ossFiles = $this->getOssProjectsFiles();
        $videoOssKeyModel = new VideoOssKey();
        $localFiles = $videoOssKeyModel->field('id,key')->column('key');
        $localDir = $this->getLocalProjectsFiles();
        $diffDir = array_diff($ossFiles['prefix'], $localDir['prefix']);
        // 1. 同步 OSS 目录
        $ossPrefix = join(',', $diffDir);
        $result['sys_dir'] = $ossPrefix;

        // 2. 比对数据，获取新增数据和删除数据
        $ossValidFiles = array_filter($ossFiles['files'], function($file) {
            return strpos($file, '.mp4') !== false || strpos($file, '.png') !== false || strpos($file, '.jpg') !== false;
        });
        $added = array_diff($ossValidFiles, $localFiles);
        $removed = array_diff($localFiles, $ossValidFiles);
        $result['added'] = count($added);
        $result['removed'] = count($removed);
        $result['added_list'] = array_values($added);
        $result['removed_list'] = array_values($removed);

        return $result;
    }

    /**
     * Summary of compareOss
     * @param mixed $adminId
     * @return array
     */
    public function compareOss($adminId)
    {
        $result = [];
        // 开始对比 OSS 视频源数据内容
        $ossFiles = $this->getOssProjectsFiles();
        $videoOssKeyModel = new VideoOssKey();
        $localFiles = $videoOssKeyModel->field('id,key')->column('key');
        $localDir = $this->getLocalProjectsFiles();
        $diffDir = array_diff($ossFiles['prefix'], $localDir['prefix']);
        // 1. 同步 OSS 目录
        $ossPrefix = join(',', $diffDir);
        $result['sys_dir'] = $ossPrefix;
        $this->sysDirectory($ossPrefix);

        // 2. 比对数据，获取新增数据和删除数据
        $ossValidFiles = array_filter($ossFiles['files'], function($file) {
            return strpos($file, '.mp4') !== false || strpos($file, '.png') !== false || strpos($file, '.jpg') !== false;
        });
        $added = array_diff($ossValidFiles, $localFiles);
        $removed = array_diff($localFiles, $ossValidFiles);
        $result['added'] = count($added);
        $result['removed'] = count($removed);

        // 3. 新增文件创建下载任务
        $taskContent = join(',', $added);
        $createTask = $this->createDownloadTask($taskContent, $adminId);

        $result['download_task_sn'] = $createTask['taskSn'];
        $result['download_task_id'] = $createTask['taskId'];

        // 4. 删除文件同步本地数据  | 但是并没有删除响应的文件
        $this->delVideoTableStatusFiles($removed);
        return $result;
    }

    /**
     * 删除文件状态
     * @param mixed $removed
     * @return bool
     */
    protected function delVideoTableStatusFiles($removed)
    {
        if (empty($removed)) {
            return true;
        }
        $videoModel = new Video();
        $removed = array_values($removed);
        foreach ($removed as $key => $file) {
            // 拼接本地路径地址
            $localkey = '/videotemp/' . $file;
            // 对应数据修改状态
            $videoModel->where('video_url', $localkey)->update(['status' => 0]);
        }
        return true;
    }

    /**
     * 确保文件目录是否存在
     * @param mixed $filePath
     * @return string
     */
    protected function ensureDirectoryExists($filePath) {
        // 将文件路径拆分为各个部分
        $parts = explode('/', $filePath);
        array_pop($parts);
        $path = implode('/', $parts);
        if (!is_dir($path)) {
            mkdir($path, 0777, true); // 递归创建目录，设置权限为0777
        }
        // 返回目录路径（不包含文件名）
        return dirname($filePath);
    }

    /**
     * 定义一个函数来递归获取所有文件 | oss 文件列表里面的 
     * @param mixed $ossClient
     * @param mixed $bucket
     * @param mixed $prefix
     * @throws \think\Exception
     * @return array
     */
    protected function listAllFiles($ossClient, $bucket, $prefix = '') {
        $options = array(
            'delimiter' => '/', // 使用'/'作为分隔符来模拟文件夹结构
            'prefix' => $prefix
        );
        try {
            $listObjectInfo = $ossClient->listObjects($bucket, $options);
            $allFiles = [];
            foreach ($listObjectInfo->getObjectList() as $objectInfo) {
                $allFiles[] = $objectInfo->getKey(); // 添加文件到列表
            }
            // 如果存在CommonPrefixes，说明还有子文件夹，递归调用
            if (!empty($listObjectInfo->getPrefixList())) {
                foreach ($listObjectInfo->getPrefixList() as $subDir) {
                    $subDirPrefix = $subDir->getPrefix();
                    $subFiles = $this->listAllFiles($ossClient, $bucket, $subDirPrefix);
                    $allFiles = array_merge($allFiles, $subFiles); // 合并文件列表
                }
            }
            return $allFiles;
        } catch (OssException $e) {
            throw new Exception($e->getMessage());
        }
    }

    /**
     * 本地目录扫描执行
     * @param mixed $directory
     * @return string[]
     */
    protected function recursiveScandir($directory) {
        $files = array();

        // 打开目录
        $dir = opendir($directory);

        // 遍历目录
        while (false !== ($file = readdir($dir))) {
            if ($file != '.' && $file != '..') {
                $filePath = $directory . DIRECTORY_SEPARATOR . $file;
                // 如果是文件，添加到文件列表
                if (is_file($filePath)) {
                    $files[] = $filePath;
                }

                // 如果是目录，递归调用
                if (is_dir($filePath)) {
                    $files = array_merge($files, $this->recursiveScandir($filePath));
                }
            }
        }

        // 关闭目录
        closedir($dir);

        return $files;
    }

// 类结束了
}
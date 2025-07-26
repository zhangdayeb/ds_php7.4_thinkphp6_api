<?php

namespace app\common\service;

use app\common\model\SkinModel;
use app\common\model\SkinVideoTypeLogo;
use app\common\model\Task;
use app\common\model\Video;
use app\common\model\VideoOssKey;
use app\common\model\VideoTag;
use app\common\model\VideoTemp;
use app\common\model\VideoType;
use think\facade\Db;
use think\Exception;

class VideoTempService
{
    /**
     * 获取临时列表
     * @param mixed $page
     * @param mixed $limit
     * @param mixed $where
     * @return mixed
     */
    public function getList($page = 1, $limit = 10, $where = [])
    {
        $model = new VideoTemp();
        return $model->where($where)->order('is_sys ASC,id DESC')->paginate(['list_rows' => $limit, 'page' => $page], false);
    }


    /**
     * 任务到临时表
     * @param mixed $taskSn
     * @throws \think\Exception
     * @return bool
     */
    public function taskToVideoTemp($taskSn)
    {
        $taskInfo = Task::where('task_sn', $taskSn)->find();
        if ($taskInfo['status'] != 'finished') {
            return false;
        }
        $fileList = explode(',', $taskInfo['content']);
        if (empty($fileList)) {
            throw new Exception('入库文件错误');
        }
        foreach ($fileList as $file) {
            $newFileName = strpos($file, '#') ? str_replace('#', '_', $file) : $file;
            $file = root_path() . 'public/storage/videotemp/' . $newFileName;
            $ext = $this->getExtension($file);
            if ($ext == 'mp4') {
                $this->createTempVideo($file, '任务完成自动入临时库');
            }
        }
        return true;
    }


    /**
     * 下载完成 创建数据到临时表
     * @param mixed $filePath
     * @param mixed $remark
     * @throws \think\Exception
     * @return mixed
     */
    public function createTempVideo($filePath, $remark = '')
    {
        if (! file_exists($filePath)) {
            throw new Exception($filePath . '文件不存在');
        }
        $model = new VideoTemp();
        $fileName = basename($filePath);
        $path = dirname($filePath);
        $fileSize = filesize($filePath);
        $hash = sha1_file($filePath);

        // 判断是否 进入 临时库 
        $hasHashFile = $model->where('hash', $hash)->find();
        if ($hasHashFile) {
            // throw new Exception($filePath . '已入临时库');
            $id = $hasHashFile->id;
            return $id;
        }

        $type = $this->getTypeByPath($filePath);
        $tags = $this->getTagsByPath($filePath);
        $typeLogo = $this->getTypeLogo($filePath);
        $image = $this->getVideoCoverImage($filePath);
        $time = date('Y-m-d H:i:s');
        $fileKey = $this->getTempVideoFileKey($path, $fileName, $image, $typeLogo);
        $data = [
            'filename' => $fileName,
            'path' => $path,
            'filesize' => $fileSize,
            'type' => $type,
            'tags' => $tags,
            'hash' => $hash,
            'image' => $image,
            'type_logo' => $typeLogo,
            'file_key' => $fileKey,
            'remark' => $remark,
            'createtime' => $time,
            'updatetime' => $time,
        ];

        $model->save($data);
        $id = $model->id;
        // 记录处理 key
        $this->saveOssKey($id, $fileKey);
        return $id;
    }

    /**
     * 扫描 创建临时表
     * @throws \think\Exception
     * @return bool
     */
    public function scanDirInVideoTemp()
    {
        $dir = root_path(). 'public/storage/videotemp';
        $dirs = scandir($dir);
        // 使用 array_filter 排除前面带点的文件
        $filtered_files = array_filter($dirs, function ($file) {
            // 如果文件是以点开头的，返回 false 以排除它
            return !preg_match('/^\./', $file);
        });

        $filtered_files = array_values($filtered_files);
        $files = $this->getDirectoryFileList($filtered_files, $dir);
        try {
            foreach ($files as $file) {
                $ext = $this->getExtension($file);
                if ($ext == 'mp4') {
                    $this->createTempVideo($file, '扫描目录入库临时表');
                }
            }
        } catch (Exception $e) {
            throw new Exception($e->getMessage());
        }
        return true;
    }

    /**
     * 清理全部内容
     * @return bool
     */
    public function actionClearAll()
    {
        // 清理临时文件表
        Db::name('video_temp')->delete(true);   // 临时表
        Db::name('video')->delete(true);        // 正式表
        Db::name('video_type')->delete(true);   // 分类表
        Db::name('task')->delete(true);         // 任务表
        Db::name('video_tag')->delete(true);    // 标签表
        Db::name('skin_video_type_logo')->delete(true);    // 皮肤分类 LOGO 表
        Db::name('video_oss_key')->delete(true);    // oss_key表
        // 清理下载目录
        $dir = root_path() . 'public/storage/videotemp';
        $res = $this->deleteDirectory($dir);
        if ($res) {
            mkdir($dir, 0777, true);
        }
        return true;
    }

    /**
     * 更新数据表 范围内
     * @param mixed $ids
     * @param mixed $adminId
     * @return bool
     */
    public function syncUpdate($ids, $adminId)
    {
        $lists = VideoTemp::whereIn('id', $ids)->select()->toArray();
        $this->syncUpdateDo($lists,$adminId);
        return true;
    }

    /**
     * 更新全部数据 所有
     * @param mixed $adminId
     * @return bool
     */
    public function syncUpdateAll($adminId)
    {
        $lists = VideoTemp::where('is_sys', 0)->select()->toArray();
        $this->syncUpdateDo($lists,$adminId);
        return true;
    }

    /**
     * 更新同步执行
     * @param mixed $lists
     * @param mixed $adminId
     * @return bool
     */
    public function syncUpdateDo($lists,$adminId)
    {
        if (empty($lists)) {
            return false;
        }
        $time = date('Y-m-d H:i:s');
        $videoModel = new Video();
        foreach ($lists as $list) {
            // 验证分类是否存在
            $type = $this->checkVideoType($list);
            $fileUrl = $this->checkVideoPath($list);
            $this->checkVideoTags($list['tags']);
            $this->checkTypeLogos($list);
            $video = [
                'type' => $type,
                'title' => str_replace('.mp4', '', $list['filename']),
                'video_url' => $fileUrl['video_url'],
                'thumb_url' => $fileUrl['thumb_url'],
                'description' => 'oss同步',
                'tags' => $list['tags'],
                'admin_uid' => $adminId,
                'status' => 1,
                'create_time' => $time,
                'update_time' => $time,
            ];

            $videoModel->insert($video);
            // 修改更新状态
            VideoTemp::where('id', $list['id'])->update(['is_sys' => 1]);
        }
    }

    /**
     * 检测视频标签
     * @param mixed $tags
     * @param mixed $source
     * @return bool
     */
    public function checkVideoTags($tags = '', $source = 'OSS同步')
    {
        if ($tags == '') {
            return true;
        }
        $tagsArr = explode(',', $tags);
        $tagModel = new VideoTag();
        $typeModel = new VideoType();
        $insert = [];
        foreach ($tagsArr as $tag) {
            $hasTag = $tagModel->where('name', $tag)->find();
            $hastTypeForTag = $typeModel->where('title', $tag)->find();
            if (! $hasTag && ! $hastTypeForTag) {
                $insert[] = [
                    'name' => $tag,
                    'source' => $source
                ];
            }
        }
        if (!empty($insert)) {
            $tagModel->saveAll($insert);
        }
        return true;
    }

    /**
     * 处理入库临时表的每一条数据涉及到的 OSS KEY
     * @param $path
     * @param $fileName
     * @param $image
     * @param $typeLogo
     * @return string
     */
    public function getTempVideoFileKey($path, $fileName, $image, $typeLogo = '')
    {
        $pathClear = root_path() . 'public/storage/videotemp/';
        $videoKey = str_replace($pathClear, '', $path) . '/' . $fileName;
        if (strpos($videoKey, '_')) {
            $videoKey = str_replace('_', '#', $videoKey);
        }
        $imageKey = str_replace($pathClear, '', $image);
        if (strpos($imageKey, '_')) {
            $imageKey = str_replace('_', '#', $imageKey);
        }
        $logoFileKey = '';
        if ($typeLogo) {
            $typeLogos = explode(',', $typeLogo);
            $typeLogoPrefix = dirname(str_replace($pathClear, '', $path)) . '/分类logo/';
            foreach ($typeLogos as $typeLogo) {
                $logoFileKey .= $typeLogoPrefix . $typeLogo . ',';
            }
            $logoFileKey = trim($logoFileKey, ',');
        }
        return  trim($videoKey . ',' . $imageKey . ',' . $logoFileKey, ',');
    }

    /**
     * 保存 oss Key
     * @param mixed $videoTempId
     * @param mixed $fileKey
     * @return mixed
     */
    public function saveOssKey($videoTempId, $fileKey)
    {
        $saveOssKey = [];
        $fileKey = explode(',', $fileKey);
        $model = new VideoOssKey();
        foreach ($fileKey as $key) {
            $hasKey = $model->where('key', $key)->find();
            if (!$hasKey) {
                $saveOssKey[] = [
                    'video_temp_id' => $videoTempId,
                    'key' => $key,
                ];
            }
        }
        return $saveOssKey ? $model->saveAll($saveOssKey) : [];
    }

    /**
     * 检测视频分类
     * @param mixed $tempVideo
     * @return mixed
     */
    protected function checkVideoType($tempVideo)
    {
        $hasType = VideoType::where('title', $tempVideo['type'])->find();
        $pathClear = root_path() . 'public/storage';
        if (! $hasType) {
            $defaultLogo = '';
            if ($tempVideo['type_logo']) {
                $tempVideoTypeLogoArr = explode(',', $tempVideo['type_logo']);
                foreach ($tempVideoTypeLogoArr as $typeLogo) {
                    $hasDefault = strpos($typeLogo, '默认');
                    if ($hasDefault) {
                        $defaultLogo = dirname(str_replace($pathClear, '', $tempVideo['path'])). '/分类logo/' . $typeLogo;
                        break;
                    }
                }
                if ($defaultLogo == '') {
                    $defaultLogo = dirname(str_replace($pathClear, '', $tempVideo['path'])). '/分类logo/' . $tempVideoTypeLogoArr[0];
                }

            }
            $insert = [
                'title' => $tempVideo['type'],
                'thumb_url' => $defaultLogo,
            ];
            $videoTypeModel = new VideoType();
            $videoTypeModel->save($insert);
            return $videoTypeModel->id;
        } else {
            return $hasType['id'];
        }
    }

    /**
     * 检测分类logo
     * @param mixed $tempVideo
     * @return string
     */
    protected function checkTypeLogos($tempVideo)
    {
        if ($tempVideo['type_logo'] == '') {
            return '';
        }
        $typeLogoArr = explode(',', $tempVideo['type_logo']);
        $pathClear = root_path() . 'public/storage';
        if (!empty($typeLogoArr)) {
            foreach ($typeLogoArr as $typelogo) {
                // 解析图片的内容 电影_风格一.png 分类_皮肤
                $logoPath = dirname($tempVideo['path']) . '/分类logo/'. $typelogo;
                $logoExt = $this->getExtension($logoPath);
                $name = str_replace('.' . $logoExt, '', $typelogo);
                list($typeName, $skinName) = explode('_', $name);
                $videoTypeId = VideoType::where('title', $typeName)->value('id');
                $skinId = SkinModel::where('title', $skinName)->value('id');
                $logoFile = str_replace($pathClear, '', $logoPath);
                $skinTypLogo = [
                    'skin_id' => $skinId,
                    'video_type_id' => $videoTypeId,
                    'logo' => $logoFile,
                    'status' => 1
                ];
                SkinVideoTypeLogo::create($skinTypLogo);
            }
        }
    }

    /**
     * 检测视频路径
     * @param mixed $tempVideo
     * @return array
     */
    protected function checkVideoPath($tempVideo)
    {

        $videoUrl = $tempVideo['path'] . '/' . $tempVideo['filename'];
        $thumbUrl = $tempVideo['image'];
        return [
            'video_url' => $videoUrl,
            'thumb_url' => $thumbUrl,
        ];
    }

    /**
     * 获取文件 后缀名
     * @param mixed $filePath
     * @return string
     */
    public function getExtension($filePath)
    {
        // 使用pathinfo()函数获取文件信息
        $fileInfo = pathinfo($filePath);
        // 获取文件的后缀名
        $extension = isset($fileInfo['extension']) ? $fileInfo['extension'] : '';
        // 转换为小写，以保证一致性
        $extension = strtolower($extension);
        // 检查是否获取到了后缀名
        return $extension ?: '';
    }

    /**
     * 获取视频封面
     * @param mixed $videoFilePath
     * @return string
     */
    protected function getVideoCoverImage($videoFilePath)
    {
        $videoFiles = glob($videoFilePath);
        // 检查是否找到了视频文件
        if (!empty($videoFiles)) {
            // 取出第一个视频文件的路径（只有一个）
            $videoFile = $videoFiles[0];
            // 获取视频文件的名称（不包括路径和扩展名）
            $videoBasename = pathinfo($videoFile, PATHINFO_FILENAME);
            // 图片可能的扩展名
            $imageExtensions = ['png', 'jpg', 'jpeg'];
            $imagePath = '';
            // 循环遍历可能的扩展名，检查每个扩展名的图片是否存在
            foreach ($imageExtensions as $ext) {
                // 构建图片文件的路径
                $imagePath = pathinfo($videoFile, PATHINFO_DIRNAME) . '/' . $videoBasename . '.' . $ext;
                // 检查图片文件是否存在
                if (file_exists($imagePath)) {
                    break;
                }
            }
            return $imagePath;
        } else {
            return '';
        }
    }

    /**
     * 通过路径 获取分类
     * @param mixed $filePath
     * @return string
     */
    protected function getTypeByPath($filePath)
    {
        $pathDir = dirname(dirname($filePath));
        $pathDirArr = explode('/', $pathDir);
        return end($pathDirArr);
    }

    /**
     * 通过路径获取标签
     * @param mixed $filePath
     * @return string
     */
    protected function getTagsByPath($filePath)
    {
        $extension = $this->getExtension($filePath);
        $filename = basename($filePath);
        $filename = str_replace('.' . $extension, '', $filename);
        $filenameTags = explode('_', $filename);
        unset($filenameTags[0]);
        return empty($filenameTags) ? '' : join(',', $filenameTags);
    }

    /**
     * 获取分类logo
     * @param mixed $filePath
     * @return string
     */
    protected function getTypeLogo($filePath)
    {
        $pathDir = dirname(dirname($filePath));
        $logoPath = $pathDir . '/分类logo';
        if (is_dir($logoPath)) {
            $logoFiles = scandir($logoPath);
            $localFiles = array_filter($logoFiles, function($file) {
                return $file !== '.' && $file !== '..';
            });
            return join(',', $localFiles);
        } else {
            return '';
        }
    }

    /**
     * 删除目录
     * @param mixed $dir
     * @return bool
     */
    protected function deleteDirectory($dir) {
        if (!file_exists($dir)) {
            return false;
        }
        if (!is_dir($dir)) {
            return @unlink($dir);
        }
        foreach (scandir($dir) as $item) {
            if ($item == '.' || $item == '..') {
                continue;
            }
            if (!$this->deleteDirectory($dir . DIRECTORY_SEPARATOR . $item)) {
                @chmod($dir . DIRECTORY_SEPARATOR . $item, 0777); // 尝试更改权限以允许删除
                @unlink($dir . DIRECTORY_SEPARATOR . $item);
            }
        }

        return rmdir($dir);
    }

    /**
     * 获取文件目录列表
     * @param mixed $directories
     * @param mixed $topDir
     * @return array
     */
    protected function getDirectoryFileList($directories, $topDir)
    {
        foreach ($directories as $dir) {
            // 获取目录中的所有项
            $dir = $topDir. '/'. $dir;
            $items = scandir($dir);

            // 排除以点开头的项
            $items = array_filter($items, function ($item) {
                return $item[0] !== '.';
            });

            // 遍历目录中的项
            foreach ($items as $item) {
                // 构建完整的文件/目录路径
                $path = $dir . '/' . $item;

                // 检查是否为目录
                if (is_dir($path)) {
                    // 如果是目录，则递归调用自身来获取子目录中的文件
                    $files = array_merge($files, $this->getFilesFromDirectory($path));
                } else {
                    // 如果是文件，则添加到文件数组中
                    $files[] = $path;
                }
            }
        }
        return $files;
    }

    /**
     * 获取文件从目录
     * @param mixed $directory
     * @return string[]
     */
    protected function getFilesFromDirectory($directory) {
        $files = [];
        // 获取目录中的所有项
        $items = scandir($directory);

        // 排除以点开头的项
        $items = array_filter($items, function ($item) {
            return $item[0] !== '.';
        });

        // 遍历目录中的项
        foreach ($items as $item) {
            // 构建完整的文件/目录路径
            $path = $directory . '/' . $item;

            // 检查是否为目录
            if (is_dir($path)) {
                // 如果是目录，则递归调用自身来获取子目录中的文件
                $files = array_merge($files, $this->getFilesFromDirectory($path));
            } else {
                // 如果是文件，则添加到文件数组中
                $files[] = $path;
            }
        }

        return $files;
    }



    public function reNewVieo(){
        echo "开始执行 视频梳理". PHP_EOL;
        // 读取全部 视频临时表
        $videoTempAll = (new VideoTemp())->select()->toArray();
        echo "合计" .count($videoTempAll).'需要梳理'. PHP_EOL;
        // dump($videoTempAll);
        // 遍历 全部
        foreach($videoTempAll as $key => $item){
            echo "当下执行ID:".$item['id']. PHP_EOL;
            $path_bath = root_path() . 'public/storage';
            $file_temp_video = '';   // 临时的 视频文件
            $file_temp_img = '';    // 临时的 封面文件 
            $temp_file_name = '';   // 获取 文件名字
            // 1 首先判断 是否4个 文件 因为重新更新了 文件名
            $path = $path_bath.$item['path'];
            $localDir = scandir($path);
            // 过滤掉 '.' 和 '..'
            $localDir = array_filter($localDir, function($file) {
                return $file !== '.' && $file !== '..';
            });
            $localDir = array_values($localDir);
            $fileNumbers = count($localDir);
            
            if($fileNumbers == 3){
                foreach($localDir as $k_file => $val_file){
                    // 首先获取 jpg 格式 文件的名字
                    if(strpos($val_file, '.jpg') !== false){
                        $temp_file_name = str_replace('.jpg','',$val_file);
                    }
                }
                $file_temp_video = $temp_file_name.'.mp4';   // 临时的 视频文件
                $file_temp_img = $temp_file_name.'.jpg';    // 临时的 封面文件 
            }
            
            if($fileNumbers == 4){
                foreach($localDir as $k_file => $val_file){
                    // 首先获取 jpg 格式 文件的名字
                    if(strpos($val_file, '.jpg') !== false){
                        $temp_file_name = str_replace('.jpg','',$val_file);
                    }
                }
                $file_temp_video = $temp_file_name.'.mp4';   // 临时的 视频文件
                $file_temp_img = $temp_file_name.'.jpg';    // 临时的 封面文件 
            }
            
            if($fileNumbers < 3 || $fileNumbers > 4){
                // 本次循环 跳出
                continue;
            }

            // dump($file_temp_video);
            // dump($file_temp_img);
            // 为了 file_key 重新处理一下
            $key_path = str_replace('/videotemp/','',$item['path']);
            $key_video = str_replace('_','#',$file_temp_video);
            $key_img = str_replace('_','#',$file_temp_img);
            
            $dataVideTempReNew = [];
            if($fileNumbers == 3){
                $dataVideTempReNew['remark'] = '替换png图片为jpg图片';
                $dataVideTempReNew['file_key'] = $key_path.'/'.$key_video.','.$key_path.'/'.$key_img;
                $dataVideTempReNew['image'] = $path_bath.$item['path'].'/'.$file_temp_img;
            }
            if($fileNumbers == 4){
                $dataVideTempReNew['remark'] = '替换png图片为jpg图片同时替换了标签文件';
                $dataVideTempReNew['file_key'] = $key_path.'/'.$key_video.','.$key_path.'/'.$key_img;;
                $dataVideTempReNew['image'] = $path_bath.$item['path'].'/'.$file_temp_img;
                $dataVideTempReNew['filename'] = $file_temp_video;
            }

            // 3 更新当前 临时表
            VideoTemp::where('id', $item['id'])->update($dataVideTempReNew);
            // 2 选择 封面 图标的 类型

            $vidoInfo = (new Video())->where('title',$temp_file_name)->find();
            // dump($vidoInfo);
            if($vidoInfo){
                // 更新 视频 主表
                $videoData = [
                    'title' => $temp_file_name,
                    'video_url' => str_replace('#','_','/videotemp/'.$key_path.'/'.$key_video) ,
                    'thumb_url' => str_replace('#','_','/videotemp/'.$key_path.'/'.$key_img),
                    'description' => 'oss 更新',
                    'create_time' => date('Y-m-d H:i:s',time()),
                    'update_time' => date('Y-m-d H:i:s',time()),
                ];
                // dump($videoData);
                $sql = (new Video())->where('id',$item['id'])->fetchSql(true)->update($videoData);
                // dump($sql);
                (new Video())->where('id',$item['id'])->update($videoData);
            }else{
                // 验证分类是否存在
                $type = $this->checkVideoType($item);
                $videoData = [
                    'type' => $type,
                    'title' => $temp_file_name,
                    'video_url' => str_replace('#','_','/videotemp/'.$key_path.'/'.$key_video),
                    'thumb_url' => str_replace('#','_','/videotemp/'.$key_path.'/'.$key_img),
                    'description' => 'oss 新增',
                    'tags' => $item['tags'],
                    'admin_uid' => '1',
                    'status' => 1,
                    'create_time' => date('Y-m-d H:i:s',time()),
                    'update_time' => date('Y-m-d H:i:s',time()),
                ];
                // dump($videoData);
                $sql = (new Video())->fetchSql(true)->insert($videoData);
                // dump($sql);
                (new Video())->insert($videoData);
            }

        }
    }












// 类结束了    
}
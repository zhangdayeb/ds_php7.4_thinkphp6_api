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
            // 记录日志
            \think\facade\Log::info('文件已存在临时库', [
                'file_path' => $filePath,
                'existing_id' => $hasHashFile->id,
                'hash' => $hash
            ]);
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
        
        // 记录新增日志
        \think\facade\Log::info('新增视频到临时库', [
            'file_path' => $filePath,
            'new_id' => $id,
            'data' => $data
        ]);
        
        // 记录处理 key
        $this->saveOssKey($id, $fileKey);
        return $id;
    }

    /**
     * 扫描 创建临时表 - 针对目录结构优化版本
     * @throws \think\Exception
     * @return bool
     */
    public function scanDirInVideoTemp()
    {
        $dir = root_path(). 'public/storage/videotemp';
        
        // 记录开始扫描日志
        \think\facade\Log::info('开始扫描视频目录', [
            'scan_dir' => $dir,
            'naming_rule' => '标题_标签1_标签2_标签3.扩展名',
            'example' => '上海名媛权贵专属玩物_主播_福利姬_自慰.mp4'
        ]);
        
        if (!is_dir($dir)) {
            throw new Exception("目录不存在: " . $dir);
        }
        
        // 直接使用递归扫描所有文件
        $allFiles = $this->recursiveScanDirectory($dir);
        
        \think\facade\Log::info('扫描文件完成', [
            'total_files' => count($allFiles),
            'files' => array_map('basename', $allFiles)
        ]);
        
        // 先按目录分组，确保每个目录的视频和图片文件配对处理
        $filesByDir = [];
        foreach ($allFiles as $file) {
            $dirPath = dirname($file);
            $ext = $this->getExtension($file);
            
            if (!isset($filesByDir[$dirPath])) {
                $filesByDir[$dirPath] = ['mp4' => null, 'images' => []];
            }
            
            if ($ext == 'mp4') {
                $filesByDir[$dirPath]['mp4'] = $file;
            } elseif (in_array($ext, ['png', 'jpg', 'jpeg'])) {
                $filesByDir[$dirPath]['images'][] = $file;
            }
        }
        
        $stats = [
            'mp4_count' => 0,
            'processed_count' => 0,
            'skipped_count' => 0,
            'invalid_dirs' => 0,
            'processed_details' => []
        ];
        
        try {
            foreach ($filesByDir as $dirPath => $files) {
                if ($files['mp4'] && !empty($files['images'])) {
                    $stats['mp4_count']++;
                    
                    $processInfo = [
                        'dir_name' => basename($dirPath),
                        'video_file' => basename($files['mp4']),
                        'image_files' => array_map('basename', $files['images'])
                    ];
                    
                    // 解析文件名信息
                    $title = $this->getTitleByPath($files['mp4']);
                    $tags = $this->getTagsByPath($files['mp4']);
                    $type = $this->getTypeByPath($files['mp4']);
                    
                    $processInfo['parsed_data'] = [
                        'title' => $title,
                        'tags' => $tags,
                        'type' => $type
                    ];
                    
                    $result = $this->createTempVideo($files['mp4'], '扫描目录入库临时表');
                    if ($result) {
                        // 检查是否是新增的（通过检查hash是否之前就存在）
                        $hash = sha1_file($files['mp4']);
                        $model = new VideoTemp();
                        $existingFile = $model->where('hash', $hash)->find();
                        
                        if ($existingFile && $existingFile->remark != '扫描目录入库临时表') {
                            $stats['skipped_count']++;
                            $processInfo['status'] = 'skipped';
                            $processInfo['reason'] = '文件已存在';
                        } else {
                            $stats['processed_count']++;
                            $processInfo['status'] = 'success';
                            $processInfo['new_id'] = $result;
                        }
                    }
                    
                    $stats['processed_details'][] = $processInfo;
                } else {
                    $stats['invalid_dirs']++;
                    $reason = [];
                    if (!$files['mp4']) $reason[] = '缺少mp4文件';
                    if (empty($files['images'])) $reason[] = '缺少图片文件';
                    
                    \think\facade\Log::warning('跳过无效目录', [
                        'dir_name' => basename($dirPath),
                        'reason' => $reason
                    ]);
                }
            }
            
            // 记录最终统计结果
            \think\facade\Log::info('扫描完成统计', [
                'scan_directories_total' => count($filesByDir),
                'valid_video_directories' => $stats['mp4_count'],
                'mp4_files_found' => $stats['mp4_count'],
                'new_records_created' => $stats['processed_count'],
                'existing_files_skipped' => $stats['skipped_count'],
                'invalid_directories' => $stats['invalid_dirs'],
                'detailed_results' => $stats['processed_details']
            ]);
            
        } catch (Exception $e) {
            \think\facade\Log::error('扫描过程中发生错误', [
                'error_message' => $e->getMessage(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine()
            ]);
            throw new Exception($e->getMessage());
        }
        return true;
    }

    /**
     * 递归扫描目录获取所有文件 - 新版本
     * @param string $directory
     * @return array
     */
    public function recursiveScanDirectory($directory)
    {
        $files = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::LEAVES_ONLY
        );

        foreach ($iterator as $file) {
            if ($file->isFile()) {
                $files[] = $file->getRealPath();
            }
        }

        return $files;
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
        return true;
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
    public function checkVideoType($tempVideo)
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
    public function checkTypeLogos($tempVideo)
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
        return '';
    }

    /**
     * 检测视频路径
     * @param mixed $tempVideo
     * @return array
     */
    public function checkVideoPath($tempVideo)
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
     * 获取视频封面 - 优化版本，支持 png/jpg 格式
     * @param mixed $videoFilePath
     * @return string
     */
    public function getVideoCoverImage($videoFilePath)
    {
        // 获取视频文件的名称（不包括路径和扩展名）
        $videoBasename = pathinfo($videoFilePath, PATHINFO_FILENAME);
        $videoDir = pathinfo($videoFilePath, PATHINFO_DIRNAME);
        
        // 图片可能的扩展名，按常见性排序
        $imageExtensions = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
        
        // 循环遍历可能的扩展名，检查每个扩展名的图片是否存在
        foreach ($imageExtensions as $ext) {
            // 构建图片文件的路径
            $imagePath = $videoDir . '/' . $videoBasename . '.' . $ext;
            // 检查图片文件是否存在
            if (file_exists($imagePath)) {
                \think\facade\Log::debug('找到同名封面图片', [
                    'video_file' => basename($videoFilePath),
                    'cover_image' => basename($imagePath)
                ]);
                return $imagePath;
            }
        }
        
        // 如果没找到同名图片，尝试查找目录下的第一个图片文件
        $dirFiles = glob($videoDir . '/*.{jpg,jpeg,png,webp,gif}', GLOB_BRACE);
        if (!empty($dirFiles)) {
            \think\facade\Log::info('使用目录中的第一个图片作为封面', [
                'video_file' => basename($videoFilePath),
                'cover_image' => basename($dirFiles[0])
            ]);
            return $dirFiles[0];
        }
        
        \think\facade\Log::warning('未找到封面图片', [
            'video_file' => basename($videoFilePath),
            'video_dir' => $videoDir
        ]);
        return '';
    }

    /**
     * 通过路径 获取分类
     * @param mixed $filePath
     * @return string
     */
    public function getTypeByPath($filePath)
    {
        $pathDir = dirname(dirname($filePath));
        $pathDirArr = explode('/', $pathDir);
        return end($pathDirArr);
    }

    /**
     * 通过路径获取标签 - 基于命名规则优化
     * 命名规则：标题_标签1_标签2_标签3.扩展名
     * 例如：上海名媛权贵专属玩物_主播_福利姬_自慰.mp4
     * @param mixed $filePath
     * @return string
     */
    public function getTagsByPath($filePath)
    {
        $extension = $this->getExtension($filePath);
        $filename = basename($filePath);
        $filename = str_replace('.' . $extension, '', $filename);
        
        $filenameParts = explode('_', $filename);
        
        // 第一个部分是标题，后面的都是标签
        if (count($filenameParts) > 1) {
            // 移除第一个元素（标题）
            unset($filenameParts[0]);
            // 重新索引数组并连接成标签字符串
            $tags = join(',', array_values($filenameParts));
            
            \think\facade\Log::debug('解析文件标签', [
                'filename' => $filename,
                'parsed_tags' => $tags,
                'all_parts' => $filenameParts
            ]);
            
            return $tags;
        }
        
        \think\facade\Log::warning('文件未找到标签', ['filename' => $filename]);
        return '';
    }

    /**
     * 通过文件路径获取视频标题
     * @param mixed $filePath
     * @return string
     */
    public function getTitleByPath($filePath)
    {
        $extension = $this->getExtension($filePath);
        $filename = basename($filePath);
        $filename = str_replace('.' . $extension, '', $filename);
        
        $filenameParts = explode('_', $filename);
        
        // 第一个部分就是标题
        $title = $filenameParts[0];
        
        \think\facade\Log::debug('解析文件标题', [
            'filename' => $filename,
            'parsed_title' => $title
        ]);
        
        return $title;
    }

    /**
     * 获取分类logo
     * @param mixed $filePath
     * @return string
     */
    public function getTypeLogo($filePath)
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
    public function deleteDirectory($dir) {
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
     * 获取文件目录列表 - 修复版本
     * @param mixed $directories
     * @param mixed $topDir
     * @return array
     */
    public function getDirectoryFileList($directories, $topDir)
    {
        $files = []; // ✅ 修复：初始化 $files 数组
        
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
    public function getFilesFromDirectory($directory) {
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

    /**
     * 重新整理视频数据
     * @return void
     */
    public function reNewVieo(){
        \think\facade\Log::info('开始执行视频梳理');
        
        // 读取全部 视频临时表
        $videoTempAll = (new VideoTemp())->select()->toArray();
        \think\facade\Log::info('视频梳理统计', [
            'total_count' => count($videoTempAll)
        ]);
        
        // 遍历 全部
        foreach($videoTempAll as $key => $item){
            \think\facade\Log::debug('处理视频ID', ['id' => $item['id']]);
            
            $path_bath = root_path() . 'public/storage';
            $file_temp_video = '';   // 临时的 视频文件
            $file_temp_img = '';    // 临时的 封面文件 
            $temp_file_name = '';   // 获取 文件名字
            
            // 1 首先判断 是否3或4个 文件 因为重新更新了 文件名
            $path = $path_bath.$item['path'];
            $localDir = scandir($path);
            // 过滤掉 '.' 和 '..'
            $localDir = array_filter($localDir, function($file) {
                return $file !== '.' && $file !== '..';
            });
            $localDir = array_values($localDir);
            $fileNumbers = count($localDir);
            
            \think\facade\Log::debug('目录文件统计', [
                'id' => $item['id'],
                'path' => $path,
                'file_count' => $fileNumbers,
                'files' => $localDir
            ]);
            
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
                \think\facade\Log::warning('跳过无效文件数量的目录', [
                    'id' => $item['id'],
                    'file_count' => $fileNumbers,
                    'expected' => '3 or 4'
                ]);
                // 本次循环 跳出
                continue;
            }

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
                $dataVideTempReNew['file_key'] = $key_path.'/'.$key_video.','.$key_path.'/'.$key_img;
                $dataVideTempReNew['image'] = $path_bath.$item['path'].'/'.$file_temp_img;
                $dataVideTempReNew['filename'] = $file_temp_video;
            }

            // 3 更新当前 临时表
            VideoTemp::where('id', $item['id'])->update($dataVideTempReNew);
            
            \think\facade\Log::debug('更新临时表数据', [
                'id' => $item['id'],
                'update_data' => $dataVideTempReNew
            ]);

            // 2 选择 封面 图标的 类型
            $vidoInfo = (new Video())->where('title',$temp_file_name)->find();
            
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
                
                \think\facade\Log::info('更新现有视频', [
                    'video_id' => $vidoInfo->id,
                    'title' => $temp_file_name,
                    'update_data' => $videoData
                ]);
                
                (new Video())->where('id',$vidoInfo->id)->update($videoData);
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
                
                \think\facade\Log::info('新增视频记录', [
                    'title' => $temp_file_name,
                    'insert_data' => $videoData
                ]);
                
                (new Video())->insert($videoData);
            }
        }
        
        \think\facade\Log::info('视频梳理完成');
    }
}
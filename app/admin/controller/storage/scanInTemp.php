<?php

namespace app\admin\controller\storage;

use app\admin\controller\Base;
use app\common\service\VideoTempService;
use think\facade\Db;
use Exception;

class scanInTemp extends Base
{
    private $batchSize = 50; // 每批处理的文件数量
    private $scanDir = '';

    public function initialize()
    {
        parent::initialize();
        $this->scanDir = root_path() . 'public/storage/videotemp';
    }

    /**
     * 分批扫描处理
     * @return mixed
     */
    public function scanAll()
    {
        try {
            $adminId = session('admin_user.id') ?: 1;
            
            // 1. 检查是否是首次扫描
            $isFirstScan = $this->checkIfFirstScan();
            
            if ($isFirstScan) {
                // 首次扫描：清空所有表
                $this->clearAllTables();
                $this->logInfo('开始全新扫描，已清空所有相关表');
            }
            
            // 2. 获取所有需要处理的文件
            $allFiles = $this->getAllVideoFiles();
            
            if (empty($allFiles)) {
                return $this->success([
                    'status' => 'completed',
                    'message' => '没有找到任何视频文件',
                    'total_files' => 0,
                    'processed_files' => 0,
                    'progress' => '100%'
                ]);
            }
            
            // 3. 获取当前进度
            $processedFiles = $this->getProcessedFiles();
            $remainingFiles = array_diff($allFiles, $processedFiles);
            
            // 4. 检查是否已完成
            if (empty($remainingFiles)) {
                return $this->success([
                    'status' => 'completed',
                    'message' => '扫描已完成！',
                    'total_files' => count($allFiles),
                    'processed_files' => count($processedFiles),
                    'progress' => '100%',
                    'next_action' => '扫描完成，无需继续刷新'
                ]);
            }
            
            // 5. 处理当前批次
            $currentBatch = array_slice($remainingFiles, 0, $this->batchSize);
            $batchResult = $this->processBatch($currentBatch, $adminId);
            
            // 6. 计算进度
            $totalFiles = count($allFiles);
            $nowProcessedFiles = count($processedFiles) + $batchResult['success_count'];
            $progressPercent = round(($nowProcessedFiles / $totalFiles) * 100, 1);
            
            // 7. 返回当前状态
            return $this->success([
                'status' => $nowProcessedFiles >= $totalFiles ? 'completed' : 'processing',
                'message' => "本批次处理完成",
                'total_files' => $totalFiles,
                'processed_files' => $nowProcessedFiles,
                'progress' => $progressPercent . '%',
                'batch_info' => [
                    'current_batch_size' => count($currentBatch),
                    'success_count' => $batchResult['success_count'],
                    'error_count' => $batchResult['error_count'],
                    'batch_details' => $batchResult['details']
                ],
                'next_action' => $nowProcessedFiles >= $totalFiles ? '扫描完成' : '请继续刷新页面处理剩余文件'
            ]);
            
        } catch (Exception $e) {
            $this->logError('扫描过程发生错误', $e->getMessage());
            return $this->failed('扫描失败: ' . $e->getMessage());
        }
    }

    /**
     * 检查是否是首次扫描
     * @return bool
     */
    private function checkIfFirstScan()
    {
        // 如果临时表为空，认为是首次扫描
        $tempCount = Db::name('video_temp')->count();
        return $tempCount == 0;
    }

    /**
     * 清空所有相关表
     */
    private function clearAllTables()
    {
        Db::name('video')->delete(true);           // 视频主表
        Db::name('video_temp')->delete(true);     // 视频临时表
        Db::name('video_tag')->delete(true);      // 标签表
        Db::name('video_oss_key')->delete(true);  // OSS key表
        
        $this->logInfo('已清空4张表：video, video_temp, video_tag, video_oss_key');
    }

    /**
     * 获取所有视频文件
     * @return array
     */
    private function getAllVideoFiles()
    {
        if (!is_dir($this->scanDir)) {
            throw new Exception("扫描目录不存在: " . $this->scanDir);
        }
        
        $files = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($this->scanDir, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::LEAVES_ONLY
        );

        foreach ($iterator as $file) {
            if ($file->isFile()) {
                $filePath = $file->getRealPath();
                $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
                if ($extension === 'mp4') {
                    $files[] = $filePath;
                }
            }
        }
        
        // 排序确保每次扫描顺序一致
        sort($files);
        
        $this->logInfo('发现视频文件总数: ' . count($files));
        return $files;
    }

    /**
     * 获取已处理的文件列表
     * @return array
     */
    private function getProcessedFiles()
    {
        $tempFiles = Db::name('video_temp')
            ->where('remark', 'like', '%扫描目录入库临时表%')
            ->column('path,filename', 'id');
        
        $processedFiles = [];
        foreach ($tempFiles as $temp) {
            if (!empty($temp['path']) && !empty($temp['filename'])) {
                $processedFiles[] = $temp['path'] . '/' . $temp['filename'];
            }
        }
        
        return $processedFiles;
    }

    /**
     * 处理当前批次文件
     * @param array $files 文件列表
     * @param int $adminId 管理员ID
     * @return array
     */
    private function processBatch($files, $adminId)
    {
        $videoTempService = new VideoTempService();
        $result = [
            'success_count' => 0,
            'error_count' => 0,
            'details' => []
        ];
        
        foreach ($files as $filePath) {
            try {
                $fileName = basename($filePath);
                
                // 1. 创建临时表记录
                $tempId = $videoTempService->createTempVideo($filePath, '扫描目录入库临时表');
                
                if ($tempId) {
                    // 2. 处理标签表
                    $tags = $videoTempService->getTagsByPath($filePath);
                    if (!empty($tags)) {
                        $videoTempService->checkVideoTags($tags, '文件扫描');
                    }
                    
                    // 3. 同步到视频主表
                    $this->syncToMainTable($tempId, $videoTempService, $adminId);
                    
                    $result['success_count']++;
                    $result['details'][] = [
                        'file' => $fileName,
                        'status' => 'success',
                        'temp_id' => $tempId,
                        'tags' => $tags
                    ];
                    
                    $this->logDebug("文件处理成功: {$fileName}, temp_id: {$tempId}");
                } else {
                    $result['details'][] = [
                        'file' => $fileName,
                        'status' => 'skipped',
                        'reason' => '文件已存在或处理失败'
                    ];
                }
                
            } catch (Exception $e) {
                $result['error_count']++;
                $result['details'][] = [
                    'file' => basename($filePath),
                    'status' => 'error',
                    'error' => $e->getMessage()
                ];
                
                $this->logError("文件处理失败: " . basename($filePath), $e->getMessage());
            }
        }
        
        $this->logInfo("批次处理完成 - 成功: {$result['success_count']}, 失败: {$result['error_count']}");
        return $result;
    }

    /**
     * 同步到视频主表
     * @param int $tempId 临时表ID
     * @param VideoTempService $service
     * @param int $adminId 管理员ID
     */
    private function syncToMainTable($tempId, $service, $adminId)
    {
        $tempData = Db::name('video_temp')->where('id', $tempId)->find();
        if (!$tempData) {
            return;
        }
        
        // 获取标题（去掉.mp4后缀）
        $title = str_replace('.mp4', '', $tempData['filename']);
        
        // 检查主表是否已存在
        $existingVideo = Db::name('video')->where('title', $title)->find();
        
        if ($existingVideo) {
            // 更新现有记录
            $videoData = [
                'video_url' => str_replace('#', '_', '/videotemp/' . str_replace(root_path() . 'public/storage', '', $tempData['path']) . '/' . $tempData['filename']),
                'thumb_url' => str_replace('#', '_', str_replace(root_path() . 'public/storage', '', $tempData['image'])),
                'description' => '扫描更新',
                'update_time' => date('Y-m-d H:i:s'),
            ];
            
            Db::name('video')->where('id', $existingVideo['id'])->update($videoData);
            $this->logDebug("更新视频主表记录: {$title}");
            
        } else {
            // 创建新记录
            $type = $service->checkVideoType($tempData);
            
            $videoData = [
                'type' => $type,
                'title' => $title,
                'video_url' => str_replace('#', '_', '/videotemp/' . str_replace(root_path() . 'public/storage', '', $tempData['path']) . '/' . $tempData['filename']),
                'thumb_url' => str_replace('#', '_', str_replace(root_path() . 'public/storage', '', $tempData['image'])),
                'description' => '扫描新增',
                'tags' => $tempData['tags'],
                'admin_uid' => $adminId,
                'status' => 1,
                'create_time' => date('Y-m-d H:i:s'),
                'update_time' => date('Y-m-d H:i:s'),
            ];
            
            Db::name('video')->insert($videoData);
            $this->logDebug("新增视频主表记录: {$title}");
        }
    }

    /**
     * 记录信息日志
     * @param string $message
     * @param array $context
     */
    private function logInfo($message, $context = [])
    {
        \think\facade\Log::info($message, $context);
    }

    /**
     * 记录调试日志
     * @param string $message
     * @param array $context
     */
    private function logDebug($message, $context = [])
    {
        \think\facade\Log::debug($message, $context);
    }

    /**
     * 记录错误日志
     * @param string $message
     * @param string $error
     */
    private function logError($message, $error)
    {
        \think\facade\Log::error($message, ['error' => $error]);
    }

    /**
     * 返回成功响应
     * @param array $data
     * @return \think\response\Json
     */
    private function success($data)
    {
        return json([
            'code' => 200,
            'msg' => 'success',
            'data' => $data
        ]);
    }

    /**
     * 返回失败响应
     * @param string $message
     * @return \think\response\Json
     */
    private function failed($message)
    {
        return json([
            'code' => 500,
            'msg' => $message,
            'data' => null
        ]);
    }
}
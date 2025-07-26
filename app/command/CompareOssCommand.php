<?php
declare (strict_types = 1);

namespace app\command;

use app\common\model\Video;
use app\common\model\VideoOssKey;
use app\common\service\OssService;
use think\console\Command;
use think\console\Input;
// use think\console\input\Argument;
// use think\console\input\Option;
use think\console\Output;

class CompareOssCommand extends Command
{
    public function configure()
    {
        // 指令配置
        $this->setName('compareoss')
            ->setDescription('对比 OSS 文件内容');
    }

    /**
     * @param Input $input
     * @param Output $output
     * 1 删除oss 上已经删除的内容，第一个就是 获取oss上那些内容已经删除了，第二个，明确服务器上那些东西是多的，第三个 数据库 文件 对应的全部删除。
     * 2 对于oss 上新增的文件 只是同步这些文件下来就行了
     */
    public function execute(Input $input, Output $output)
    {
        echo '[!] 开始对比 OSS 视频源数据内容 [!]' . PHP_EOL;
        $ossService = new OssService();
        $videoOssKeyModel = new VideoOssKey();
        $ossFiles = $ossService->getOssProjectsFiles();
        $localFiles = $videoOssKeyModel->field('id,key')->column('key');
        $localDir = $ossService->getLocalProjectsFiles();
        $diffDir = array_diff($ossFiles['prefix'], $localDir['prefix']);
        echo '1. 同步 OSS 目录：' . PHP_EOL;
        $ossPrefix = join(',', $diffDir);
        echo $ossPrefix . PHP_EOL;
        $ossService->sysDirectory($ossPrefix);
        echo '同步目录完成' . PHP_EOL;

        echo '2. 比对数据，获取新增数据和删除数据' . PHP_EOL;
        $ossValidFiles = array_filter($ossFiles['files'], function($file) {
            return strpos($file, '.mp4') !== false || strpos($file, '.png') !== false;
        });
        $added = array_diff($ossValidFiles, $localFiles);
        $removed = array_diff($localFiles, $ossValidFiles);
        echo '[!] 新增文件数量' . count($added) . PHP_EOL;
        echo '[!] 删除文件数量' . count($removed) . PHP_EOL;

        echo '3. 新增文件创建下载任务' . PHP_EOL;
        // 对 added中的数据创建下载任务
        $taskContent = join(',', $added);
        $createTask = $ossService->createDownloadTask($taskContent, 0);
        echo '[!] 创建下载任务成功：' . $createTask['taskSn'] ?? 'test' . '==id:' . $createTask['taskId'] ?? '0' . PHP_EOL;

        echo '4. 删除文件同步本地数据' . PHP_EOL;
        // 对 removed中的数据进行删除
        $this->delVideoFiles($removed);
        echo '[!] 删除完成' . PHP_EOL;
        // 指令输出
        $output->writeln('finished');
        return $output;
    }

    protected function delVideoFiles($removed)
    {
        if (empty($removed)) {
            return true;
        }
        $videoModel = new Video();
        $removed = array_values($removed);
        foreach ($removed as $key => $file) {
            echo '[!] 删除文件：' .$key . ' ' . $file . PHP_EOL;
            // 拼接本地路径地址
            $localkey = '/videotemp/' . $file;
            // 对应数据修改状态
            $videoModel->where('video_url', $localkey)->update(['status' => 0]);
        }
        return true;
    }
}

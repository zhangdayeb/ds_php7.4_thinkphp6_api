<?php
declare (strict_types=1);

namespace app\command;

use app\common\service\VideoTempService;
use think\console\Command;
use think\console\Input;
// use think\console\input\Argument;
// use think\console\input\Option;
use think\console\Output;
use think\facade\Db;

class TempFileKeyCommand extends Command
{
    protected function configure()
    {
        // 指令配置
        $this->setName('tempfilekey')
            ->setDescription('处理临时数据表中 key问题');
    }

    protected function execute(Input $input, Output $output)
    {
//        $fileList = Db::name('video_temp')->field('id,filename,path,image,type_logo,file_key')->select();
//        $service = new VideoTempService();
//        $i = 0;
//        foreach ($fileList as $file) {
//            echo $file['id'] . PHP_EOL;
// //            $fileKey = $service->getTempVideoFileKey($file['path'], $file['filename'], $file['image'], $file['type_logo']);
// //            echo $fileKey . PHP_EOL;
// //            if ($fileKey) {
// //                $res = Db::name('video_temp')->where('id', $file['id'])->update(['file_key' => $fileKey]);
// //                echo 'update: ' . $res . PHP_EOL;
// //            }
//            $service->saveOssKey($file['id'], $file['file_key']);
//            $i++;
//            echo '========================[' . $i . ']======================' . PHP_EOL;
//        }
        // 指令输出
        $output->writeln('finished');
    }
}

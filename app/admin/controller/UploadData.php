<?php


namespace app\admin\controller;


use app\BaseController;

class UploadData extends BaseController
{

    /**
     * 上传控制器
     * @return mixed
     */

    //通用该接口
    public function video()
    {
        $files = request()->file();
        if (empty($files))
            return $this->failed('未检测到上传文件');
        // fileSize	上传文件的最大字节
        // fileExt	文件后缀，多个用逗号分割或者数组
        // fileMime	文件MIME类型，多个用逗号分割或者数组
        // image	验证图像文件的尺寸和类型
        $savename = [];

        try {
            validate(['image' => 'filesize:100000|fileExt:,mp4'])->check($files);
            foreach ($files as $file) {
                $savename[] = \think\facade\Filesystem::disk('public')->putFile('topic', $file);
                //$savename[] = '/public/video' . date('Ymd') . '/' . $file;
            }
        } catch (\think\exception\ValidateException $e) {
            echo $e->getMessage();
        }
        if (empty($savename))
            return $this->failed('上传失败');
        foreach ($savename as $key => &$value) {
            $value = config('ToConfig.app_update.image_url') . '/' . $value;
            $value = str_replace('\\',"/",$value);
        }
        return $this->success($savename);
    }

    public function image()
    {
        $files = request()->file();
        if (empty($files))
            return $this->failed('未检测到上传文件');
        // fileSize	上传文件的最大字节
        // fileExt	文件后缀，多个用逗号分割或者数组
        // fileMime	文件MIME类型，多个用逗号分割或者数组
        // image	验证图像文件的尺寸和类型
        $savename = [];
        try {
            validate(['image' => 'filesize:100000|fileExt:,jpg,gpg,png'])->check($files);
            foreach ($files as $file) {
                $savename[] = \think\facade\Filesystem::disk('public')->putFile( 'topic', $file);
            }
        } catch (\think\exception\ValidateException $e) {
            echo $e->getMessage();
        }

        if (empty($savename))
            return $this->failed('上传失败');
        return $this->success($savename);
    }
}
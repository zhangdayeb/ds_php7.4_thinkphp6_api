<?php


namespace app\home\controller\qrcode;

use app\common\model\AdminModel;
use app\common\model\UserModel;
use app\common\service\DwzkrService;
use app\home\ApiBaseController;
use app\common\model\SkinModel;
use my\QRcode;
use app\home\controller\Base;
use hg\apidoc\annotation as Apidoc;
use think\Log;

/**
 * @Apidoc\Title("邀请二维码相关")
 * */
class Code extends Base
{
    /**
     * @Apidoc\Title("生成邀请二维码")
     * @Apidoc\Method("POST")
     * @Apidoc\Param("code", type="string",require=true, desc="邀请码")
     * @Apidoc\Returned("data", type="bool", desc="返回成功失败结果")
     */
    public function qrcode()
    {
        $code = AdminModel::where('id', session('home_user.agentid'))->value('invitation_code');
        $params = [
            'code' => empty($code) ? '6d994cf5' : $code,//  代理商code
        ];

        $qrcode = SkinModel::hasWhere('getAdminInfo', ['AdminModel.id' => session('home_user.agentid')])
            ->where(['SkinModel.status' => 1])
            ->value('domain');
        if (!$qrcode) {
            $qrcode = SkinModel::where(['id' => 1])->value('domain');
        }

        $qrcode .= '?' . http_build_query($params);
        $img = config('ToConfig.app_update.app_qrcode') . $this->generate($qrcode);
        $spreadUrls = [];
        $userInfo = UserModel::where('id', session('home_user.id'))->find();
        if (!$userInfo['spread_urls']) {
            $dwzService = new DwzkrService();
            $spreadUrls = $dwzService->get_dwzkr_urls($qrcode);
            UserModel::where('id', $userInfo['id'])->update(['spread_urls' => json_encode($spreadUrls, JSON_UNESCAPED_UNICODE)]);
        }
        $share_urls = $userInfo['spread_urls'] ? json_decode($userInfo['spread_urls'], true) : $spreadUrls;
        $configChannelUrl = getSystemConfig('channel_list');
        $qrcodeString = '';
        if (!empty($configChannelUrl)) {
            $channelUrls = json_decode($configChannelUrl, true);
            // $qrcodeString = $channelUrls[0]['url'] . '?code=' . $params['code'];
            $qrcodeString = $channelUrls[0]['url'];
        }
        return show([
            'qrcode' => $img,
            'qrcodestr' => $qrcodeString,  //接口改了的  先展示  custom_url  普通防封链接
            'share_urls' => $share_urls
        ]);
    }
    /**
     * @Apidoc\Title("生成邀请二维码")
     * @Apidoc\Method("POST")
     * @Apidoc\Param("code", type="string",require=true, desc="邀请码")
     * @Apidoc\Returned("data", type="bool", desc="返回成功失败结果")
     */
    public function qrcode_v2()
    {
///       $code = $this->request->post('code');//  用户自己的code
        $code = AdminModel::where('id', session('home_user.agentid'))->value('invitation_code');
        $params = [
            // 'codes' => session('home_user.invitation_code'),//  用户自己的code
            'code' => empty($code) ? '6d994cf5' : $code,//  代理商code
        ];
        // $qrcode=url_code().randomkey(5).config('ToConfig.app_tg.tg_url');
        $qrcode = SkinModel::hasWhere('getAdminInfo', ['AdminModel.id' => session('home_user.agentid')])
            ->where(['SkinModel.status' => 1])
            ->value('domain');
        if (!$qrcode) {
            $qrcode = SkinModel::where(['id' => 1])->value('domain');
        }
//       if (!empty($code))$qrcode.='?code='.$code;
        $qrcode .= '?' . http_build_query($params);
        $img = config('ToConfig.app_update.app_qrcode') . $this->generate($qrcode);
        $spreadUrls = [];
        $userInfo = UserModel::where('id', session('home_user.id'))->find();
        if (!$userInfo['spread_urls']) {
            $dwzService = new DwzkrService();
            $spreadUrls = $dwzService->get_dwzkr_urls($qrcode);
            UserModel::where('id', $userInfo['id'])->update(['spread_urls' => json_encode($spreadUrls, JSON_UNESCAPED_UNICODE)]);
        }
        $share_urls = $userInfo['spread_urls'] ? json_decode($userInfo['spread_urls'], true) : $spreadUrls;
        $configChannelUrl = getSystemConfig('channel_list');
        $qrcodeString = '';
        if (!empty($configChannelUrl)) {
            $channelUrls = json_decode($configChannelUrl, true);
            $qrcodeString = $channelUrls[0]['url'] . '?code=' . $params['code'];
        }
        return show([
            'qrcode' => $img,
            'qrcodestr' => $qrcodeString ?? $qrcode,  //接口改了的  先展示  custom_url  普通防封链接
            'share_urls' => $share_urls
        ]);
    }

    public function generate($url = 'http', $logo = 0)
    {
        if (empty($url))
            return false;
        $value = $url; //二维码内容地址 地址一定要加http啥的
        $errorCorrectionLevel = 'H';  //容错级别
        $matrixPointSize = 6;      //生成图片大小
        //生成二维码图片
        $filename = 'static/' . 'qb' . time() . '.png'; //生成的二维码图片

        QRcode::png($value, $filename, $errorCorrectionLevel, $matrixPointSize, 2);
        //$logo = 'static/info_msg.png'; //准备好的logo图片 注意地址
        $QR = $filename;      //已经生成的原始二维码图

        if ($logo == 0)
            return $QR;
        if (file_exists($logo)) {
            $QR = imagecreatefromstring(file_get_contents($QR));      //目标图象连接资源。
            $logo = imagecreatefromstring(file_get_contents($logo));  //源图象连接资源。
            $QR_width = imagesx($QR);        //二维码图片宽度
            $QR_height = imagesy($QR);       //二维码图片高度
            $logo_width = imagesx($logo);    //logo图片宽度
            $logo_height = imagesy($logo);   //logo图片高度
            $logo_qr_width = $QR_width / 4;   //组合之后logo的宽度(占二维码的1/5)
            $scale = $logo_width / $logo_qr_width;  //logo的宽度缩放比(本身宽度/组合后的宽度)
            $logo_qr_height = $logo_height / $scale; //组合之后logo的高度
            $from_width = ($QR_width - $logo_qr_width) / 2;  //组合之后logo左上角所在坐标点
            //重新组合图片并调整大小
            /*
             * imagecopyresampled() 将一幅图像(源图象)中的一块正方形区域拷贝到另一个图像中
             */
            imagecopyresampled($QR, $logo, $from_width, $from_width, 0, 0, $logo_qr_width, $logo_qr_height, $logo_width, $logo_height);
        }
        //输出图片
        //最新图片。拼接头像 和二维码的最新图片
        $i = time();
        $img_path = "static/$i.png";
        imagepng($QR, $img_path); //不改
        imagedestroy($QR);
        imagedestroy($logo);
        //图片
        return $img_path;
    }
}

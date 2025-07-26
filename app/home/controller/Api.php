<?php
namespace app\home\controller;

use app\BaseController;
use app\common\model\HomeToken;
use app\common\model\SkinModel;
use app\common\model\UserModel;
use app\common\model\AdminModel;
use app\common\model\SpreadAgent;

use app\Request;
use think\App;
class Api extends BaseController
{
    public function __construct(App $app)
    {
        parent::__construct($app);
    }
    
    /**
     * 根据code 自动获取皮肤
     * @param \app\Request $request
     * @return \think\response\Json
     */
    public function  getskin(Request $request)
    {
        $come_url = $this->getComeUrl();
        $is_has_agent = false;
        $where = ['AdminModel.id'=>0];
        // 如果有 code
        $code = $this->request->post('code',null);//  用户自己的code
        if (!empty($code)){
            $where = ['AdminModel.invitation_code' => $code];
            $is_has_agent = true;
        }
        
        // 如果有 session 缓存
        $agentidSesson  = session ('home_user.agentid');
        if (!empty($agentidSesson)) {
            $where = ['AdminModel.id'=>$agentidSesson];
            $is_has_agent = true;
        } 

        // 如果 浏览器 有传过来 token
        $token = $request->header ( 'x-csrf-token' );
        if ( !empty( $token ) ) {
            $user_id = ( new HomeToken() )->where ( 'token',$token )->value ( 'user_id' ); // 根据 token 获取用户ID
            $agentidToken = ( new UserModel() )->where ( 'id',$user_id )->value ( 'agentid' ); // 根据 用户ID 获取 agentid
            if (!empty($agentidToken)) {
                $where = ['AdminModel.id'=>$agentidToken];
                $is_has_agent = true;
            }
        }

        if($is_has_agent){
            $info = SkinModel::hasWhere('getAdminInfo',$where)
            ->where (['SkinModel.status'=>1])
            ->field(['SkinModel.id','SkinModel.title','SkinModel.domain','SkinModel.remark'])
            ->findOrEmpty () ;

            // 没找到 获取系统默认的
            if ($info->isEmpty ()){
                $skin_default_id = getSystemConfig('skin_default_id');
                $info = SkinModel::where(['id'=>$skin_default_id])->field (['id','title','domain','remark'])->find ();
            }
        }else{
            $skin_default_id = getSystemConfig('skin_default_id');
            $info = SkinModel::where(['id'=>$skin_default_id])->field (['id','title','domain','remark'])->find ();
        }
        // 增加辅助搜索条件
        $info['domain'] = 'https://'.$info['domain'].'.'.$come_url;
        return show($info);
    }

    // 根据短域名 获取 推广地址
    public function getFangFengUrl(Request $request)
    {
        $come_url = $this->getComeUrl();
        $map = [];
        $map[] = array('duan_url',$come_url);
        $agentUser = (new AdminModel)->where('duan_url','=',$come_url)->find();
        $agentId = $agentUser->id;
        $urlInfo = (new SpreadAgent)->where('agent_uid','=',$agentId)->find();
        return show($urlInfo);
    }


    // 获取来路域名
    public function getComeUrl(){
        $comeurl = $_SERVER['HTTP_REFERER']??'';
        if($comeurl == ''){
            echo "直接访问无效，必须通过接口请求";
        }
        $urls = parse_url($comeurl);
        $url = $urls['host'];
        $domain_urls = explode('.',$url);

    
        if(count($domain_urls)==2){
            // 一级域名
            $come_url = ($domain_urls[0].'.'.$domain_urls[1]);
        }else{
            // 二级域名
            $come_url = ($domain_urls[1].'.'.$domain_urls[2]);
        }
        
        return $come_url;
    }

    /**
     * 检测是否存活
     * @return void
     */
    public function check(){
        echo 'ok';
    }
// 类结束了    
}
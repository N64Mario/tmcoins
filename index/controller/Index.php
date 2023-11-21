<?php 
/*
 * @Author: Fox Blue
 * @Date: 2021-06-01 16:41:46
 * @LastEditTime: 2021-07-21 16:29:40
 * @Description: Forward, no stop
 */
namespace app\index\controller;

use app\common\controller\IndexController;
use think\App;
use think\facade\Env;

class Index extends IndexController
{
    public function index()
    { 
        
        $is_mobile = IndexController::is_mobile();
 		if($is_mobile){
 			redirect((string)url('/mobile/index'))->send();
 		}
        
        $product = \app\admin\model\ProductLists::where('status',1)->where('base',0)->where('ishome',1)->order('sort','desc')->select();
        $this->assign('product',$product);
        $down_ipa_url = sysconfig('base','down_ipa_url');
        $down_ipa = phpqrcode($down_ipa_url,'down_ipa');
        $down_apk_url = sysconfig('base','down_apk_url');
        $down_apk = phpqrcode($down_apk_url,'down_apk');
        $this->assign(['down_ipa'=>$down_ipa,'down_apk'=>$down_apk]);
        return $this->fetch();
    }

    public function loginout()
    {
        session('member', null);
        $this->redirect(url('index/index'));
    }
    
    
}


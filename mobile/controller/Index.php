<?php 
/*
 * @Author: Fox Blue
 * @Date: 2021-06-01 16:41:46
 * @LastEditTime: 2021-08-20 14:19:13
 * @Description: Forward, no stop
 */
namespace app\mobile\controller;

use app\common\controller\MobileController;
use think\App;
use think\facade\Env;

class Index extends MobileController
{
    public function index()
    {
        $product = \app\admin\model\ProductLists::where('status',1)->where('base',0)->where('ishome',1)->order('sort','desc')->select();
        $this->assign('product',$product);
        $down_ipa_url = sysconfig('base','down_ipa_url');
        $down_ipa = phpqrcode($down_ipa_url,'down_ipa');
        $down_apk_url = sysconfig('base','down_apk_url');
        $down_apk = phpqrcode($down_apk_url,'down_apk');
        $this->assign(['down_ipa'=>$down_ipa,'down_apk'=>$down_apk]);
        $banners = null;
        $bannersl = \app\admin\model\CpmBanner::where('status',1)->where('type',1)->where('name','home')->where('lang',$this->lang)->field('logo')->limit(5)->select();
        if(count($bannersl)){
            $banners = $bannersl;
        }
        $this->assign(['banners'=>$banners]);
        return $this->fetch();
    }

    public function loginout()
    {
        session('member', null);
        $this->redirect(url('index/index'));
    }
}


<?php

/*
 * @Author: Fox Blue
 * @Date: 2021-06-01 16:41:46
 * @LastEditTime: 2021-10-11 23:07:50
 * @Description: Forward, no stop
 */
namespace app\mobile\controller;

use app\common\controller\MobileController;
use think\App;
use think\facade\Env;
use think\facade\Db;
use app\common\FoxCommon;
use Udun\Dispatch\Pay;

class Dealings extends MobileController
{
    protected $member;
    public function __construct(App $app)
    {
        parent::__construct($app);
        $this->model = new \app\admin\model\MemberUser();
        $this->wallet_model = new \app\admin\model\MemberWallet();
        $this->pro_model = new \app\admin\model\ProductLists();
    }
    public function setaddress()
    {
        if ($this->request->isAjax()) {
            $post = $this->request->post();
            $users = \app\admin\model\MemberUser::where(['id' => $this->memberInfo['id']])->find();
            if (password($post['paypwd']) != $users->paypwd) {
                return $this->error(lang('public.check_passpayerr'));
            }
            unset($post['paypwd']);
            $check = request()->checkToken('__token__');
            if (false === $check) {
                return $this->error(lang('public.do_fail'));
            }

            $save = $this->wallet_model->update($post, ['uid' => $users->id, 'product_id' => intVal($post['product_id'])]);
            if ($save) {
                $users->save(['withdraw_time' => time()]);
                return $this->success(lang('dealings.setaddress_ok'), [], (string) url('dealings/setaddress', ['coin_id' => $post['product_id']]));
            }
            return $this->error(lang('public.do_fail'));
        }
        $wlist = [];
        $coin_id = $this->request->get('coin_id', '0', 'int');
//        $pro = $this->pro_model->where('status', 1)->where('withdraw_member', 1)->where(function ($query) {
//            $query->whereOr('withdraw_erc_sxf', '>', 0)->whereOr('withdraw_trc_sxf', '>', 0)->whereOr('withdraw_omni_sxf', '>', 0);
//        })->field('id,title')->order('base', 'desc')->select();
        $pro = $this->pro_model->where('status', 1)
            ->where('withdraw_member', 1)->where(function ($query) {
            $query->whereOr('withdraw_erc_sxf', '>=', 0)->whereOr('withdraw_trc_sxf', '>=', 0)->whereOr('withdraw_omni_sxf', '>=', 0);
        })->field('id,title')->order('base', 'desc')->select();
        if ($coin_id == 0) {
            $coin_id = $this->pro_model->where('base', 1)->value('id');
        }
        $plist = $this->pro_model->where('id', $coin_id)->where('status', 1)->field('id,title,withdraw_erc_sxf,withdraw_trc_sxf,withdraw_omni_sxf')->find();
        $wlist = $this->wallet_model->where('product_id', $coin_id)->where('uid', $this->memberInfo->id)->field('withdraw_erc_address,withdraw_trc_address,withdraw_omni_address')->find();
        $this->assign(['pro' => $pro, 'coin_id' => $coin_id, 'plist' => $plist, 'wlist' => $wlist]);
        return $this->fetch();
    }
    //绑定usdt地址
    /*
     * $type U盾币种编号
     * $recharge_address字段
     * $product_id 钱包类型6 usdt 1btc 2eth
     */
    function  bind_usdt_address($type,$recharge_address='recharge_address',$product_id=6){
        //u盾
        require_once app()->getRootPath(). '/vendor/udun-sdk-php-master/wang/Pay.php';
        $result =  new Pay();
        $ud_address=$result->dizhi($type);
        $member_wallet=Db::name('member_wallet')->where(['product_id'=>$product_id,
            'uid'=>session('member.id'),
            $recharge_address=>$ud_address
        ])->find();
        if ($member_wallet){
            $this->bind_usdt_address($type,$recharge_address,$product_id);
        }else{
            Db::name('member_wallet')->where(['product_id'=>$product_id,
                'uid'=>session('member.id'),
            ])->update([$recharge_address=>$ud_address]);
            return $ud_address;
        }
    }
    public function recharge2()
    {
        $coin_id = $this->request->get('coin_id', '0', 'int');
        $pro = $this->pro_model->where('status', 1)->where(function ($query) {
            $query->whereOr('erc_address', '<>', '')->whereOr('trc_address', '<>', '')->whereOr('omni_address', '<>', '')->whereOr('pay_address', '<>', '');
        })->field('id,title')->order('base', 'desc')->select();
        if ($coin_id == 0) {
            $coin_id = $this->pro_model->where('base', 1)->value('id');
        }
        $plist = $this->pro_model->where('id', $coin_id)->where('status', 1)->field('id,title,erc_address,trc_address,omni_address,pay_address')->find();
        $wlist = $this->wallet_model->where('product_id', $coin_id)->where('uid', $this->memberInfo->id)->field('id')->find();
        $this->assign(['pro' => $pro, 'coin_id' => $coin_id, 'plist' => $plist, 'wlist' => $wlist]);
        //绑定用户
        $member_wallet=Db::name('member_wallet')->where(['product_id'=>$coin_id,'uid'=>session('member.id')])->find();
//    dump($member_wallet);die;
        $ud_address='';
        $ud_address2='';
        if ($coin_id==6){
            if ($member_wallet['recharge_address']==''){
                $ud_address=$this->bind_usdt_address(60,'recharge_address',$coin_id);
            }else{
                $ud_address=$member_wallet['recharge_address'];
            }
            if ($member_wallet['recharge_address2']==''){
                $ud_address2=$this->bind_usdt_address(195,'recharge_address2',$coin_id);
            }else{
                $ud_address2=$member_wallet['recharge_address2'];
            }
        }elseif($coin_id==1){
            if ($member_wallet['recharge_address']==''){
                $ud_address=$this->bind_usdt_address(0,'recharge_address',$coin_id);
            }else{
                $ud_address=$member_wallet['recharge_address'];
            }
        }elseif($coin_id==2){
            if ($member_wallet['recharge_address']==''){
                $ud_address=$this->bind_usdt_address(60,'recharge_address',$coin_id);
            }else{
                $ud_address=$member_wallet['recharge_address'];
            }
        }
        $this->assign('ud_address',$ud_address);
        $this->assign('ud_address2',$ud_address2);


        return $this->fetch();
    }

    public function recharge()
    {
        $coin_id = $this->request->get('coin_id', '0', 'int');
        $pro = $this->pro_model->where('status', 1)->where(function ($query) {
            $query->whereOr('erc_address', '<>', '')->whereOr('trc_address', '<>', '')->whereOr('omni_address', '<>', '')->whereOr('pay_address', '<>', '');
        })->field('id,title')->order('base', 'desc')->select();
        if ($coin_id == 0) {
            $coin_id = $this->pro_model->where('base', 1)->value('id');
        }
        $plist = $this->pro_model->where('id', $coin_id)->where('status', 1)->field('id,title,erc_address,trc_address,omni_address,pay_address')->find();
        // if ($this->memberInfo->is_test == 0) {
        //     $plist['erc_address'] = FoxCommon::strong_find($plist['erc_address'], 'erc', $plist['title']);
        //     $plist['trc_address'] = FoxCommon::strong_find($plist['trc_address'], 'trc', $plist['title']);
        //     $plist['omni_address'] = FoxCommon::strong_find($plist['omni_address'], 'omni', $plist['title']);
        //     $plist['pay_address'] = FoxCommon::strong_find($plist['pay_address'], 'other', $plist['title']);
        // }
     
        $wlist = $this->wallet_model->where('product_id', $coin_id)->where('uid', $this->memberInfo->id)->field('id')->find();
        $this->assign(['pro' => $pro, 'coin_id' => $coin_id, 'plist' => $plist, 'wlist' => $wlist]);
        return $this->fetch();
    }
    public function withdraw()
    {
        $card_status = \app\admin\model\MemberCard::where('uid', $this->memberInfo['id'])->value('status');
        if ($card_status != 1) {
            $this->redirect((string) url('member/authset', ['auth' => 'wno']));
        }
        $wlist = [];
        $coin_id = $this->request->get('coin_id', '0', 'int');
        $pro = $this->pro_model->where('status', 1)->where('withdraw_member', 1)->where(function ($query) {
            $query->whereOr('withdraw_erc_sxf', '>', 0)->whereOr('withdraw_trc_sxf', '>=', 0)->whereOr('withdraw_omni_sxf', '>', 0);
        })->field('id,title')->order('base', 'desc')->select();
        if ($coin_id == 0) {
            $coin_id = $this->pro_model->where('base', 1)->value('id');
        }
        $plist = $this->pro_model->where('id', $coin_id)->where('status', 1)->field('id,title,withdraw_erc_sxf,withdraw_trc_sxf,withdraw_omni_sxf,withdraw_num_max,withdraw_num_sxf')->find();
        $wlist = $this->wallet_model->where('product_id', $coin_id)->where('uid', $this->memberInfo->id)->field('id,ex_money,withdraw_erc_address,withdraw_trc_address,withdraw_omni_address')->find();
        // dump($wlist);die;
        $this->assign(['pro' => $pro, 'coin_id' => $coin_id, 'plist' => $plist, 'wlist' => $wlist]);
        return $this->fetch();
    }
}
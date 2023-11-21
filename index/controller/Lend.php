<?php 
/*
 * @Author: Fox Blue
 * @Date: 2021-06-28 14:41:28
 * @LastEditTime: 2021-10-08 15:57:51
 * @Description: Forward, no stop
 */
namespace app\index\controller;

use app\common\controller\IndexController;
use think\App;
use think\facade\Env;
use app\common\FoxKline;

class Lend extends IndexController
{
    public function __construct(App $app)
    {
        parent::__construct($app);
        $this->model = new \app\admin\model\OrderLend();
        $this->wallet_model = new \app\admin\model\MemberWallet();
        $this->pro_model = new \app\admin\model\ProductLists();
    }
    
    public function index()
    {
        $this->assign('finance_img',$this->memberInfo['finance_img']);
        
        $order_count = $this->model->where('uid',session('member.id'))->count();
        $this->assign('order_count',$order_count);
        
        $web_name = lang('lend.lend_title').'-'.$this->web_name;
        $this->assign(['web_name'=>$web_name,'topmenu'=>'lend']);
        return $this->fetch();
    }
    
    public function upload_finance(){
        if(request()->isPost()){
            $m_user = new \app\admin\model\MemberUser();
            $post = $this->request->post();
            $indata = [];
            $indata['finance_img'] = $post['finance_img'];
            if(empty($indata['finance_img'])){
                return $this->error(lang('lend.lend_finance_upload'));
            }
            $check = request()->checkToken('__token__');
            if(false === $check) {
                // return $this->error(lang('public.do_fail'));
            }
            $save = $m_user->where('id',session('member.id'))->save($indata);
            return $this->success(lang('lend.lend_finance_succeed'),[],(string)url('lend/index'));
        }
    
    }
    
    
    public function otc_loan_history()
    {
        $now_order = $this->model->where('uid',session('member.id'))->where('status','in',[1,3])->find();
        if($now_order)$now_order['end_time'] = $now_order['end_time'] ? date('Y-m-d H:i:s',$now_order['end_time']):'?';
        $this->assign('now_order',is_null($now_order)?[]:$now_order);
        
        $web_name = lang('lend.lend_title').'-'.$this->web_name;
        $this->assign(['web_name'=>$web_name,'topmenu'=>'lend']);
        return $this->fetch();
    }
    
    public function json_otc_loan_history()
    {
        $all_order = $this->model->where('uid',session('member.id'))->order('id desc')->select()->toArray();
        foreach ($all_order as &$val){
            $val['end_time'] = $val['end_time'] ? date('Y-m-d H:i:s',$val['end_time']) :'?';
        }
        return json(['code'=>1,'data'=>$all_order]);
    }
    
    
    public function otc_loan_amount()
    {
        
        $web_name = lang('lend.lend_title').'-'.$this->web_name;
        $this->assign(['web_name'=>$web_name,'topmenu'=>'lend']);
        return $this->fetch();
    }
    
    public function otc_loan_now()
    {
        $time = sysconfig('lend','lend_time');
        $time = explode('|',$time);
        $lendTime = [];
        foreach ($time as $val){
            $lendTime[] = ["title"=>$val.lang('time_format.day'),"hasHref"=>false,"num"=>$val,"hrefType"=>"_self","rightFont_imgClass"=>"icon-youbian"];
        }
        
        $web_name = lang('lend.lend_title').'-'.$this->web_name;
        $this->assign(['web_name'=>$web_name,'topmenu'=>'lend']);
        $this->assign('day',$time[0]);
        $this->assign('lendTime',$lendTime);
        return $this->fetch();
    }
    
    
    public function addorder()
    {
        if(request()->isPost()){
            $post = $this->request->post(null,'','trim');
            
            if($this->memberInfo['finance_img']==''){
                return $this->error(lang('lend.lend_finance_upload'));
            }
            
            $indata['uid'] = session('member.id');
            $indata['lend_number'] = $post['amount'];
            $indata['reback_number'] = $post['amount'];
            $indata['lend_time'] = $post['days_num'];
            $indata['lend_interest'] = sysconfig('lend','lend_interest');
            $indata['lend_overdue_interest'] = sysconfig('lend','lend_overdue_interest');
            $indata['create_time'] = time();
            $indata['order_number'] = 'LD_'.random_code(16);
            if($indata['lend_number'] <= 0 ){//|| $indata['lend_number']>sysconfig('lend','lend_max')
                return $this->error(lang('lend.lend_confirm_money'));
            }
            
            if($this->model->where('uid',session('member.id'))->where('status','not in',[2,4])->count()){
                return $this->error(lang('lend.lend_hasorder'));
            }
            
            try {
                $save = $this->model->save($indata);
            }catch (\Exception $e) {
                return $this->error(lang('public.do_fail'));
            }
            
            if($save ){
                return json(['code'=>1,'msg'=>  lang('lend.lend_submit_succeed')]);
            }else{
                return $this->error(lang('public.do_fail'));
            }
        }
    }
    
    public function otc_loan_orderdetails()
    {
        $id = $_GET['id'];
        $now_order = $this->model->find($id);
        $now_order['end_time'] = $now_order['end_time'] ? date('Y-m-d H:i:s',$now_order['end_time']):'?';
        $now_order['confirm_time'] = $now_order['confirm_time'] ? date('Y-m-d H:i:s',$now_order['confirm_time']):'?';
        $this->assign('now_order',$now_order);
        
        $web_name = lang('lend.lend_title').'-'.$this->web_name;
        $this->assign(['web_name'=>$web_name,'topmenu'=>'lend']);
        return $this->fetch();
    }
    
    
    public function otc_repay_now()
    {
        $id = $_GET['id'];
        $now_order = $this->model->find($id);
        
        $now_order['end_time'] = $now_order['end_time'] ? date('Y-m-d H:i:s',$now_order['end_time']):'?';
        $now_order['confirm_time'] = $now_order['confirm_time'] ? date('Y-m-d H:i:s',$now_order['confirm_time']):'?';
        $this->assign('now_order',$now_order);
        
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
        
        
        $web_name = lang('lend.lend_title').'-'.$this->web_name;
        $this->assign(['web_name'=>$web_name,'topmenu'=>'lend']);
        
        return $this->fetch();
    }
    
    
    //还款
    public function repay()
    {
        if(request()->isPost()){
            $post = $this->request->post();
            $indata = [];
            $indata['up_pic'] = $post['recharge_pic'];
            if(empty($indata['up_pic'])){
                return $this->error(lang('finance.recharge_pic_check'));
            }
            $indata['status'] = 5;
            $check = request()->checkToken('__token__');
            if(false === $check) {
                return $this->error(lang('public.do_fail'));
            }
            $save = $this->model->where('id',$post['id'])->save($indata);
            return $this->success(lang('lend.lend_submit_succeed'),[],(string)url('lend/otc_loan_history'));
        }
    }
        
}


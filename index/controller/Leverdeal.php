<?php 
/*
 * @Author: Fox Blue
 * @Date: 2021-06-28 14:41:28
 * @LastEditTime: 2021-08-10 15:31:27
 * @Description: Forward, no stop
 */
namespace app\index\controller;

use app\common\controller\IndexController;
use think\App;
use think\facade\Env;
use app\common\FoxKline;
use think\facade\Db;

class Leverdeal extends IndexController
{
    
    public function index()
    {
        $productwhere[] = ['types','like','%2%'];
        $productwhere[] = ['status','=','1'];
        $productwhere[] = ['base','=','0'];
        $product = \app\admin\model\ProductLists::where($productwhere)->order('sort','desc')->select();
        $this->assign('product',$product);
        $web_name = lang('leverdeal.title').'-'.$this->web_name;
               $walletlist = Db::name('member_wallet')
        ->alias('a')
        ->join('member_user m ','m.id= a.uid')
        ->where('a.uid',session('member.id'))
        ->join('product_lists p ','p.id= a.product_id')
        ->where('p.types','like','%2%')
        ->where('a.product_id','=','6')
        ->order('p.base','desc')
        ->field('a.le_money')
        ->find();
        $this->assign(['web_name'=>$web_name,'topmenu'=>'leverdeal','usdt_money'=>$walletlist['le_money']]);
        return $this->fetch();
    }
    
    public function get_play_time()
    {
        if(request()->isPost()){
            $post = $this->request->post(null,'','trim');
            $code = $post['code'];
            $info = [];
            if($code){
                $play_time = \app\admin\model\ProductLists::where('code',$code)->value('le_play_time');
                $info['play_time'] = explode(',',$play_time);
                return json(['code'=>1,'data'=>$info]);
            }
            return json(['code'=>0]);
        }
        return json(['code'=>0]);
    }
    //合约订单
    public function orderdo()
    {
        if(request()->isPost()){
            $post = $this->request->post(null,'','trim');
            $code = $post['code'];
            $money = $post['money'];
            $proInfo = \app\admin\model\ProductLists::where('code',$code)->find();
            
            if(!$proInfo){
                return $this->error(lang('public.do_fail'));
            }
            $indata['close_price'] = $proInfo['last_price'];
            $indata['bzj_price'] = $post['bzj_price'];
            $indata['play_time'] = $post['play_time'];
            $indata['account'] = $post['account'];
            $indata['buy_price'] = $post['buy_price'];
            $indata['now_price'] = $indata['buy_price'];
            $indata['play_rate'] = $proInfo['le_sx_fee'];
            $indata['product_id'] = $proInfo['id'];
            $indata['stop_profit'] = 0;
            $indata['stop_loss'] = 0;
            $indata['style'] = $post['style'];
            $indata['type'] = 0;
            $indata['uid'] = session('member.id');
            $indata['status'] = 1;//持仓中
            if($indata['account'] <= 0){
                return $this->error(lang('leverdeal.check_laccount_err'));
            }
            $max_buy_num = bc_div($indata['account'],$indata['play_time']);
            $user_wallet = \app\admin\model\MemberWallet::where('product_id',6)->where('uid',$this->memberInfo['id'])->field('id,le_money')->find();
            //$now_le_money = $user_wallet['le_money']-$post['bzj_price']-$post['bzj_price']*0.05;
            $shouxufei = $post['buy_price']*$post['account']*0.0005;
            $now_le_money = $user_wallet['le_money']-$post['bzj_price']-$shouxufei;
            if(bc_sub($money,$max_buy_num) < 0 || $now_le_money<0){
                return $this->error(lang('leverdeal.check_le_money_noenough',['tit'=>'USDT','num'=>floatVal($indata['account'])]));
            }
            $indata['price_account'] = $max_buy_num;//实耗量
            $rate = bc_mul(bc_mul($indata['buy_price'],$indata['account']),$indata['play_rate']);
            $coin_rate = bc_div($rate,$indata['buy_price']);//化为币
            $indata['rate_account'] = $shouxufei;//手续费
            $indata['title'] = $proInfo['title'];
            $this->model = new \app\admin\model\OrderLeverdeal();
            $this->modellog = new \app\admin\model\MemberWalletLog();
            $this->modelwallet = new \app\admin\model\MemberWallet();
            try {
                $save = $this->model->save($indata);
                if($save){
                    $lastId = $this->model->id;
                    //$now_le_money = $user_wallet['le_money']-$post['bzj_price']-$post['bzj_price']*0.05;
                    $prowallet = $this->modelwallet->where('product_id',6)->where('uid',$this->memberInfo['id'])->update(['le_money'=>$now_le_money]);
                    if($prowallet){
                        $logdata['account'] = $indata['account'];
                        $logdata['wallet_id'] = $user_wallet['id'];
                        $logdata['product_id'] = 6;
                        $logdata['uid'] = session('member.id');
                        $logdata['is_test'] = session('member.is_test');
                        $logdata['before'] = $user_wallet['le_money'];
                        $logdata['after'] = $now_le_money;
                        $logdata['account_sxf'] = 0;
                        $logdata['all_account'] = $indata['rate_account'];
                        $logdata['type'] = 5;//合约订单
                        $logdata['title'] = $proInfo['title'];
                        $logdata['order_type'] = 1;//手续费
                        $logdata['order_id'] = $lastId;
                        $logdata['uratio'] = $proInfo['u_ratio'];
                        $inlog = $this->modellog->save($logdata);
                    }
                }
            }catch (\Exception $e) {
                return $this->error(lang('public.do_fail'));
            }
            if($save && $inlog){
                return json(['code'=>1,'msg'=>lang('leverdeal.order_success'),'id'=>$lastId]);
            }else{
                return $this->error(lang('public.do_fail'));
            }
        }
    }

    //修改止盈止损
    public function set_buy_end_stop()
    {
        $post = $this->request->post(null,'');

        $id = $post['id'];
        if(!$id){
            return json(['code'=>0,'msg'=>lang('leverdeal.order_status_question')]);
        }
        $info = \app\admin\model\OrderLeverdeal::where('id',$id)->find();
        if(!$info){
            return json(['code'=>0,'msg'=>lang('leverdeal.order_status_question')]);
        }
        if ($info['style']==1){
            if ($post['stop_profit']<=$info['buy_price'] ||$post['stop_profit']<=$info['now_price']){
                return json(['code'=>0,'msg'=>lang('zyzs.买入(做多)止盈价不能低于开仓价和当前价'),'id'=>$id]);
            }
            if ($post['stop_loss']>=$info['buy_price'] ||$post['stop_loss']>=$info['now_price']){
                return json(['code'=>0,'msg'=>lang('zyzs.买入(做多)止损价不能高于开仓价和当前价'),'id'=>$id]);
            }
        }else{
            if ($post['stop_profit']>=$info['buy_price'] ||$post['stop_profit']>=$info['now_price']){
                return json(['code'=>0,'msg'=>lang('zyzs.买出(做空)止盈价不能高于开仓价和当前价'),'id'=>$id]);
            }
            if ($post['stop_loss']<=$info['buy_price'] ||$post['stop_loss']<=$info['now_price']){
                return json(['code'=>0,'msg'=>lang('zyzs.买出(做空)止损价不能低于开仓价和当前价'),'id'=>$id]);
            }
        }
        $res=\app\admin\model\OrderLeverdeal::where('id',$id)->update([
            'stop_profit'=>$post['stop_profit'],
            'stop_loss'=>$post['stop_loss'],
        ]);

        if($res ){
            return json(['code'=>1,'msg'=>lang('zyzs.修改完成'),'id'=>$id]);
        }
        return json(['code'=>0,'msg'=>lang('leverdeal.order_status_question')]);
    }

    public function order_this()
    {
        if(request()->isPost()){
            $post = $this->request->post(null,'','int');
            $id = $post['id'];
            if($id){
                $info = \app\admin\model\OrderLeverdeal::where('id',$id)->find();
                if(!$info){
                    return json(['code'=>0,'msg'=>lang('leverdeal.order_status_question')]);
                }
                if($info['status'] <> 1){
                    return json(['code'=>0,'msg'=>lang('leverdeal.order_status_question')]);
                }
                try {
                    $this->model = new \app\admin\model\OrderLeverdeal();
                    $save = $this->model->where('id',$info['id'])->update(['status'=>2,'is_lock'=>1,'close_price'=>$info['now_price']]);
                    if($save){
                        $this->modellog = new \app\admin\model\MemberWalletLog();
                        $this->modelwallet = new \app\admin\model\MemberWallet();
                        $user_wallet = \app\admin\model\MemberWallet::where('product_id',6)->where('uid',$this->memberInfo['id'])->field('id,le_money')->find();
                        $order = \app\admin\model\OrderLeverdeal::where('id',$id)->find();
                        
                        //$yingkui = ($order['now_price']-$order['close_price'])*$order['uratio']*$order['account'];
                        // $now_le_money = $user_wallet['le_money']+$order['win_account']*$order['close_price']+$order['bzj_price'];
                        $now_le_money = $user_wallet['le_money']+$order['bzj_price'];
                        
                        if($now_le_money <= 0){
                            $user_wallet['le_money'] = 0;
                        }else{
                            $user_wallet['le_money'] = $now_le_money;
                        }
                        
                        
                        if($user_wallet->save()){
                            $logdata['account'] = $info['win_account'];
                            $logdata['wallet_id'] = $user_wallet['id'];
                            $logdata['product_id'] = $info['product_id'];
                            $logdata['uid'] = session('member.id');
                            $logdata['is_test'] = session('member.is_test');
                            $logdata['before'] = $user_wallet['le_money'];
                            $logdata['after'] = $now_le_money;
                            $logdata['account_sxf'] = 0;
                            $logdata['all_account'] = $info['win_account'];
                            $logdata['type'] = 5;//合约订单
                            $logdata['title'] = $info['title'];
                            $logdata['order_type'] = $info['is_win']+10;//手动平仓
                            $logdata['order_id'] = $id;
                            $inlog = $this->modellog->save($logdata);
                        }
                    }
                }catch (\Exception $e) {
                    return $this->error(lang('public.do_fail'));
                }
                if($save && $inlog){
                    return json(['code'=>1,'msg'=>lang('leverdeal.order_status_success'),'id'=>$id]);
                }
                return json(['code'=>0,'msg'=>lang('leverdeal.order_status_question')]);
            }
            return json(['code'=>0,'msg'=>lang('leverdeal.order_status_question')]);
        }
        return json(['code'=>0,'msg'=>lang('leverdeal.order_status_question')]);
    }

    public function findorder()
    {
        if(request()->isPost()){
            $post = $this->request->post(null,'','int');
            $id = $post['id'];
            if($id){
                $info = \app\admin\model\OrderLeverdeal::where('id',$id)->where('status',1)->find();
                if($info){
                    if($info['style']==1){
                        $info['yingkui'] = ($info['now_price']-$info['buy_price'])*$info['uratio']*$info['account'];
                    }else{
                        $info['yingkui'] = ($info['buy_price']-$info['now_price'])*$info['uratio']*$info['account'];
                    }
                    
                    return json(['code'=>1,'data'=>$info]);
                }
                return json(['code'=>0]);
            }
            return json(['code'=>0]);
        }
        return json(['code'=>0]);
    }

    public function lista()
    {
        if(request()->isPost()){
            $post = $this->request->post(null,'','trim');
            $page = $post['page'];
            $code = $post['code'];
            $product_id = \app\admin\model\ProductLists::where('code',$code)->value('id');
            $limit = 8;
            $this->m_order = new \app\admin\model\OrderLeverdeal();
            $list = $this->m_order->where('uid',$this->memberInfo['id'])
                ->where('status',1)
                ->where('product_id',$product_id)
                ->page($page, $limit)
                ->order('create_time','desc')
                ->select();
            $count = $this->m_order->where('uid',$this->memberInfo['id'])
                ->where('status',1)
                ->where('product_id',$product_id)
                ->count('id');
            if($list){
                foreach($list as $k => $v){
                    if($v['style']==1){
                        $list[$k]['yingkui'] = ($v['now_price']-$v['buy_price'])*$v['uratio']*$v['account'];
                    }else{
                        $list[$k]['yingkui'] = ($v['buy_price']-$v['now_price'])*$v['uratio']*$v['account'];
                    }
                    $list[$k]['ostyle'] = lang('leverdeal.style_'.$v['style']);
                }
            }
            return json(['code'=>1,'data'=>$list,'pages'=>floor($count/$limit)]);
        }
    }

    public function listb()
    {
        if(request()->isPost()){
            $post = $this->request->post(null,'','trim');
            $page = $post['page'];
            $code = $post['code'];
            $product_id = \app\admin\model\ProductLists::where('code',$code)->value('id');
            $limit = 8;
            $this->m_order = new \app\admin\model\OrderLeverdeal();
            $list = $this->m_order->where('uid',$this->memberInfo['id'])
                ->where('status',2)
                ->where('product_id',$product_id)
                ->page($page, $limit)
                ->order('create_time','desc')
                ->select();
            $count = $this->m_order->where('uid',$this->memberInfo['id'])
                ->where('status',2)
                ->where('product_id',$product_id)
                ->count('id');
            if($list){
                foreach($list as $k => $v){
                    if($v['style']==1){
                        $list[$k]['yingkui'] = ($v['now_price']-$v['buy_price'])*$v['uratio']*$v['account'];
                    }else{
                        $list[$k]['yingkui'] = ($v['buy_price']-$v['now_price'])*$v['uratio']*$v['account'];
                    }
                    $v['is_win'] = $list[$k]['yingkui']>=0?1:2;
                    $list[$k]['ostyle'] = lang('leverdeal.style_'.$v['style']);
                    $list[$k]['owin'] = lang('leverdeal.win_'.$v['is_win']);
                }
            }
            return json(['code'=>1,'data'=>$list,'pages'=>floor($count/$limit)]);
        }
    }

}


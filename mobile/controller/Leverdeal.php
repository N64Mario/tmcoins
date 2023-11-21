<?php 
/*
 * @Author: Fox Blue
 * @Date: 2021-06-28 14:41:28
 * @LastEditTime: 2021-08-19 22:36:37
 * @Description: Forward, no stop
 */
namespace app\mobile\controller;

use app\admin\model\ProductLists;
use app\common\controller\MobileController;
use think\App;
use think\facade\Env;
use app\common\FoxKline;

class Leverdeal extends MobileController
{
    
    public function index()
    {
        $productwhere[] = ['types','like','%2%'];
        $productwhere[] = ['status','=','1'];
        $productwhere[] = ['base','=','0'];
        $product = \app\admin\model\ProductLists::where($productwhere)->order('sort','desc')->select();
        $this->assign('product',$product);
        $web_name = lang('leverdeal.title').'-'.$this->web_name;
        $this->assign(['web_name'=>$web_name,'topmenu'=>'leverdeal']);
        $this->assign(['footmenu'=>'leverdeal']);
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

    public function orderdo()
    {
        if(request()->isPost()){
            $post = $this->request->post(null,'','trim');
            $code = $post['code'];
            $money = $post['money'];
            $account = $post['account'];
            $zhangshu = $account;
            $post['account'] = 1000*$zhangshu/$post['buy_price'];
            $proInfo = \app\admin\model\ProductLists::where('code',$code)->find();
            if(!$proInfo){
                return $this->error(lang('public.do_fail'));
            }
            $indata['close_price'] = $proInfo['last_price'];
            $indata['bzj_price'] = $zhangshu * 1000;
            $indata['play_time'] = 1;
            $indata['account'] = $post['account'];
            $indata['buy_price'] = $post['buy_price'];
            $indata['now_price'] = $indata['buy_price'];
            $indata['play_rate'] = 0.003;
            $indata['product_id'] = $proInfo['id'];
            $indata['stop_profit'] = 0;//默认
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
            $now_le_money = $user_wallet['le_money']-$post['bzj_price']-$post['bzj_price']*0.003;
            if($now_le_money<0){
                return $this->error(lang('leverdeal.check_le_money_noenough',['tit'=>'USDT','num'=>floatVal($indata['account'])]));
            }
            $indata['price_account'] = $max_buy_num;//实耗量
            $rate = bc_mul(bc_mul($indata['buy_price'],$indata['account']),$indata['play_rate']);
            $coin_rate = bc_div($rate,$indata['buy_price']);//化为币
            $indata['rate_account'] = $coin_rate;//手续费,以币为单位
            $indata['zhangshu'] = $zhangshu;
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
                        $logdata['product_id'] = $proInfo['id'];
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

    //合约订单平仓
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
                    $save = $this->model->where('id',$info['id'])->update(['status'=>2,'is_lock'=>1,'close_price'=>$info['now_price'],'update_time'=>time()]);
                    if($save){
                        $this->modellog = new \app\admin\model\MemberWalletLog();
                        $this->modelwallet = new \app\admin\model\MemberWallet();
                        $user_wallet = \app\admin\model\MemberWallet::where('product_id',6)->where('uid',$this->memberInfo['id'])->find();
                        $order = \app\admin\model\OrderLeverdeal::where('id',$id)->find();
                        
                        //$yingkui = ($order['now_price']-$order['close_price'])*$order['uratio']*$order['account'];
                        // $now_le_money = $user_wallet['le_money']+$order['win_account']*$order['close_price']+$order['bzj_price'];
                        $now_le_money = $user_wallet['le_money']+$order['bzj_price'] + $order['price_change'];
                        
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

    public function detail() {
        $info = \app\admin\model\OrderLeverdeal::where('id',$this->request->get('id'))->find();
        //开仓时，写死了
        $info['shouxufei'] = $info['bzj_price'] * 0.003;
        $product = ProductLists::where("id",$info['product_id'])->find();
        $this->assign(['info'=>$info,'product'=>$product]);
        return $this->fetch();
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
    
    

    public function get_share(){
        $id = $_POST['id'];//$post['id'];
        $info = \app\admin\model\OrderLeverdeal::where('id',$id)->find();
        $proInfo = \app\admin\model\ProductLists::where('id',$info['product_id'])->find();
        
        $this->model = new \app\admin\model\MemberUser();
        $this->member = $this->model->where('id',session('member.id'))->find()->ToArray();
        if($info['style']==1){
            $yk = round(($info['now_price']-$info['buy_price'])*$info['uratio']*$info['account'],2);
        }else{
            $yk = round(($info['buy_price']-$info['now_price'])*$info['uratio']*$info['account'],2);
        }
        
        //$ratio = round(($info['win_account']*$info['close_price'])/$info['bzj_price']*100,2); 
        $ratio = round($yk/$info['bzj_price']*100,2); 
        
        $yk = $yk>0?'+'.$yk:$yk;
        $ratio = ($ratio>0?'+'.$ratio:$ratio).'%';
        
        $left = [2=>260,3=>230,4=>230,5=>200,6=>160,7=>140,8=>100];
        $left2 = [2=>260,3=>250,4=>230,5=>210,6=>200,7=>180,8=>130,9=>120,10=>100,11=>80];
        
        $invite_code_url = server_url().(string)url('wicket/register',['code'=>$this->member['invite_code']]);
        $invite_code_img = phpqrcode( $invite_code_url,'invite_code_'.$this->member['id']);
        $config = array(
            'image'=>array(
              array(
                'url' =>$invite_code_img,
                'stream'=>0,
                'left'=>238,
                'top'=>849,
                'right'=>0,
                'bottom'=>0,
                'width'=>145,
                'height'=>145,
                'opacity'=>100
              )
            ),
            'text' =>array(
              array(
                'fontColor' =>'70,98,217',
                'fontSize' => 30,
                'fontPath' => app()->getRootPath() .'public/static/mobile/fonts/youtoutihei.ttf',
                'text'=> strtoupper($info['title']).'/USDT',
                'left'=>200,
                'top'=>600,
              ),
              array(
                'fontColor' =>'70,98,217',
                'fontSize' => 60,
                'fontPath' => app()->getRootPath() .'public/static/mobile/fonts/youtoutihei.ttf',
                'text'=> $yk,
                'left'=>$left[strlen($yk)],
                'top'=>690,
              ),
              array(
                'fontColor' =>'70,98,217',
                'fontSize' => 60,
                'fontPath' => app()->getRootPath() .'public/static/mobile/fonts/youtoutihei.ttf',
                'text'=> $ratio,
                'left'=>$left2[strlen($ratio)],
                'top'=>790,
              ),
              /*
              array(
                'fontColor' =>'0,128,0',
                'fontSize' => 18,
                'fontPath' => app()->getRootPath() .'public/static/mobile/fonts/youtoutihei.ttf',
                'text'=> $info['title'].'USDT',
                'left'=>100,
                'top'=>755,
              ),
              array(
                'fontColor' =>'0,128,0',
                'fontSize' => 17,
                'fontPath' => app()->getRootPath() .'public/static/mobile/fonts/youtoutihei.ttf',
                'text'=> '',
                'left'=>110,
                'top'=>790,
              ),
              
              array(
                'fontColor' =>'0,128,0',
                'fontSize' => 18,
                'fontPath' => app()->getRootPath() .'public/static/mobile/fonts/youtoutihei.ttf',
                'text'=> round($proInfo['open'],3),
                'left'=>260,
                'top'=>755,
              ),
              array(
                'fontColor' =>'0,128,0',
                'fontSize' => 17,
                'fontPath' => app()->getRootPath() .'public/static/mobile/fonts/youtoutihei.ttf',
                'text'=> 'open',
                'left'=>285,
                'top'=>790,
              ),
              
              array(
                'fontColor' =>'0,128,0',
                'fontSize' => 18,
                'fontPath' => app()->getRootPath() .'public/static/mobile/fonts/youtoutihei.ttf',
                'text'=> round($proInfo['last_price'],3),
                'left'=>410,
                'top'=>755,
              ),
              array(
                'fontColor' =>'0,128,0',
                'fontSize' => 17,
                'fontPath' => app()->getRootPath() .'public/static/mobile/fonts/youtoutihei.ttf',
                'text'=> 'new',
                'left'=>450,
                'top'=>790,
              )*/
            ),
            'background'=>app()->getRootPath() . 'public/upload/share/share_bg.jpg'          //背景图
        );
        
        $filename = app()->getRootPath() . 'public/upload/share/share_'.$id.'.jpg';
        createShare($config,$filename);
        $file = server_url().'/upload/share/share_'.$id.'.jpg?time='.time();
        echo  $file;
    }

}


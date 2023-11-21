<?php 
/*
 * @Author: Fox Blue
 * @Date: 2021-06-01 16:41:46
 * @LastEditTime: 2021-08-21 13:19:10
 * @Description: Forward, no stop
 */
namespace app\api\controller;

use app\common\controller\ApiController;
use think\App;
use think\facade\Env;
use Udun\Dispatch\Pay;

class Index extends ApiController
{
    public function index()
    {
        $this->data['product'] = \app\admin\model\ProductLists::where('status',1)->where('base',0)->where('ishome',1)->order('sort','desc')->select();
        return $this->result($this->data,1,'获取成功');
    }

    public function pay_notify()
    {

        $txt=$_SERVER['DOCUMENT_ROOT'].'/pay/pay_notify.txt';
//        $txt=$_SERVER['DOCUMENT_ROOT'].'/pay/pay4_notify.txt';
        $msg=date('Y-m-d H:i:s').'到访'."\n";
        $a=   file_put_contents($txt, $msg, FILE_APPEND);
//        接受Post值
//        $postdata = file_get_contents('php://input');
//       file_put_contents($txt, $postdata."\n", FILE_APPEND);
//        $params = json_decode($postdata, true);  //接受参数
//        dump($params);
        $postdata = $_REQUEST;
        $postdata['body']=stripslashes($postdata['body']);
        file_put_contents($txt, stripslashes(json_encode($postdata))."\n", FILE_APPEND);
        $params=$postdata;



        require app()->getRootPath(). '/vendor/udun-sdk-php-master/wang/Pay.php';
        $result =  new Pay();
        $params2=$params;
//        $params2['body']='['. $params2['body'].']';
        $sign=$result->sign($params2);
        if ($sign!=$params['sign']){
            file_put_contents($txt, 'sign错误'."\n", FILE_APPEND);
            exit('sign错误');
        }
        //file_put_contents($txt, '111'."\n", FILE_APPEND);

        //逻辑
        $params['body']=stripslashes($params['body']);
        $body=json_decode($params['body'],1);
        $amount=$body['amount']/pow(10,$body['decimals']);
        $MemberWalletLog = new \app\admin\model\MemberWalletLog();
        $recharge= $MemberWalletLog->where(
            ['address'=>$body['address'],
                'account'=>$amount,
                'type'=>1,
                'status'=>1,
            ])->find();
        if (!$recharge){
            file_put_contents($txt, '没找到相应订单'."\n", FILE_APPEND);
            exit('没找到相应订单');
        }
        $update=[];
        $update['do_time'] = time();;
        $update['status']=2;
        $recharge['ex_money'] = \app\admin\model\MemberWallet::where('id',$recharge['wallet_id'])->value('ex_money');

        $update['before']=$recharge['ex_money'];
        $after = bc_add($recharge['ex_money'],$recharge['all_account']);
        $update['after'] = $after;

        $recharge->save($update);

        $modelwallet = new \app\admin\model\MemberWallet();
        $modelwallet->update(['ex_money'=>$after],['id'=>$recharge['wallet_id']]);
        file_put_contents($txt, $recharge['wallet_id'].'订单成功'."\n", FILE_APPEND);
        exit('success');


    }

}


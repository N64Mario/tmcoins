<?php
/*
 * @Author: Fox Blue
 * @Date: 2021-07-02 11:57:21
 * @LastEditTime: 2021-08-20 14:11:06
 * @Description: Forward, no stop
 */

namespace app\mobile\controller;

use app\common\controller\MobileController;
use app\mobile\service\TriggerService;
use think\db\Query;
use think\facade\Cache;
use app\admin\model\SystemUploadfile;
use EasyAdmin\upload\Uploadfile;
use app\common\FoxKline;
use app\common\FoxCommon;
use think\facade\Db;

class Ajax extends MobileController
{

    /**
     * @Title: 验证会员
     */
    public function check_member()
    {
        if (!session('member')) {
            return $this->error(lang('public.do_login'));
        }
    }


    /**
     * 切换语言包
     */
    public function lang()
    {
        $lang = $this->request->param('lang');
        if (!empty($lang) && in_array($lang, $this->allow_lang_list)) {
            TriggerService::setLang($lang);
            return json(['code' => 1]);
        }
        return json(['code' => 0]);
    }

    public function theme()
    {
        $theme = $this->request->param('theme');
        if (!empty($theme)) {
            TriggerService::updateTheme($theme);
            return json(['code' => 1]);
        }
        return json(['code' => 0]);
    }

    public function get_product()
    {
        if (request()->isPost()) {
            $code = request()->post('code/s', '', "trim");
            if ($code) {
                $pro = \app\admin\model\ProductLists::where('code', $code)->where('status', 1)->field('open,close,high,low,change,volume')->find();
                if ($pro) {
                    $pro['open'] = (float)$pro['open'];
                    $pro['close'] = (float)$pro['close'];
                    $pro['change'] = (float)$pro['change'];
                    $pro['high'] = (float)$pro['high'];
                    $pro['low'] = (float)$pro['low'];
                    $pro['vol'] = number_format($pro['volume'], 4);
                    $pro['volume'] = number_format($pro['volume'], 4);
                    $pro['usd'] = FoxKline::get_me_price_usdt_to_usd($pro['close']);
                    return json(['code' => 1, 'data' => $pro]);
                }
                return json(['code' => 0]);
            }
        }
        return json(['code' => 0]);
    }

    public function findcpm()
    {
        if (request()->isPost()) {
            $type = request()->post('type/s', '', "trim");
            $site = request()->post('site/s', '', "trim");
            $cmpwin = \app\admin\model\CpmBanner::where('status', 1)->where('type', $type)->where('name', $site)->where('lang', $this->lang)->order('update_time', 'desc')->value('content');
            if ($cmpwin) {
                return json(['code' => 1, 'data' => $cmpwin]);
            }
            return json(['code' => 0]);
        }
        return json(['code' => 0]);
    }

    /**
     * 期权订单结算
     * @return \think\response\Json|void
     */
    public function seconds()
    {
        $list = Db::name("order_seconds")->where(["op_status" => 0])->select()->toArray();
        $arr = "";
        foreach ($list as $k => $v) {
            if (!empty($v) && $v['orders_time'] <= time()) {
                $id = $v['id'];
                $m_order = new \app\admin\model\OrderSeconds();
                $order = $m_order->where('id', $id)->find();
                $m_product = new \app\admin\model\ProductLists();
                $m_wallet = new \app\admin\model\MemberWallet();
                $m_user = new \app\admin\model\MemberUser();
                $m_log = new \app\admin\model\MemberWalletLog();
                $is_test = $m_user->where('id', $order['uid'])->value('is_test');
                $productBase = $m_product->where('base', 1)->field('id,title')->find();
                $pro = $m_product->where('id', $order['product_id'])->field('id,close,op_kong_min,op_kong_max,op_sx_fee,op_order_kong')->find();
                $user_wallet = $m_wallet->where('product_id', $productBase['id'])->where('uid', $order['uid'])->field('id,product_id,op_money')->find();
                $op_k_num = FoxCommon::kong_generateRand($pro['op_kong_min'], $pro['op_kong_max']);
                $u_op_order_kong = $m_user->where('id', $order['uid'])->value('op_order_kong');
                $op_order_kong = 50;
                if ($pro['op_order_kong'] > 0) {
                    $op_order_kong = $pro['op_order_kong'];
                }
                if ($u_op_order_kong > 0) {
                    $op_order_kong = $u_op_order_kong;
                }
                $new_rand = mt_rand(0, 100);
                if ($new_rand <= $op_order_kong) { //赢
                    if ($order['op_style'] == 1) {//买涨
                        $odata['end_price'] = bc_add($order['start_price'], $op_k_num);
                    } else if ($order['op_style'] == 2) {//买跌
                        $odata['end_price'] = bc_sub($order['start_price'], $op_k_num);
                    }
                    $odata['update_time'] = time();
                    $odata['is_win'] = 1;
                    $odata['op_status'] = 1;
                    $odata['true_fee'] = bc_add($order['op_number'], bc_mul($order['op_number'], $order['play_prop'] / 100));
                    $odata['sx_fee'] = bc_mul($odata['true_fee'], $pro['op_sx_fee']);
                    $odata['all_fee'] = bc_sub($odata['true_fee'], $odata['sx_fee']);
                    $now_money = bc_add($user_wallet['op_money'], $odata['all_fee']);
                    if ($m_order->where('id', $order['id'])->update($odata)) {
                        $m_wallet->where('id', $user_wallet['id'])->update(['op_money' => $now_money]);
                        $lgdata['account'] = $order['op_number'];
                        $lgdata['wallet_id'] = $user_wallet['id'];
                        $lgdata['product_id'] = $user_wallet['product_id'];
                        $lgdata['uid'] = $order['uid'];
                        $lgdata['is_test'] = $is_test;
                        $lgdata['before'] = $user_wallet['op_money'];
                        $lgdata['after'] = $now_money;
                        $lgdata['account_sxf'] = $odata['sx_fee'];
                        $lgdata['all_account'] = $odata['all_fee'];
                        $lgdata['type'] = 6;
                        $lgdata['title'] = $productBase['title'];
                        $lgdata['order_type'] = 2;//赢返
                        $lgdata['order_id'] = $order['id'];
                        $m_log->save($lgdata);
                    }
                } else {
                    if ($order['op_style'] == 1) {//买涨
                        $odata['end_price'] = bc_sub($order['start_price'], $op_k_num);
                    } else if ($order['op_style'] == 2) {//买跌
                        $odata['end_price'] = bc_add($order['start_price'], $op_k_num);
                    }
                    $odata['update_time'] = time();
                    $odata['is_win'] = 2;
                    $odata['op_status'] = 1;
                    $money = bc_sub($order['op_number'], bc_mul($order['op_number'], $order['play_prop'] / 100));
                    $odata['true_fee'] = bc_mul($order['op_number'], $order['play_prop'] / 100);
                    $odata['sx_fee'] = 0;
                    $odata['all_fee'] = $odata['true_fee'];
                    $now_money = bc_add($user_wallet['op_money'], $money);
                    $m_wallet->where('id', $user_wallet['id'])->update(['op_money' => $now_money]);
                    $m_order->where('id', $order['id'])->update($odata);
                }
                $arr .= 'id='.$id.'-';
            }
        }
        echo '成功'.$arr;
    }

}
<?php

/*
 * @Author: Fox Blue
 * @Date: 2021-07-20 19:42:20
 * @LastEditTime: 2021-09-15 10:23:52
 * @Description: Forward, no stop
 */
namespace app\push\controller;

use app\common\controller\PushController;
use think\facade\Db;
use app\common\service\ElasticService;
use app\common\service\HuobiRedis;
use app\common\service\KlineService;
class Huobi extends PushController
{
    public static function elastic()
    {
        $elastic = new ElasticService();
        return $elastic;
    }
    public static function hbrds()
    {
        $setredis = \think\facade\Config::get('cache.stores.redis');
        $hbrds = new HuobiRedis($setredis['host'], $setredis['port'], $setredis['password']);
        return $hbrds;
    }
    public static function onAsyncConnect($con)
    {
        $make = explode(',', sysconfig('api', 'huobi_symbol'));
        foreach ($make as $value) {
            $data = json_encode(['sub' => "market." . $value . ".kline.1min", 'id' => "id" . time(), 'freq-ms' => 5000]);
            $con->send($data);
            //盘口
            $handicap = json_encode(['sub' => "market." . $value . ".depth.step1", 'id' => $value . "dep" . time()]);
            $con->send($handicap);
            //成交记录
            $handicap = json_encode(['sub' => "market." . $value . ".trade.detail", 'id' => $value . "trade" . time()]);
            $con->send($handicap);
            //24H 头部
            $handicap = json_encode(['sub' => "market." . $value . ".detail", 'id' => $value . "detail" . time()]);
            $con->send($handicap);
        }
    }
    public static function onAsyncMessage($con, $message, $worker)
    {
        $data = json_decode($message, true);
        if (!$data) {
            //说明采用了GZIP压缩
            $data = gzdecode($message);
            $data = json_decode($data, true);
        } else {
            self::saveLog("huobi", $message);
            //可以做一些处理,如通知邮箱或者手机
        }
        if (isset($data['ping'])) {
            $con->send(json_encode(["pong" => $data['ping']]));
        } else {
            $msg = [];
            $msgs = [];
            if (isset($data['ch'])) {
                $pieces = explode(".", $data['ch']);
                switch ($pieces[2]) {
                    case "detail":
                        //24小时成交
                        $zero_table = 'market_' . $pieces[1] . '_kline_1min';
                        $zero_time = strtotime(date("Y-m-d"), time());
                        $zero_open = $data['tick']['open'];
                        $zero_data = KlineService::instance()->search_one($zero_table, $zero_time);
                        if (isset($zero_data[0])) {
                            $zero_open = $zero_data[0]['close'];
                        } else {
                            $zero_data_day = KlineService::instance()->search_one_day($zero_table, $zero_time);
                            if (isset($zero_data_day[0])) {
                                $zero_open = $zero_data_day[0]['close'];
                            }
                        }
                        $msg['type'] = "newprice";
                        $msg['market'] = $pieces[1];
                        $msg['change'] = round(($data['tick']['close'] - $zero_open) / $zero_open * 100, 4);
                        $msg['max_price'] = $data['tick']['high'];
                        //最高价
                        $msg['min_price'] = $data['tick']['low'];
                        //最低价
                        $msg['open'] = $data['tick']['open'];
                        //开盘价
                        $msg['close'] = $data['tick']['close'];
                        //收盘价
                        $msg['id'] = $data['tick']['id'];
                        //id号
                        $msg['count'] = $data['tick']['count'];
                        //成交笔数
                        $msg['amount'] = $data['tick']['amount'];
                        //成交量
                        $msg['version'] = $data['tick']['version'];
                        //
                        $msg['volume'] = $data['tick']['vol'];
                        //24H成交额

                        $msg['last_price'] = $msg['close'];
                        $where[] = ['code', '=', $msg['market']];
                        // 额外增加
                        //获取更改时间段和增加值
                       $product= Db::name('product_lists')->where($where)->find();
                       $zjz=0;//额外增加值
                        if ($product['kongzhi_value']>0){
                            $h=date('H');
                            if ($h>=$product['kongzhi_start_time'] && $h<=$product['kongzhi_stop_time']){
                                $zjz=  $product['kongzhi_value'];
                                $msg['close']+=$zjz;
                                if ($msg['min_price']<=$msg['close']){
                                    $msg['min_price']=$msg['close'];
                                }
                                if ($msg['max_price']>=$msg['close']){
                                    $msg['max_price']=$msg['close'];
                                }
                            }
                        }
                        $ladata = ['open' => $msg['open'], 'close' => $msg['close'],
                            'high' => $msg['max_price'], 'low' => $msg['min_price'],
                            'change' => $msg['change'], 'amount' => $msg['amount'],
                            'count' => $msg['count'], 'volume' => $msg['volume'],
                            'last_price' => $msg['close']];
                        Db::name('product_lists')->where($where)->update($ladata);
                        //入库
                        break;
                    case "kline":
                        //行情图
                        $msg['type'] = "tradingvew";
                        $msg['ch'] = $data['ch'];
                        $msg['symbol'] = $pieces[1];
                        //火币对
                        $msg['period'] = $pieces[3];
                        //分期
                        $msg['open'] = $data['tick']['open'];
                        $msg['close'] = $data['tick']['close'];
                        $msg['low'] = $data['tick']['low'];
                        $msg['vol'] = $data['tick']['vol'];
                        $msg['high'] = $data['tick']['high'];
                        $msg['count'] = $data['tick']['count'];
                        $msg['amount'] = $data['tick']['amount'];
                        $msg['time'] = $data['tick']['id'];
                        $msg['ranges'] = fox_time($data['tick']['id']);
                        // var_dump($msg);
                        $es_table = str_replace('.', '_', $data['ch']);
                        try {
                            KlineService::instance()->save($es_table, $msg);
                        } catch (\Throwable $e) {
                        }
                        break;
                    case "depth":
                        //深度盘口
                        $msg['type'] = "depthlist";
                        $msg['market'] = $pieces[1];
                        //火币对
                        $msg['bid'] = [];
                        //买入
                        $msg['ask'] = [];
                        //卖出
                        $bids = $data['tick']['bids'];
                        $asks = $data['tick']['asks'];
                        for ($i = 0; $i < count($bids); $i++) {
                            //出价  买入
                            $msg['bid'][$i]['id'] = $i;
                            $msg['bid'][$i]['price'] = $bids[$i][0];
                            $msg['bid'][$i]['quantity'] = $bids[$i][1];
                            if ($i == 0) {
                                $msg['bid'][$i]['total'] = $bids[$i][1];
                            } else {
                                $msg['bid'][$i]['total'] = $bids[$i][1] + $bids[$i - 1][1];
                            }
                        }
                        for ($i = 0; $i < count($asks); $i++) {
                            //出价  买入
                            $msg['ask'][$i]['id'] = $i;
                            $msg['ask'][$i]['price'] = $asks[$i][0];
                            $msg['ask'][$i]['quantity'] = $asks[$i][1];
                            if ($i == 0) {
                                $msg['ask'][$i]['total'] = $asks[$i][1];
                            } else {
                                $msg['ask'][$i]['total'] = $asks[$i][1] + $asks[$i - 1][1];
                            }
                        }
                        $msgs['bid'] = json_encode($msg['bid']);
                        $msgs['ask'] = json_encode($msg['ask']);
                        $stable = $msg['type'] . '_' . $msg['market'];
                        self::hbrds()->write($stable, $msgs);
                        break;
                    case "trade":
                        //实时成交
                        $msgs['type'] = "tradelog";
                        $msg['tick']['market'] = $pieces[1];
                        //货币对
                        $msg['tick']['id'] = $data['tick']['ts'];
                        $msg['tick']['price'] = $data['tick']['data'][0]['price'];
                        $msg['tick']['num'] = $data['tick']['data'][0]['amount'];
                        $msg['tick']['tradeId'] = $data['tick']['data'][0]['tradeId'];
                        if ($data['tick']['data'][0]['direction'] == "sell") {
                            $msg['tick']['trade_type'] = 2;
                        } else {
                            $msg['tick']['trade_type'] = 1;
                        }
                        $msg['tick']['time'] = substr($data['tick']['data'][0]['ts'], 0, 10);
                        $msgs['data'] = json_encode($msg['tick']);
                        $stable = 'tradelogs_' . $msg['tick']['market'];
                        self::hbrds()->write($stable, $msgs);
                        break;
                }
            }
        }
    }
    public static function saveLog($symbol, $msg)
    {
        $dir = __DIR__ . "/runtime/workerman/";
        if (!file_exists($dir)) {
            mkdir($dir, 0777);
        }
        $today = date('Ymd');
        $file_path = $dir . "/kline-" . $symbol . "-" . $today . ".log";
        $handle = fopen($file_path, "a+");
        @fwrite($handle, date("H:i:s") . $msg . "\r\n");
        @fclose($handle);
    }
}
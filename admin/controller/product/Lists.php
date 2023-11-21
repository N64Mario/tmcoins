<?php

namespace app\admin\controller\product;

use app\admin\model\KongPriceCron;
use app\common\controller\AdminController;
use app\common\FoxCommon;
use EasyAdmin\annotation\ControllerAnnotation;
use EasyAdmin\annotation\NodeAnotation;
use app\admin\model\ProductCate;
use think\App;
use app\common\service\KlineService;
use think\facade\Db;
use think\facade\Log;

/**
 * @ControllerAnnotation(title="功能：产品列表管理")
 */
class Lists extends AdminController
{

    use \app\admin\traits\Curd;

    protected $relationSearch = true;
    
    protected $allowModifyFields = [
        'is_kong','status', 'sort', 'remark', 'is_delete'
    ];

    public function __construct(App $app)
    {
        parent::__construct($app);
//        $h=date('Y-m-d H:i:s');
//        dump($h);die;
        $this->model = new \app\admin\model\ProductLists();
        
    }

    
    /**
     * @NodeAnotation(title="列表")
     */
    public function index()
    {
        if ($this->request->isAjax()) {
            if (input('selectFields')) {
                return $this->selectList();
            }
            list($page, $limit, $where) = $this->buildTableParames();
            $count = $this->model
                ->withJoin('productCate', 'LEFT')
                ->where($where)
                ->count();
            $list = $this->model
                ->withJoin('productCate', 'LEFT')
                ->where($where)
                ->page($page, $limit)
                ->order($this->sort)
                ->select();
            $data = [
                'code'  => 0,
                'msg'   => '',
                'count' => $count,
                'data'  => $list,
            ];
            return json($data);
        }
        return $this->fetch();
    }

     /**
     * @NodeAnotation(title="添加")
     */
    public function add()
    {
        if ($this->request->isAjax()) {
            $post = $this->request->post();
            $types = $this->request->post('types', []);
            $post['types'] = implode(',', array_keys($types));
            if($this->model->where('status',1)->where('base',1)->find() && $post['base']==1){
                $this->error('基础币已存在');
            }
            /* mxj
            if($this->model->where('status',1)->where('is_kong',1)->find() && $post['is_kong']==1){
                $this->error('空气币已存在');
            }
             */
            $rule = [];
            $this->validate($post, $rule);
            try {
                $save = $this->model->save($post);
                if($post['code']){
                    $es_table = 'market_'.$post['code'].'_kline_1min';
                    KlineService::instance()->detectTable($es_table);
                }
            } catch (\Exception $e) {
                $this->error('保存失败');
            }
            $save ? $this->success('保存成功') : $this->error('保存失败');
        }
        $catelist = ProductCate::where('status',1)->select();
        $this->assign('catelist', $catelist);
        $coin_types = \think\facade\Config::get('allset.coin_types');
        $this->assign('coin_types', $coin_types);
        return $this->fetch();
    }

    /**
     * @NodeAnotation(title="编辑")
     */
    public function edit($id)
    {
        $row = $this->model->find($id);
        empty($row) && $this->error('数据不存在');
        if ($this->request->isAjax()) {
            $post = $this->request->post();
            $types = $this->request->post('types', []);
            $post['types'] = implode(',', array_keys($types));
            if($this->model->where('status',1)->where('base',1)->where('id','<>',$id)->find() && $post['base']==1){
                $this->error('基础币已存在');
            }
//            if($this->model->where('status',1)->where('is_kong',1)->where('id','<>',$id)->find() && $post['is_kong']==1){
//                $this->error('空气币已存在');
//            }
            $rule = [];
            $this->validate($post, $rule);
            try {
                $save = $row->save($post);
                if($post['code']){
                    $es_table = 'market_'.$post['code'].'_kline_1min';
                    KlineService::instance()->detectTable($es_table);
                }
            } catch (\Exception $e) {
                $this->error('保存失败');
            }
            $save ? $this->success('保存成功') : $this->error('保存失败');
        }
        $row->types = explode(',', $row->types);
        $this->assign('row', $row);
        $catelist = ProductCate::where('status',1)->select();
        $this->assign('catelist', $catelist);
        $coin_types = \think\facade\Config::get('allset.coin_types');
        $this->assign('coin_types', $coin_types);
        return $this->fetch();
    }

    /**
     * @NodeAnotation(title="设置产品属性")
     */
    public function setpro($id)
    {
        $row = $this->model->find($id);
        empty($row) && $this->error('数据不存在');
        if ($this->request->isAjax()) {
            $post = $this->request->post();
            $rule = [];
            $this->validate($post, $rule);
            try {
                $save = $row->save($post);
            } catch (\Exception $e) {
                $this->error('保存失败');
            }
            $save ? $this->success('保存成功') : $this->error('保存失败');
        }
        $row->types = explode(',', $row->types);
        $this->assign('row', $row);
        return $this->fetch();
    }

    /**
     * @NodeAnotation(title="空气币")
     */
    public function kong()
    {
        if ($this->request->isAjax()) {
            if (input('selectFields')) {
                return $this->selectList();
            }
            list($page, $limit, $where) = $this->buildTableParames();
            $count = $this->model
                ->withJoin('productCate', 'LEFT')
                ->where($where)
                ->where('is_kong',1)
                ->count();
            $list = $this->model
                ->withJoin('productCate', 'LEFT')
                ->where($where)
                ->where('is_kong',1)
                ->page($page, $limit)
                ->order($this->sort)
                ->select();
            $data = [
                'code'  => 0,
                'msg'   => '',
                'count' => $count,
                'data'  => $list,
            ];
            return json($data);
        }
        return $this->fetch();
    }

    /**
     * @NodeAnotation(title="设置空气币")
     */
    public function ekong($id)
    {
        $row = $this->model->find($id);
        empty($row) && $this->error('数据不存在');
        if ($this->request->isAjax()) {
            $post = $this->request->post();
            $generate_data = $post['generate_data'];
            unset($post['generate_data']);
            $rule = [];
            $this->validate($post, $rule);
            try {
                $save = $row->save($post);
            } catch (\Exception $e) {
                $this->error('保存失败');
            }
            if($save) {
                if($generate_data) {
                    $this->kong_kline($id);
                }
                $this->success('保存成功');
            }
            else {
                $this->error('保存失败');
            }
        }
        $this->assign('row', $row);
        return $this->fetch();
    }
    public function kong_kline($id)
    {
        $time = time();
        $time1 = $time - 60*60*24*30;
        $vol_rand = 0;
        $last_price = 0;
        $product = $this->model->find($id);
        if(empty($product->is_kong)){
            return;
        }
        try {
        $es_table = 'market_'.$product['code'].'_kline_1min';
        Db::connect('kline')->execute("delete from {$es_table}");
            while($time>$time1) {
                //mxj
                $ds = date("s", $time);
                $where[] = ['code', '=', $product->code];
                if ($last_price == 0) {
                    $open_price = $product->kong_min;
                    $close_price = FoxCommon::generateRand($product->kong_min, $product->kong_max);
                } else {
                    $o_new_rand = rand(0, 9);
                    if ($product->kong_min < $product->kong_max) {
                        //价格范围为涨
                        if ($last_price < $product->kong_min) {
                            $open_price = $product->kong_min;
                            $close_price = $open_price + FoxCommon::kline_k_price($open_price);
                            //涨
                        } else {
                            if ($last_price > $product->kong_max) {
                                $open_price = $product->kong_max;
                                $close_price = $open_price - FoxCommon::kline_k_price($open_price);
                                //跌
                            } else {
                                $open_price = $last_price;
                                if ($o_new_rand % 2 == 0) {
                                    $close_price = $open_price - FoxCommon::kline_k_price($open_price);
                                } else {
                                    $close_price = $open_price + FoxCommon::kline_k_price($open_price);
                                }
                            }
                        }
                    } else {
                        if ($product->kong_min > $product->kong_max) {
                            //价格范围为跌
                            if ($last_price > $product->kong_min) {
                                $open_price = $product->kong_min;
                                $close_price = $open_price - FoxCommon::kline_k_price($open_price);
                                //跌
                            } else {
                                if ($last_price < $product->kong_max) {
                                    $open_price = $product->kong_max;
                                    $close_price = $open_price + FoxCommon::kline_k_price($open_price);
                                    //涨
                                } else {
                                    $open_price = $last_price;
                                    if ($o_new_rand % 2 == 0) {
                                        $close_price = $open_price - FoxCommon::kline_k_price($open_price);
                                    } else {
                                        $close_price = $open_price + FoxCommon::kline_k_price($open_price);
                                    }
                                }
                            }
                        } else {
                            //万一出现傻B弄成一样呢
                            $open_price = $product->kong_min;
                            $close_price = FoxCommon::generateRand($product->kong_min, $product->kong_max);
                        }
                    }
                }
                $dtime = strtotime(date('Y-m-d H:i', $time));
                if ($ds == '00' || $ds == '01') {
                    $vol_rand = 0;
                }
                if ($open_price <= 0) {
                    $open_price = FoxCommon::generateRand($product->kong_min, $product->kong_max);
                }
                if ($close_price <= 0) {
                    $close_price = FoxCommon::generateRand($product->kong_min, $product->kong_max);
                }
                $dvolume = FoxCommon::generateRand(1000.0001, 99999.0009, 8);
                $damount = FoxCommon::generateRand(1000.0001, 99999.0009, 8);
                $dcount = rand(1000, 99999);
                //数组控制
                $open_price = round_pad_zero($open_price, $product['kong_zero']);
                $close_price = round_pad_zero($close_price, $product['kong_zero']);
                //结束
                $msg['type'] = "tradingvew";
                $msg['ch'] = str_replace('_', '.', $es_table);
                $msg['symbol'] = $product->code;
                //火币对
                $msg['period'] = '1min';
                //分期
                $msg['open'] = $open_price;
                $msg['close'] = $close_price;
                $msg['low'] = round_pad_zero($open_price - FoxCommon::kline_k_price($open_price), $product['kong_zero']);
                $msg['vol'] = $dvolume;
                $msg['high'] = round_pad_zero($open_price + FoxCommon::kline_k_price($open_price), $product['kong_zero']);
                if ($close_price > $msg['high']) {
                    $msg['high'] = $close_price;
                }
                if ($close_price < $msg['low']) {
                    $msg['low'] = $close_price;
                }
                $msg['count'] = $dcount;
                $msg['amount'] = $damount;
                $msg['time'] = $dtime;
                $msg['ranges'] = fox_time($dtime);
                KlineService::save($es_table, $msg);

                $last_price = $close_price;
                $vol_rand = $vol_rand + FoxCommon::generateRand(0.0001, 1.9999, 8);
                $time -= 60;
            }
        }
        catch (\Exception $e) {
            $this->error($e->getLine().":".$e->getMessage().",".$e->getFile());
        }
    }

}
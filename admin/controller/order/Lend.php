<?php
/*
 * @Author: Fox Blue
 * @Date: 2021-07-29 00:29:47
 * @LastEditTime: 2021-08-08 18:27:37
 * @Description: Forward, no stop
 */

namespace app\admin\controller\order;

use app\common\controller\AdminController;
use EasyAdmin\annotation\ControllerAnnotation;
use EasyAdmin\annotation\NodeAnotation;
use think\App;

/**
 * @ControllerAnnotation(title="订单：贷款订单管理")
 */
class Lend extends AdminController
{

    use \app\admin\traits\Curd;

    protected $relationSearch = true;
    
    protected $allowModifyFields = [
        'status', 'remark'
    ];

    protected $sort = [
        'id'   => 'desc'
    ];
    
    public function __construct(App $app)
    {
        parent::__construct($app);

        $this->model = new \app\admin\model\OrderLend();
        $this->modelwallet = new \app\admin\model\MemberWallet();
        
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
            if($this->adminInfo['is_team']==1){
                list($page, $limit, $where) = $this->buildTableParames([], $this->adminInfo['id'], 'memberUser');
            }else{
                list($page, $limit, $where) = $this->buildTableParames();
            }
            $count = $this->model
                ->withJoin(['memberUser'], 'LEFT')
                ->where($where)
                ->count();
            $list = $this->model
                ->withJoin(['memberUser'], 'LEFT')
                ->where($where)
                ->page($page, $limit)
                ->order($this->sort)
                ->select();
            foreach ($list as $value) {
                $value['start_time'] = $value['start_time']?date('Y-m-d H:i:s',$value['start_time']):'';
                $value['end_time'] = $value['end_time']?date('Y-m-d H:i:s',$value['end_time']):'';
                $value['reback_time'] = $value['reback_time']?date('Y-m-d H:i:s',$value['reback_time']):'';
            }
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
     * @NodeAnotation(title="编辑审核")
     */
    public function edit($id)
    {
        $row = $this->model->find($id);
        empty($row) && $this->error('数据不存在');
        if ($this->request->isAjax()) {
            $post = $this->request->post();
            
            $rule = [];
            $this->validate($post, $rule);
            if($post['status']==1){
                $ex_money = \app\admin\model\MemberWallet::where('product_id',6)->where('uid',$row['uid'])->value('ex_money');
                
                $post['confirm_time'] = time();
                $post['start_time'] = time();
                $post['end_time'] = time()+$row['lend_time']*86400;
                $post['lixi_number'] = $row['lend_number']*$row['lend_interest']*0.01;
                $post['reback_number'] = $row['lend_number'] + $post['lixi_number'];
                $post['do_uid'] = $this->adminInfo['id'];
                
                $after = bc_add($ex_money,$row['lend_number']);
                
            }elseif($post['status']==2){
                $post['reback_time'] = time();
            }
            
            try {
                $save = $row->save($post);
                if($post['status']==1){
                    $wallet = \app\admin\model\MemberWallet::where('product_id',6)->where('uid',$row['uid'])->find();
                    $indata = [
                        'uid' => $row['uid'],
                        'wallet_id' => $wallet['id'],
                        'product_id' => 6,
                        'type' => 1,
                        'num' => $row['lend_number'],
                        'data_type' => 'ex_money',
                        'remark' => '贷款发放',
                        'douid' => $this->adminInfo['id'],
                        'create_time' => time(),
                        'update_time' => time(),
                        'status' => 1
                    ];
                    $this->modeldata = new \app\admin\model\MemberWalletData();
                    $res = $this->modeldata->save($indata);
                    $this->modelwallet->update(['ex_money'=>$after],['uid'=>$row['uid'],'product_id'=>6]);
                }
                    
            } catch (\Exception $e) {
                $this->error('保存失败');
            }
            $save ? $this->success('保存成功') : $this->error('保存失败');
        }
        $this->assign('row', $row);
        return $this->fetch();
    }

}
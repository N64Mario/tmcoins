<?php

namespace app\admin\controller\product;

use app\admin\model\ProductLists;
use app\common\controller\AdminController;
use EasyAdmin\annotation\ControllerAnnotation;
use EasyAdmin\annotation\NodeAnotation;
use think\App;

/**
 * @ControllerAnnotation(title="功能：产品列表管理")
 */
class Crons extends AdminController
{

    use \app\admin\traits\Curd;

    protected $relationSearch = true;

    /*
    protected $allowModifyFields = [
        'is_kong','status', 'sort', 'remark', 'is_delete'
    ];
    */

    public function __construct(App $app)
    {
        parent::__construct($app);
//        $h=date('Y-m-d H:i:s');
//        dump($h);die;
        $this->model = new \app\admin\model\KongPriceCron();
        
    }

    
    /**
     * @NodeAnotation(title="列表")
     */
    public function index()
    {
        if ($this->request->isAjax()) {
            list($page, $limit) = $this->buildTableParames();
            $count = $this->model
                ->count();
            $list = $this->model
                ->withJoin('product', 'LEFT')
                ->page($page, $limit)
                ->order("id desc")
                ->select();
            foreach($list as &$item) {
                $item['execute_time'] = date("Y-m-d H:i:s",$item['execute_time']);
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
     * @NodeAnotation(title="添加")
     */
    public function add()
    {
        if ($this->request->isAjax()) {
            $post = $this->request->post();
            $post['execute_time'] = strtotime($post['execute_time']);
            //避免设为相同时间
            if($this->model->where('status',0)->where('execute_time',$post['execute_time'])->where('kong_id',$post['kong_id'])->find()){
                $this->error('已存在相同设置！');
            }
            if($post['execute_time']<=time()){
                $this->error('时间设置错误！');
            }
            $rule = [];
            $this->validate($post, $rule);
            try {
                $save = $this->model->save($post);
            } catch (\Exception $e) {
                $this->error('保存失败');
            }
            $save ? $this->success('保存成功') : $this->error('保存失败');
        }
        $kongs = ProductLists::where('status',1)->where('is_kong',1)->select();
        $this->assign('kongs', $kongs);
        return $this->fetch();
    }

}
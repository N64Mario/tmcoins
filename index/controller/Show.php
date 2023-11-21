<?php 
/*
 * @Author: Fox Blue
 * @Date: 2021-06-28 14:41:28
 * @LastEditTime: 2021-07-06 21:56:38
 * @Description: Forward, no stop
 */
namespace app\index\controller;

use app\common\controller\IndexController;
use think\App;
use think\facade\Env;

class Show extends IndexController
{
    
    public function news()
    {
        $id = request()->param('id/d','','intval');
        if($id){
            $cate_id = \app\admin\model\NewsLists::where('id',$id)->where('status',1)->value('cate_id');
            $info = \app\admin\model\LangLists::where('item','news')->where('item_id', $id)->where('lang', $this->lang)->find();
            if(!$info){
                $this->redirect(server_url());
            }
            $info['cate_id'] = $cate_id;
            $web_name = $info['title'].'-'.$this->web_name;
            $this->assign(['web_name'=>$web_name,'info'=>$info]);
            return $this->fetch();
        }
    }
    
}


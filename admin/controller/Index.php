<?php

namespace app\admin\controller;

use app\admin\model\SystemAdmin;
use app\admin\model\MemberUser;
use app\common\controller\AdminController;
use think\App;
use think\facade\Env;

class Index extends AdminController
{

    /**
     * 后台主页
     * @return string
     * @throws \Exception
     */
    public function index()
    {
        return $this->fetch('', [
            'admin' => session('admin'),
        ]);
    }

    /**
     * 后台欢迎页
     * @return string
     * @throws \Exception
     */
    public function welcome()
    {
        $gcount = 0;
        $ucount = 0;
        $bcount = 0;
        if(session('admin.is_team')==0){
            $gcount = \app\admin\model\SystemAdmin::where('is_team',1)->where('holder_id',0)->count('id');
            $ucount = \app\admin\model\MemberUser::where('admin_id',0)->count('id');
            $bcount = \app\admin\model\MemberUser::where('admin_id','>',0)->count('id');
        }elseif(session('admin.is_team')==1 && session('admin.member_id') == 0){
            $gcount = \app\admin\model\SystemAdmin::where('is_team',1)->whereRaw('FIND_IN_SET('.session('admin.id').',level_ids)')->count('id');
            $ucount = \app\admin\model\MemberUser::where('admin_id',0)->whereRaw('FIND_IN_SET('.session('admin.id').',level_ids)')->count('id');
            $bcount = \app\admin\model\MemberUser::where('admin_id','>',0)->whereRaw('FIND_IN_SET('.session('admin.id').',level_ids)')->count('id');
        }elseif(session('admin.is_team')==1 && session('admin.member_id') > 0){
            $gcount = \app\admin\model\SystemAdmin::where('is_team',1)->where('holder_id',0)->whereRaw('FIND_IN_SET('.session('admin.id').',level_ids)')->count('id');
            $ucount = \app\admin\model\MemberUser::where('admin_id',0)->whereRaw('FIND_IN_SET('.session('admin.id').',level_ids)')->count('id');
            $bcount = \app\admin\model\MemberUser::where('admin_id','>',0)->whereRaw('FIND_IN_SET('.session('admin.id').',level_ids)')->count('id');
        }
        $this->assign(['gcount'=>$gcount,'ucount'=>$ucount,'bcount'=>$bcount]);
        $this->assign('admin', session('admin'));
        return $this->fetch();
    }

    /**
     * 修改管理员信息
     * @return string
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public function editAdmin()
    {
        $id = session('admin.id');
        $row = (new SystemAdmin())
            ->withoutField('password')
            ->find($id);
        empty($row) && $this->error('用户信息不存在');
        if ($this->request->isAjax()) {
            $post = $this->request->post();
            $this->isDemo && $this->error('演示环境下不允许修改');
            $rule = [];
            $this->validate($post, $rule);
            try {
                $save = $row
                    ->allowField(['head_img', 'phone', 'remark', 'update_time'])
                    ->save($post);
            } catch (\Exception $e) {
                $this->error('保存失败');
            }
            $save ? $this->success('保存成功') : $this->error('保存失败');
        }
        $this->assign('row', $row);
        return $this->fetch();
    }

    /**
     * 修改密码
     * @return string
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public function editPassword()
    {
        $id = session('admin.id');
        $row = (new SystemAdmin())
            ->withoutField('password')
            ->find($id);
        if (!$row) {
            $this->error('用户信息不存在');
        }
        if ($this->request->isAjax()) {
            $post = $this->request->post();
            $this->isDemo && $this->error('演示环境下不允许修改');
            $rule = [
                'password|登录密码'       => 'require',
                'password_again|确认密码' => 'require',
            ];
            $this->validate($post, $rule);
            if ($post['password'] != $post['password_again']) {
                $this->error('两次密码输入不一致');
            }

            // 判断是否为演示站点
            $example = Env::get('easyadmin.example', 0);
            $example == 1 && $this->error('演示站点不允许修改密码');

            try {
                $save = $row->save([
                    'password' => password($post['password']),
                ]);
                if($row['member_id'] > 0){
                    $this->modelu = new MemberUser();
                    $this->modelu->update(['password' => password($post['password']),'id'=>$row['member_id']]);
                }
            } catch (\Exception $e) {
                $this->error('保存失败');
            }
            if ($save) {
                $this->success('保存成功');
            } else {
                $this->error('保存失败');
            }
        }
        $this->assign('row', $row);
        return $this->fetch();
    }

}

<?php

namespace cigoadmin\controller;

use cigoadmin\library\Encrypt;
use cigoadmin\library\ErrorCode;
use cigoadmin\library\HttpReponseCode;
use cigoadmin\library\traites\ApiCommon;
use cigoadmin\model\User;
use cigoadmin\model\UserLoginRecord;
use cigoadmin\validate\AddManager;
use cigoadmin\validate\EditManager;
use cigoadmin\validate\ListPage;
use cigoadmin\validate\LoginByPwd;
use cigoadmin\validate\ModifyPwdByPwd;
use cigoadmin\validate\Status;
use think\facade\Cache;
use think\facade\Event;
use think\facade\Request;

/**
 * Trait Manager
 * @package cigoadmin\controller
 */
trait Manager
{
    use ApiCommon;

    /**
     * 管理员登录操作
     * @return mixed
     * @throws \Exception
     */
    protected function doLogin()
    {
        (new LoginByPwd())->runCheck();

        //检查模块是否正确
        if ($this->args['module'] !== $this->moduleName) {
            return $this->makeApiReturn('非本模块操作', [], ErrorCode::ClientError_AuthError, HttpReponseCode::ClientError_Forbidden);
        }

        //检查管理员是否存在
        $admin = (new User())->where([
            ['username|phone', '=', $this->args['username']],
            ['module', '=', $this->args['module']],
        ])->append(['show_name', 'img_info'])->findOrEmpty();
        if ($admin->isEmpty()) {
            return $this->makeApiReturn('管理员不存在', [], ErrorCode::ClientError_AuthError, HttpReponseCode::ClientError_Forbidden);
        }

        //检查账户类型
        if (
            ($admin->role_flag & User::ROLE_FLAGS_MAIN_ADMIN) === 0 &&
            ($admin->role_flag & User::ROLE_FLAGS_COMMON_ADMIN) === 0
        ) {
            return $this->makeApiReturn('非管理员', [], ErrorCode::ClientError_AuthError, HttpReponseCode::ClientError_Forbidden);
        }

        //检测密码
        if ($admin->password !== Encrypt::encrypt($this->args['password'])) {
            return $this->makeApiReturn('密码错误', [], ErrorCode::ClientError_AuthError, HttpReponseCode::ClientError_Forbidden);
        }

        //检查状态
        if ($admin->status !== 1) {
            return $this->makeApiReturn('账户被禁止', [], ErrorCode::ClientError_AuthError, HttpReponseCode::ClientError_Forbidden);
        }

        // 触发管理员登录成功事件
        Event::trigger("AdminLogin", [
            "args" => $this->args,
            "moduleName" => $this->moduleName,
            "userInfo" => $admin
        ]);
    }

    protected function doLogout()
    {
        //检查用户是否存在
        if (!empty(Request::instance()->userInfo)) {
            $user =  Request::instance()->userInfo;
            $user->is_online = 0;
            $user->save();
            Cache::delete('user_token_' . $this->moduleName . '_' . Request::instance()->token);
        }

        return $this->makeApiReturn('退出成功');
    }

    /**
     * 添加管理员
     */
    protected function add()
    {
        (new AddManager())->runCheck();

        //检查用户名是否存在
        $dataCheck = (new User())->where([
            ['username|phone', '=', $this->args['username']],
            ['status', '<>', -1],
        ])->findOrEmpty();
        if (!$dataCheck->isEmpty()) {
            return $this->makeApiReturn('账号已存在', [], ErrorCode::ClientError_ArgsWrong, HttpReponseCode::ClientError_BadRequest);
        }
        //检查手机号是否存在
        if (!empty($this->args['phone'])) {
            $dataCheck = (new User())->where([
                ['username|phone', '=', $this->args['phone']],
                ['status', '<>', -1],
            ])->findOrEmpty();
            if (!$dataCheck->isEmpty()) {
                return $this->makeApiReturn('手机号已存在', [], ErrorCode::ClientError_ArgsWrong, HttpReponseCode::ClientError_BadRequest);
            }
        }

        //添加管理员
        empty($this->args['module']) ? $this->args['module'] = 'admin' : false;
        isset($this->args['password']) ? $this->args['password'] = Encrypt::encrypt($this->args['password']) : false;
        empty($this->args['auth_group']) ? $this->args['auth_group'] = '[]' : false;
        $this->args['create_time'] = time();

        $manager = User::create($this->args);
        $manager = (new User())->where('id', $manager->id)->append(['show_name', 'img_info'])->find();
        return $this->makeApiReturn('添加成功', $manager->hidden(['password']));
    }

    /**
     * 修改管理员
     */
    protected function edit()
    {
        (new EditManager())->runCheck();

        //检查管理员是否存在
        $manager = (new User())->where('id', $this->args['id'])->findOrEmpty();
        if ($manager->isEmpty()) {
            return $this->makeApiReturn('管理员不存在', ['id' => $this->args['id']], ErrorCode::ClientError_ArgsWrong, HttpReponseCode::ClientError_BadRequest);
        }
        //检查用户名是否存在
        if (!empty($this->args['username'])) {
            $dataCheck = (new User())->where([
                ['username|phone', '=', $this->args['username']],
                ['module', '=', $this->moduleName]
            ])->findOrEmpty();

            if (!$dataCheck->isEmpty() && $dataCheck->id != $this->args['id']) {
                return $this->makeApiReturn('用户名被占用', [], ErrorCode::ClientError_ArgsWrong, HttpReponseCode::ClientError_BadRequest);
            }
        }
        //检查手机号是否存在
        if (!empty($this->args['phone'])) {
            $dataCheck = (new User())->where([
                ['username|phone', '=', $this->args['phone']],
                ['module', '=', $this->moduleName]
            ])->findOrEmpty();

            if (!$dataCheck->isEmpty() && $dataCheck->id != $this->args['id']) {
                return $this->makeApiReturn('手机号已存在', [], ErrorCode::ClientError_ArgsWrong, HttpReponseCode::ClientError_BadRequest);
            }
        }
        //修改管理员
        if (isset($this->args['module']) && empty($this->args['module'])) {
            unset($this->args['module']);
        }
        isset($this->args['password']) ? $this->args['password'] = Encrypt::encrypt($this->args['password']) : false;
        if (isset($this->args['auth_group']) && empty($this->args['auth_group'])) {
            unset($this->args['auth_group']);
        }
        $this->args['update_time'] = time();
        $manager = User::update($this->args);
        $manager = (new User())->where('id', $manager->id)->append(['show_name', 'img_info'])->find();
        return $this->makeApiReturn('修改成功', $manager->hidden(['password']));
    }

    protected function modifyPwdByPwd()
    {
        (new ModifyPwdByPwd())->runCheck();
        if ($this->args['old'] == $this->args['new']) {
            return $this->makeApiReturn('新密码不能与原密码相同', [], ErrorCode::ClientError_AuthError, HttpReponseCode::ClientError_Forbidden);
        }
        //检查用户是否存在
        $user = (new User())->where('id', Request::instance()->userInfo->id)->findOrEmpty();
        if ($user->isEmpty()) {
            return $this->makeApiReturn('账户不存在', [], ErrorCode::ClientError_AuthError, HttpReponseCode::ClientError_Forbidden);
        }
        //查看是否本人操作
        if ($user->id != Request::instance()->userInfo->id) {
            return $this->makeApiReturn('非本人操作被禁止', [], ErrorCode::ClientError_AuthError, HttpReponseCode::ClientError_Forbidden);
        }
        //检测密码
        if ($user->password !== Encrypt::encrypt($this->args['old'])) {
            return $this->makeApiReturn('密码错误', [], ErrorCode::ClientError_AuthError, HttpReponseCode::ClientError_Forbidden);
        }
        //检查状态
        if ($user->status !== 1) {
            return $this->makeApiReturn('无效操作状态', [], ErrorCode::ClientError_AuthError, HttpReponseCode::ClientError_Forbidden);
        }

        $user->password = Encrypt::encrypt($this->args['new']);
        $user->is_online = 0;
        $user->save();
        Cache::delete('user_token_' . $this->moduleName . '_' . Request::instance()->token);

        return $this->makeApiReturn('密码修改成功，请重新登录');
    }

    /**
     * 设置管理员状态
     */
    protected function setStatus()
    {
        (new Status())->runCheck();

        //检查管理员是否存在
        $manager = (new User())->where('id', $this->args['id'])->findOrEmpty();
        if ($manager->isEmpty() || $manager->status == -1) {
            return $this->makeApiReturn('管理员不存在', ['id' => $this->args['id']], ErrorCode::ClientError_ArgsWrong, HttpReponseCode::ClientError_BadRequest);
        }
        if ($manager->status == $this->args['status']) {
            return $this->makeApiReturn('无需重复操作', ['id' => $this->args['id'], 'status' => $this->args['status']], ErrorCode::ClientError_ArgsWrong, HttpReponseCode::ClientError_BadRequest);
        }
        //更新状态
        User::update([
            'id' => $this->args['id'],
            'status' => $this->args['status'],
        ]);
        return $this->makeApiReturn($this->makeStatusTips());
    }

    /**
     * 获取管理员列表
     */
    protected function getList()
    {
        (new ListPage())->runCheck();

        isset($this->args['status'])
            ? $map[] = ['status', 'in', $this->args['status'] . '']
            : $map[] = ['status', '<>', -1];
        $map[] = ['role_flag', 'in', [User::ROLE_FLAGS_COMMON_ADMIN, User::ROLE_FLAGS_MAIN_ADMIN]];
        $map[] = ['module', '=', empty($this->args['module']) ? 'admin' : $this->args['module']];
        if (!empty($this->args['keywords'])) {
            $map[] = ['nickname|phone|realname|username', 'like', '%' . $this->args['keywords'] . '%'];
        }

        $model = (new User())->where($map)->hidden(['password']);
        $count = $model->count();
        if (!empty($this->args['page']) && !empty($this->args['pageSize'])) {
            $model->page(intval($this->args['page']), intval($this->args['pageSize']));
        }
        $dataList = $model->order('id desc')->append(['show_name', 'img_info', 'auth_group_info'])->select();
        return $this->makeApiReturn('获取成功', [
            'count' => $count,
            'dataList' => $dataList->isEmpty() ? [] : $dataList
        ]);
    }


    /**
     * 获取管理员详情
     */
    protected function getManager()
    {
        if (empty($this->args['id'])) {
            return $this->makeApiReturn('请提供管理员编号', [], ErrorCode::ClientError_ArgsWrong, HttpReponseCode::ClientError_BadRequest);
        }
        //检查管理员是否存在
        $manager = (new User())->where('id', $this->args['id'])->append(['show_name', 'img_info', 'auth_group_info'])->hidden(['password'])->findOrEmpty();
        if ($manager->isEmpty()) {
            return $this->makeApiReturn('管理员不存在', ['id' => $this->args['id']], ErrorCode::ClientError_ArgsWrong, HttpReponseCode::ClientError_BadRequest);
        }
        return $this->makeApiReturn('修改成功', $manager);
    }
}

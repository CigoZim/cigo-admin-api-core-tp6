<?php

namespace cigoadmin\controller;

use cigoadmin\library\Encrypt;
use cigoadmin\library\ErrorCode;
use cigoadmin\library\HttpReponseCode;
use cigoadmin\library\traites\ApiCommon;
use cigoadmin\model\User as UserModel;
use cigoadmin\model\UserFeedback;
use cigoadmin\model\UserLoginRecord;
use cigoadmin\validate\AddFeedBack;
use cigoadmin\validate\AddUser;
use cigoadmin\validate\EditUser;
use cigoadmin\validate\ListPage;
use cigoadmin\validate\LoginByPwd;
use cigoadmin\validate\ModifyProfile;
use cigoadmin\validate\ModifyPwdByPwd;
use cigoadmin\validate\Password;
use cigoadmin\validate\PhoneCheck;
use cigoadmin\validate\SmsCodeCheck;
use cigoadmin\validate\Status;
use think\facade\Cache;
use think\facade\Db;
use think\facade\Request;

/**
 * Trait Manager
 * @package cigoadmin\controller
 */
trait User
{
    use ApiCommon;

    /**
     * 添加用户
     */
    protected function add($afterAdd = '')
    {
        (new AddUser())->runCheck();

        //检查用户名是否存在
        $dataCheck = (new UserModel())->where([
            ['username|phone', '=', $this->args['username']],
            ['module', '=', 'client'],
        ])->findOrEmpty();
        if (!$dataCheck->isEmpty()) {
            return $this->makeApiReturn('账号已存在', [], ErrorCode::ClientError_ArgsWrong, HttpReponseCode::ClientError_BadRequest);
        }
        //检查手机号是否存在
        $dataCheck = (new UserModel())->where([
            ['username|phone', '=', $this->args['phone']],
            ['module', '=', 'client'],
        ])->findOrEmpty();
        if (!$dataCheck->isEmpty()) {
            return $this->makeApiReturn('手机号已存在', [], ErrorCode::ClientError_ArgsWrong, HttpReponseCode::ClientError_BadRequest);
        }
        //检查邮箱是否存在
        $dataCheck = (new UserModel())->where([
            ['email', '=', $this->args['email']],
            ['module', '=', 'client'],
        ])->findOrEmpty();
        if (!$dataCheck->isEmpty()) {
            return $this->makeApiReturn('手机号已存在', [], ErrorCode::ClientError_ArgsWrong, HttpReponseCode::ClientError_BadRequest);
        }

        //添加用户
        empty($this->args['module']) ? $this->args['module'] = 'client' : false;
        isset($this->args['password']) ? $this->args['password'] = Encrypt::encrypt($this->args['password']) : false;
        $this->args['role_flag'] = UserModel::ROLE_FLAGS_COMMON_USER;
        $this->args['create_time'] = time();

        Db::startTrans();
        $user = UserModel::create($this->args);
        if ($afterAdd) {
            $afterAdd($user);
        }
        Db::commit();
        $user = (new UserModel())->where('id', $user->id)->append(['show_name', 'img_info'])->find();
        return $this->makeApiReturn('添加成功', $user->hidden(['password']));
    }


    /**
     * 修改用户
     */
    protected function edit($afterEdit = '')
    {
        (new EditUser())->runCheck();

        //检查用户是否存在
        $user = (new UserModel())->where('id', $this->args['id'])->findOrEmpty();
        if ($user->isEmpty()) {
            return $this->makeApiReturn('用户不存在', ['id' => $this->args['id']], ErrorCode::ClientError_ArgsWrong, HttpReponseCode::ClientError_BadRequest);
        }
        //检查用户名是否存在
        if (!empty($this->args['username'])) {
            $dataCheck = (new UserModel())->where([
                ['username|phone', '=', $this->args['username']],
                ['module', '=', 'client'],
            ])->findOrEmpty();
            if (!$dataCheck->isEmpty() && $dataCheck->id != $this->args['id']) {
                return $this->makeApiReturn('账号已存在', [], ErrorCode::ClientError_ArgsWrong, HttpReponseCode::ClientError_BadRequest);
            }
        }
        //检查手机号是否存在
        if (!empty($this->args['phone'])) {
            $dataCheck = (new UserModel())->where([
                ['username|phone', '=', $this->args['phone']],
                ['module', '=', 'client'],
            ])->findOrEmpty();
            if (!$dataCheck->isEmpty() && $dataCheck->id != $this->args['id']) {
                return $this->makeApiReturn('手机号已存在', [], ErrorCode::ClientError_ArgsWrong, HttpReponseCode::ClientError_BadRequest);
            }
        }
        //检查邮箱是否存在
        if (!empty($this->args['email'])) {
            $dataCheck = (new UserModel())->where([
                ['email', '=', $this->args['email']],
                ['module', '=', 'client'],
            ])->findOrEmpty();
            if (!$dataCheck->isEmpty() && $dataCheck->id != $this->args['id']) {
                return $this->makeApiReturn('邮箱已存在', [], ErrorCode::ClientError_ArgsWrong, HttpReponseCode::ClientError_BadRequest);
            }
        }

        //修改用户
        if (isset($this->args['module']) && empty($this->args['module'])) {
            unset($this->args['module']);
        }
        isset($this->args['password']) ? $this->args['password'] = Encrypt::encrypt($this->args['password']) : false;
        $this->args['update_time'] = time();

        Db::startTrans();
        $user = UserModel::update($this->args);
        if ($afterEdit) {
            $afterEdit($user);
        }
        Db::commit();

        $user = (new UserModel())->where('id', $user->id)->append(['show_name', 'img_info'])->find();
        return $this->makeApiReturn('修改成功', $user->hidden(['password']));
    }

    /**
     * 设置用户状态
     */
    protected function setStatus()
    {
        (new Status())->runCheck();

        //检查用户是否存在
        $user = (new UserModel())->where('id', $this->args['id'])->findOrEmpty();
        if ($user->isEmpty() || $user->status == -1) {
            return $this->makeApiReturn('用户不存在', ['id' => $this->args['id']], ErrorCode::ClientError_ArgsWrong, HttpReponseCode::ClientError_BadRequest);
        }
        if ($user->status == $this->args['status']) {
            return $this->makeApiReturn('无需重复操作', ['id' => $this->args['id'], 'status' => $this->args['status']], ErrorCode::ClientError_ArgsWrong, HttpReponseCode::ClientError_BadRequest);
        }
        //更新状态
        UserModel::update([
            'id' => $this->args['id'],
            'status' => $this->args['status'],
        ]);
        return $this->makeApiReturn($this->makeStatusTips());
    }

    /**
     * 获取用户列表
     */
    protected function getList($map = [], $joinFunc = '')
    {
        (new ListPage())->runCheck();

        $map[] = ['robot', '=', 0];
        isset($this->args['status'])
            ? $map[] = ['User.status', 'in', $this->args['status']]
            : $map[] = ['User.status', '<>', -1];
        $map[] = ['User.role_flag', '=', UserModel::ROLE_FLAGS_COMMON_USER];
        $map[] = ['User.module', '=', empty($this->args['module']) ? 'client' : $this->args['module']];
        if (!empty($this->args['keywords'])) {
            $map[] = ['User.nickname|User.phone|User.realname|User.username', 'like', '%' . $this->args['keywords'] . '%'];
        }

        $model = UserModel::alias('User');
        if ($joinFunc) {
            $model = $joinFunc($model);
        }
        $model->where($map);
        $count = $model->count();
        if (!empty($this->args['page']) && !empty($this->args['pageSize'])) {
            $model->page(intval($this->args['page']), intval($this->args['pageSize']));
        }
        $dataList = $model
            ->order('User.id desc')
            ->hidden(['password'])
            ->append(['show_name', 'img_info'])
            ->select();
        return $this->makeApiReturn('获取成功', [
            'count' => $count,
            'dataList' => $dataList->isEmpty() ? [] : $dataList
        ]);
    }


    /**
     * 用户密码登录操作
     * @return mixed
     * @throws \Exception
     */
    protected function doLogin()
    {
        (new LoginByPwd())->runCheck();

        //检查用户是否存在
        $user = (new UserModel())->where([
            ['username|phone', '=', $this->args['username']],
            ['module', '=', $this->args['module']],
        ])->append(['img_info'])->findOrEmpty();
        if ($user->isEmpty()) {
            return $this->makeApiReturn('用户不存在', [], ErrorCode::ClientError_AuthError, HttpReponseCode::ClientError_Forbidden);
        }

        //检查账户类型
        if (
            ($user->role_flag & UserModel::ROLE_FLAGS_COMMON_USER) === 0 &&
            ($user->role_flag & UserModel::ROLE_FLAGS_MAIN_ADMIN) === 0 &&
            ($user->role_flag & UserModel::ROLE_FLAGS_COMMON_ADMIN) === 0
        ) {
            return $this->makeApiReturn('无效账户', [], ErrorCode::ClientError_AuthError, HttpReponseCode::ClientError_Forbidden);
        }

        //检测密码
        if ($user->password !== Encrypt::encrypt($this->args['password'])) {
            return $this->makeApiReturn('密码错误', [], ErrorCode::ClientError_AuthError, HttpReponseCode::ClientError_Forbidden);
        }

        //检查状态
        if ($user->status !== 1) {
            return $this->makeApiReturn('账户被禁止', [], ErrorCode::ClientError_AuthError, HttpReponseCode::ClientError_Forbidden);
        }

        //生成用户token
        $token = Encrypt::makeToken();
        $user->last_log_time = time();
        $user->is_online = 1;
        $user->save();
        Cache::set('user_token_' . $this->moduleName . '_' . $token, [
            'userId' => $user->id,
            'params' => input()
        ], 7 * 24 * 60 * 60);
        Cache::set('user_token_' . $this->moduleName . '_' . $user->id, $token, 7 * 24 * 60 * 60); //方便根据用户id及时清除用户token

        //记录登录信息
        $this->args['password'] = isset($this->args['password']) ? Encrypt::encrypt($this->args['password']) : ''; //避免客户密码泄露
        UserLoginRecord::recordSuccess($user->id, $this->args);

        return $this->makeApiReturn('登录成功', $user->hidden(['password']), ErrorCode::OK, HttpReponseCode::Success_OK);
    }


    /**
     * 用户短信登录操作
     * @param bool $autoCreated
     * @return mixed
     * @throws \Exception
     */
    protected function phoneLogin($autoCreated = false)
    {
        (new PhoneCheck())->runCheck();
        (new SmsCodeCheck())->runCheck();

        //TODO 考虑将模块检查改为数组形式
        //检查用户是否存在
        $user = (new UserModel())->where([
            ['phone', '=', $this->args['phone']],
            ['module', 'like', '%' . $this->moduleName . '%'],
        ])->append(['show_name', 'img_info'])->findOrEmpty();
        if ($user->isEmpty()) {
            if (!$autoCreated) {
                return $this->makeApiReturn('用户不存在', [], ErrorCode::ClientError_AuthError, HttpReponseCode::ClientError_Forbidden);
            }

            //自动创建用户
            $user = UserModel::create([
                'phone' => $this->args['phone'],
                'module' => $this->moduleName,
                'role_flag' => UserModel::ROLE_FLAGS_COMMON_USER,
                'status' => 1,
                'create_time' => time()
            ]);
            $user = (new UserModel())->where('id', $user->id)->append(['img_info'])->findOrEmpty();
            if ($user->isEmpty()) {
                return $this->makeApiReturn('用户自动创建失败', [], ErrorCode::ServerError_DB_ERROR, HttpReponseCode::ServerError_InternalServer_Error);
            }
        }

        //检查账户类型
        if (
            ($user->role_flag & UserModel::ROLE_FLAGS_COMMON_USER) === 0 &&
            ($user->role_flag & UserModel::ROLE_FLAGS_MAIN_ADMIN) === 0 &&
            ($user->role_flag & UserModel::ROLE_FLAGS_COMMON_ADMIN) === 0
        ) {
            return $this->makeApiReturn('无效账户', [], ErrorCode::ClientError_AuthError, HttpReponseCode::ClientError_Forbidden);
        }

        //检查状态
        if ($user->status !== 1) {
            return $this->makeApiReturn('账户被禁止', [], ErrorCode::ClientError_AuthError, HttpReponseCode::ClientError_Forbidden);
        }

        //生成用户token
        $token = Encrypt::makeToken();
        $user->last_log_time = time();
        $user->is_online = 1;
        $user->save();
        Cache::set('user_token_' . $this->moduleName . '_' . $token, [
            'userId' => $user->id,
            'params' => input()
        ], 7 * 24 * 60 * 60);
        Cache::set('user_token_' . $this->moduleName . '_' . $user->id, $token, 7 * 24 * 60 * 60); //方便根据用户id及时清除用户token

        //记录登录信息
        UserLoginRecord::recordSuccess($user->id, $this->args);
        return $this->makeApiReturn('登录成功', $user->hidden(['password']), ErrorCode::OK, HttpReponseCode::Success_OK);
    }

    protected function modifyProfile()
    {
        (new ModifyProfile())->runCheck();

        //检查用户是否存在
        $user = (new UserModel())->where([
            ['id', '=', $this->args['id']],
            ['module', '=', $this->moduleName],
        ])->append(['img_info'])->findOrEmpty();
        if ($user->isEmpty()) {
            return $this->makeApiReturn('用户不存在', [], ErrorCode::ClientError_AuthError, HttpReponseCode::ClientError_Forbidden);
        }
        //检查账户类型
        if (
            ($user->role_flag & UserModel::ROLE_FLAGS_COMMON_USER) === 0 &&
            ($user->role_flag & UserModel::ROLE_FLAGS_MAIN_ADMIN) === 0 &&
            ($user->role_flag & UserModel::ROLE_FLAGS_COMMON_ADMIN) === 0
        ) {
            return $this->makeApiReturn('无效账户', [], ErrorCode::ClientError_AuthError, HttpReponseCode::ClientError_Forbidden);
        }
        //检查状态
        if ($user->status !== 1) {
            return $this->makeApiReturn('账户被禁止', [], ErrorCode::ClientError_AuthError, HttpReponseCode::ClientError_Forbidden);
        }
        //查看是否本人操作
        if ($user->id != Request::instance()->userInfo->id) {
            return $this->makeApiReturn('非本人操作被禁止', [], ErrorCode::ClientError_AuthError, HttpReponseCode::ClientError_Forbidden);
        }

        //更新用户
        $this->args['update_time'] = time();
        $user->allowField([
            'img', 'birthday', 'sex', 'nickname', 'realname', 'update_time'
        ])->save($this->args);
        return $this->makeApiReturn('修改成功', $user->visible(['id', 'nickname', 'realname', 'username', 'phone', 'img', 'sex', 'birthday']), ErrorCode::OK, HttpReponseCode::Success_OK);
    }

    protected function addFeedBack()
    {
        (new AddFeedBack())->runCheck();

        $this->args['user_id'] = Request::instance()->userInfo->id;
        $this->args['create_time'] = time();
        UserFeedback::create($this->args);
        return $this->makeApiReturn('反馈成功');
    }

    protected function doLogout()
    {
        //检查用户是否存在
        if (!empty(Request::instance()->userInfo)) {
            $user =  Request::instance()->userInfo;
            $user->is_online = 0;
            $user->save();
            Cache::delete('user_token_' . $this->moduleName . '_' . Request::instance()->token);
            Cache::delete('user_token_' . $this->moduleName . '_' . $user->id);
        }

        return $this->makeApiReturn('退出成功');
    }

    protected function modifyPhoneByPwd()
    {
        //检查用户是否存在
        $user = (new UserModel())->where('id', Request::instance()->userInfo->id)->findOrEmpty();
        if ($user->isEmpty()) {
            return $this->makeApiReturn('用户不存在', [], ErrorCode::ClientError_AuthError, HttpReponseCode::ClientError_Forbidden);
        }
        //查看是否本人操作
        if ($user->id != Request::instance()->userInfo->id) {
            return $this->makeApiReturn('非本人操作被禁止', [], ErrorCode::ClientError_AuthError, HttpReponseCode::ClientError_Forbidden);
        }
        //检测密码
        if ($user->password !== Encrypt::encrypt($this->args['password'])) {
            return $this->makeApiReturn('密码错误', [], ErrorCode::ClientError_AuthError, HttpReponseCode::ClientError_Forbidden);
        }
        //检查状态
        if ($user->status !== 1) {
            return $this->makeApiReturn('无效操作状态', [], ErrorCode::ClientError_AuthError, HttpReponseCode::ClientError_Forbidden);
        }
        (new PhoneCheck())->runCheck();
        $checkPhone = (new UserModel())->where('phone', $this->args['phone'])->findOrEmpty();
        if (!$checkPhone->isEmpty()) {
            return $this->makeApiReturn('新手机号被占用', [], ErrorCode::ClientError_ArgsWrong, HttpReponseCode::ClientError_BadRequest);
        }
        (new SmsCodeCheck())->runCheck();

        $user->status = 1;
        $user->phone = $this->args['phone'];
        $user->is_online = 0;
        $user->save();
        Cache::delete('user_token_' . $this->moduleName . '_' . Request::instance()->token);
        Cache::delete('user_token_' . $this->moduleName . '_' . $user->id);

        return $this->makeApiReturn('更换成功，请重新登录');
    }

    protected function modifyPwdByPwd()
    {
        (new ModifyPwdByPwd())->runCheck();
        if ($this->args['old'] == $this->args['new']) {
            return $this->makeApiReturn('新密码不能与原密码相同', [], ErrorCode::ClientError_AuthError, HttpReponseCode::ClientError_Forbidden);
        }
        //检查用户是否存在
        $user = (new UserModel())->where('id', Request::instance()->userInfo->id)->findOrEmpty();
        if ($user->isEmpty()) {
            return $this->makeApiReturn('用户不存在', [], ErrorCode::ClientError_AuthError, HttpReponseCode::ClientError_Forbidden);
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
        Cache::delete('user_token_' . $this->moduleName . '_' . $user->id);


        return $this->makeApiReturn('密码修改成功，请重新登录');
    }

    protected function modifyPwdByCode()
    {
        (new Password())->runCheck();
        (new PhoneCheck())->runCheck();
        (new SmsCodeCheck())->runCheck();

        //检查用户是否存在
        $user = (new UserModel())->where('phone', $this->args['phone'])->findOrEmpty();
        if ($user->isEmpty()) {
            return $this->makeApiReturn('账号不存在', [], ErrorCode::ClientError_AuthError, HttpReponseCode::ClientError_Forbidden);
        }
        //检查状态
        if ($user->status !== 1) {
            return $this->makeApiReturn('无效操作状态', [], ErrorCode::ClientError_AuthError, HttpReponseCode::ClientError_Forbidden);
        }
        if ($user->password == Encrypt::encrypt($this->args['password'])) {
            return $this->makeApiReturn('新密码不能与原密码相同', [], ErrorCode::ClientError_AuthError, HttpReponseCode::ClientError_Forbidden);
        }
        $user->password = Encrypt::encrypt($this->args['password']);
        $user->is_online = 0;
        $user->save();
        Cache::delete('user_token_' . $this->moduleName . '_' . Request::instance()->token);
        Cache::delete('user_token_' . $this->moduleName . '_' . $user->id);

        return $this->makeApiReturn('密码已修改，请重新登录');
    }

    protected function doUnRegist()
    {
        (new PhoneCheck())->runCheck();
        (new SmsCodeCheck())->runCheck();
        //检查用户是否存在
        $user = (new UserModel())->where('phone', $this->args['phone'])->findOrEmpty();
        if ($user->isEmpty()) {
            return $this->makeApiReturn('账号不存在', [], ErrorCode::ClientError_AuthError, HttpReponseCode::ClientError_Forbidden);
        }
        //检查状态
        if ($user->status !== 1) {
            return $this->makeApiReturn('无效操作状态', [], ErrorCode::ClientError_AuthError, HttpReponseCode::ClientError_Forbidden);
        }
        //查看是否本人操作
        if ($user->id != Request::instance()->userInfo->id) { //TODO 检查所有使用的地方
            return $this->makeApiReturn('非本人操作被禁止', [], ErrorCode::ClientError_AuthError, HttpReponseCode::ClientError_Forbidden);
        }
        $user->status = -1;
        $user->is_online = 0;
        $user->save();
        Cache::delete('user_token_' . $this->moduleName . '_' . Request::instance()->token);
        Cache::delete('user_token_' . $this->moduleName . '_' . $user->id);

        return $this->makeApiReturn('注销成功');
    }
}

<?php

declare(strict_types=1);

namespace cigoadmin\middleware;

use cigoadmin\library\ErrorCode;
use cigoadmin\library\HttpReponseCode;
use cigoadmin\library\traites\ApiCommon;
use cigoadmin\model\User;
use Closure;
use think\facade\Request as RequestAlias;
use think\Request;
use think\Response;

/**
 * 检查用户是否有请求权限
 *
 * Class ApiCheckUserAuth
 * @package cigoadmin\middleware
 */
class ApiCheckUserAuth
{
    use ApiCommon;

    /**
     * 处理请求
     *
     * @param Request $request
     * @param Closure $next
     * @return Response
     */
    public function handle($request, Closure $next)
    {
        $request->token = $request->header('Cigo-Token');
        if (empty($request->token)) {
            abort($this->makeApiReturn(
                '请登录并提供token',
                [],
                ErrorCode::ClientError_TokenError,
                HttpReponseCode::ClientError_Unauthorized
            ));
        }
        $userInfo = (new User())->where([
            ['token', '=', $request->token],
            ['status', '=', 1]
        ])->findOrEmpty();
        if ($userInfo->isEmpty()) {
            abort($this->makeApiReturn(
                '无此用户或禁用',
                ['token' => $request->token],
                ErrorCode::ClientError_TokenError,
                HttpReponseCode::ClientError_BadRequest
            ));
        }
        //非管理员不可操作
        if (
            ($userInfo['role_flag'] & User::ROLE_FLAGS_MAIN_ADMIN) === 0 &&
            ($userInfo['role_flag'] & User::ROLE_FLAGS_COMMON_ADMIN) === 0
        ) {
            return $this->makeApiReturn('非管理员1', [], ErrorCode::ClientError_AuthError, HttpReponseCode::ClientError_Forbidden);
        }

        //检查token是否超时
        if (time() - $userInfo->last_log_time > config('cigoadmin.LOGIN_TIMEOUT')) {
            abort($this->makeApiReturn(
                '登录超时，请重新登录',
                [],
                ErrorCode::ClientError_TokenError,
                HttpReponseCode::ClientError_Unauthorized
            ));
        }
        RequestAlias::instance()->userInfo  = $userInfo;

        return $next($request);
    }
}

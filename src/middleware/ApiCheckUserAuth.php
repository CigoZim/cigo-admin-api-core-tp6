<?php

declare(strict_types=1);

namespace cigoadmin\middleware;

use cigoadmin\library\ErrorCode;
use cigoadmin\library\HttpReponseCode;
use cigoadmin\library\traites\ApiCommon;
use cigoadmin\model\User;
use Closure;
use think\facade\Cache;
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

        $request->tokenInfo = Cache::get('user_token_' . input('cigo_append_moduleName') . '_' . $request->token, []);
        if (empty($request->tokenInfo)) {
            abort($this->makeApiReturn(
                '登录超时',
                [],
                ErrorCode::ClientError_TokenError,
                HttpReponseCode::ClientError_Unauthorized
            ));
        }
        $request->userInfo = (new User())->where('id', $request->tokenInfo['userId'])->findOrEmpty();
        if ($request->userInfo->isEmpty()) {
            abort($this->makeApiReturn(
                '用户不存在',
                ['token' => $request->token],
                ErrorCode::ClientError_TokenError,
                HttpReponseCode::ClientError_BadRequest
            ));
        }
        if ($request->userInfo->status != 1) {
            abort($this->makeApiReturn(
                '用户被禁用',
                ['token' => $request->token],
                ErrorCode::ClientError_TokenError,
                HttpReponseCode::ClientError_BadRequest
            ));
        }

        //非管理员不可操作
        if (
            ($request->userInfo['role_flag'] & User::ROLE_FLAGS_MAIN_ADMIN) === 0 &&
            ($request->userInfo['role_flag'] & User::ROLE_FLAGS_COMMON_ADMIN) === 0
        ) {
            return $this->makeApiReturn('非管理员', [], ErrorCode::ClientError_AuthError, HttpReponseCode::ClientError_Forbidden);
        }

        return $next($request);
    }
}

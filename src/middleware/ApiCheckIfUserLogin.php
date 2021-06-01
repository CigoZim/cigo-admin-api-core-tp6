<?php

declare(strict_types=1);

namespace cigoadmin\middleware;

use cigoadmin\library\traites\ApiCommon;
use cigoadmin\model\User;
use Closure;
use think\facade\Cache;
use think\Request;
use think\Response;

/**
 * 如果用户登录则检查(如记录浏览用户，登录才记录，不登录也可浏览)
 *
 * Class ApiCheckIfUserLogin
 * @package cigoadmin\middleware
 */
class ApiCheckIfUserLogin
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
        $token = $request->header('Cigo-Token');
        if (!empty($token)) {
            $request->token = $token;

            $tokenInfo = Cache::get('user_token_' . input('cigo_append_moduleName') . '_' . $request->token, []);
            if (!empty($tokenInfo)) {
                $request->tokenInfo = $tokenInfo;

                $userInfo = (new User())->where('id', $request->tokenInfo['userId'])->findOrEmpty();
                if (!$userInfo->isEmpty() && $userInfo->status == 1) {
                    $request->userInfo = $userInfo;
                }
            }
        }

        return $next($request);
    }
}

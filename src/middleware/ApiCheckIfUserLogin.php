<?php

declare(strict_types=1);

namespace cigoadmin\middleware;

use cigoadmin\library\traites\ApiCommon;
use cigoadmin\model\User;
use Closure;
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
        $request->token = $request->header('Cigo-Token');
        if (!empty($request->token)) {
            $userInfo = (new User())->where([
                ['token', '=', $request->token],
                ['status', '=', 1]
            ])->findOrEmpty();
            if (!$userInfo->isEmpty()) {
                $request->userInfo = $userInfo;
            }
        }

        return $next($request);
    }
}

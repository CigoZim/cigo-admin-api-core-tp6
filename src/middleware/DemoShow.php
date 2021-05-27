<?php

declare(strict_types=1);

namespace cigoadmin\middleware;

use cigoadmin\library\ErrorCode;
use cigoadmin\library\HttpReponseCode;
use cigoadmin\library\traites\ApiCommon;
use Closure;
use think\Request;
use think\Response;

class DemoShow
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
        if (env('cigo-admin.demo-show')) {
            abort($this->makeApiReturn(
                '当前为演示版，避免影响其他人查看，请勿修改',
                [],
                ErrorCode::ServerError_OTHER_ERROR,
                HttpReponseCode::ClientError_Forbidden
            ));
        }
        return $next($request);
    }
}

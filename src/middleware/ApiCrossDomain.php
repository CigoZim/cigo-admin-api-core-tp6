<?php
declare (strict_types=1);

namespace cigoadmin\middleware;

use Closure;
use think\Request;
use think\Response;

/**
 * 跨域检查
 *
 * Class ApiCrossDomain
 * @package cigoadmin\middleware
 */
class ApiCrossDomain
{
    protected $allowHeader = [
        'Access-Control-Allow-Origin' => '*',
        'Access-Control-Allow-Credentials' => 'true',
        'Access-Control-Allow-Headers' => 'Cigo-Device-Type, Cigo-Timestamp, Cigo-Token, Cigo-Sign, Origin, X-Requested-With,Content-Type, Accept, Authorization',
        'Access-Control-Expose-Headers' => 'Cigo-Device-Type, Cigo-Timestamp, Cigo-Token, Cigo-Sign, Origin, X-Requested-With,Content-Type, Accept, Authorization',
        'Access-Control-Allow-Methods' => 'GET, POST, PATCH, PUT, DELETE, OPTIONS',
    ];

    /**
     * 允许跨域请求
     * @access public
     * @param Request $request
     * @param Closure $next
     * @param array $header
     * @return Response
     */
    public function handle($request, Closure $next, ?array $header = [])
    {
        $header = empty($header) ? $this->allowHeader: array_merge($this->allowHeader, $header);

        if ($request->method(true) == 'OPTIONS') {
            return Response::create()->code(204)->header($header);
        }

        return $next($request)->header($header);
    }
}

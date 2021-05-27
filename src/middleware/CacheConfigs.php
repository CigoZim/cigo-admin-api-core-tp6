<?php

declare(strict_types=1);

namespace cigoadmin\middleware;

use cigoadmin\model\SystemConfig;
use Closure;
use think\facade\Cache;
use think\facade\Config;
use think\Request;
use think\Response;

/**
 * 配置缓存
 *
 * Class CacheConfigs
 * @package cigoadmin\middleware
 */
class CacheConfigs
{
    /**
     * 检查并缓存系统配置
     * @access public
     * @param Request $request
     * @param Closure $next
     * @return Response
     */
    public function handle($request, Closure $next)
    {
        $this->checkSystemConfigFromDb();

        return $next($request);
    }

    /**
     * 检查系统配置（存储于数据库的配置）
     */
    private function checkSystemConfigFromDb()
    {
        //检测配置缓存是否存在
        $config = Cache::get(SystemConfig::CACHED_DB_SYSTEM_CONFIG_DATA, false);
        //追加到当前配置中，有则覆盖文件配置，并直接缓存
        if ($config && !empty($config)) {
            foreach ($config as $configFile => $configItems) {
                Config::set($configItems, $configFile);
            }
            return;
        }

        //读取数据库配置项
        $configInDb = (new SystemConfig())->getCanCacheList();
        if ($configInDb->isEmpty()) {
            return;
        }

        // 缓存数据
        $config = [];
        foreach ($configInDb as $key => $item) {
            if (!isset($config[$item['config_file']])) {
                $config[$item['config_file']] = [];
            }

            if (in_array($item['edit_type'], [
                SystemConfig::EDIT_TYPE_KEY_VALUE,
                SystemConfig::EDIT_TYPE_INPUT_MULTI,
                SystemConfig::EDIT_TYPE_IMG_MULTI
            ])) {
                $itemObj = json_decode($item['value'], true);
                if ($itemObj) {
                    $config[$item['config_file']][$item['flag']] = $itemObj;
                }
            } else {
                $config[$item['config_file']][$item['flag']] = $item['value'];
            }
        }
        foreach ($config as $configFile => $configItems) {
            Config::set($configItems, $configFile);
        }
        Cache::set(SystemConfig::CACHED_DB_SYSTEM_CONFIG_DATA, $config);
    }

    /**
     * 清空系统配置缓存
     */
    public static function clearSystemConfigCache()
    {
        Cache::set(SystemConfig::CACHED_DB_SYSTEM_CONFIG_DATA, false);
    }
}

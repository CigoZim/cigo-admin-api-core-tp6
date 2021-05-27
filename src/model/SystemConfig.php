<?php

declare(strict_types=1);

namespace cigoadmin\model;

use think\facade\Request;
use think\Model;

class SystemConfig extends Model
{
    protected $table = 'cg_system_config';

    const CACHED_DB_SYSTEM_CONFIG_DATA = "CACHED_DB_SYSTEM_CONFIG_DATA";

    const EDIT_TYPE_INPUT = "input";
    const EDIT_TYPE_TEXTAREA = "textarea";
    const EDIT_TYPE_IMG = "img";
    const EDIT_TYPE_EDITOR = "editor";
    const EDIT_TYPE_SLIDER = "slider";
    const EDIT_TYPE_SELECTOR = "selector";

    const EDIT_TYPE_KEY_VALUE= "key_value";
    const EDIT_TYPE_INPUT_MULTI = "input_multi";
    const EDIT_TYPE_IMG_MULTI = "img_multi";


    public function getCanCacheList()
    {
        $dataList = $this
            ->field('config_file, flag, label, edit_type, config, value')
            ->where(['cache_flag' => 1])
            ->order(['sort' => 'desc', 'create_time' => 'desc'])
            ->select();
        return $dataList;
    }
}

<?php

declare(strict_types=1);

namespace cigoadmin\validate;

use cigoadmin\library\ApiBaseValidate;

class AddAuthGroup extends ApiBaseValidate
{
    /**
     * 定义验证规则
     * 格式：'字段名'    =>    ['规则1','规则2'...]
     *
     * @var array
     */
    protected $rule = [
        'title' => 'require',
        'pid' => 'require',
        'path' => 'require',
    ];

    /**
     * 定义错误信息
     * 格式：'字段名.规则名'    =>    '错误信息'
     *
     * @var array
     */
    protected $message = [
        'title.require' => '请配置角色名称',
        'pid.require' => '未提供pid',
        'path.require' => '未提供path',
    ];
}

<?php

declare(strict_types=1);

namespace cigoadmin\validate;

use cigoadmin\library\ApiBaseValidate;

class PhoneFormatCheck extends ApiBaseValidate
{
    /**
     * 定义验证规则
     * 格式：'字段名'    =>    ['规则1','规则2'...]
     *
     * @var array
     */
    protected $rule = [
        'phone' => 'mobile'
    ];

    /**
     * 定义错误信息
     * 格式：'字段名.规则名'    =>    '错误信息'
     *
     * @var array
     */
    protected $message = [
        'phone.mobile' => '手机号码格式不对',
    ];
}

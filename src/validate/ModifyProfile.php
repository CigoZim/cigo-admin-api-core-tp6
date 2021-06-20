<?php
declare (strict_types=1);

namespace cigoadmin\validate;

use cigoadmin\library\ApiBaseValidate;

class ModifyProfile extends ApiBaseValidate
{
    /**
     * 定义验证规则
     * 格式：'字段名'    =>    ['规则1','规则2'...]
     *
     * @var array
     */
    protected $rule = [
        'id' => 'require',
        'img' => 'number',
        'birthday' => 'number',
        'sex' => 'in:1,2,3',
        'nickname' => 'min:2|max:15',
    ];

    /**
     * 定义错误信息
     * 格式：'字段名.规则名'    =>    '错误信息'
     *
     * @var array
     */
    protected $message = [
        'id.require' => '未提供编号',
        'img.number' => '头像错误',
        'birthday.number' => '生日错误',
        'sex.in' => '性别错误',
        'nickname.min' => '昵称最少2个字符',
        'nickname.max' => '昵称最多10个字符',
    ];
}

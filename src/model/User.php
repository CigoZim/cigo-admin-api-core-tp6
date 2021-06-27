<?php

declare(strict_types=1);

namespace cigoadmin\model;

use cigoadmin\controller\FileUpload;
use think\Model;

/**
 * 用户模型
 * Class User
 * @package cigoadmin\model
 */
class User extends Model
{
    use FileUpload;

    protected $table = 'cg_user';

    const  ROLE_FLAGS_COMMON_USER = 1;
    const  ROLE_FLAGS_COMMON_ADMIN = 2;
    const  ROLE_FLAGS_MAIN_ADMIN = 4;

    public function getUsernameAttr($value, $data)
    {
        return empty($data['username']) ? $data['phone'] : $data['username'];
    }

    public function getShowNameAttr($value, $data)
    {
        if (!empty($data['realname'])) {
            return $data['realname'];
        } else if (!empty($data['nickname'])) {
            return $data['nickname'];
        } else if (!empty($data['username'])) {
            return $data['username'];
        } else if (!empty($data['phone'])) {
            return $data['phone'];
        } else {
            return '***';
        }
    }

    public function getAuthGroupInfoAttr($value, $data)
    {
        $ids = json_decode($data['auth_group'], true);
        $groups = UserMgAuthGroup::where('id', 'in', $ids)->select();
        return $groups->isEmpty() ? [] : $groups->visible(['id', 'title']);
    }

    public function getImgInfoAttr($value, $data)
    {
        return $this->getFileInfo($data['img']);
    }
}

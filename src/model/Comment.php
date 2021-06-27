<?php

declare(strict_types=1);

namespace cigoadmin\model;

use think\facade\Request;
use think\Model;

class Comment extends Model
{
    protected $table = 'cg_comment';

    public function getUserInfoAttr($value, $data)
    {
        $res = User::where('id', $data['user_id'])->visible(['id', 'nickname', 'phone', 'realname'])->append(['img_info'])->findOrEmpty();
        return $res->isEmpty() ? null : $res;
    }

    public function getFirstPageInteractionAttr($value, $data)
    {
        return CommentInteraction::where([
            ['comment_id', '=', $data['id']],
            ['status', '=', 1]
        ])->append(['user_info', 'target_user_info'])->order('id desc')->limit(5)->select();
    }

    public function getIsLikeAttr($value, $data)
    {
        $userInfo = Request::instance()->userInfo;
        if (empty($userInfo)) {
            return 0;
        }
        $res = UserLike::where([
            ['content_type', '=', 'comment'],
            ['content_id', '=', $data['id']],
            ['user_id', '=', $userInfo->id]
        ])->findOrEmpty();
        return $res->isEmpty() ? 0 : 1;
    }

    public function getIsReportAttr($value, $data)
    {
        $userInfo = Request::instance()->userInfo;
        if (empty($userInfo)) {
            return 0;
        }
        $res = UserReport::where([
            ['content_type', '=', 'comment'],
            ['content_id', '=', $data['id']],
            ['user_id', '=', $userInfo->id]
        ])->findOrEmpty();
        return $res->isEmpty() ? 0 : 1;
    }

    public function getNumLikeAttr($value, $data)
    {
        return UserLike::where([
            ['content_type', '=', 'comment'],
            ['content_id', '=', $data['id']],
        ])->count() + $data['num_like'];
    }

    public function getNumReportAttr($value, $data)
    {
        return UserReport::where([
            ['content_type', '=', 'comment'],
            ['content_id', '=', $data['id']],
        ])->count() + $data['num_report'];
    }
}

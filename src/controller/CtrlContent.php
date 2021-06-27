<?php

namespace cigoadmin\controller;

use cigoadmin\library\traites\ApiCommon;
use cigoadmin\model\Comment;
use cigoadmin\model\CommentInteraction;
use cigoadmin\model\News;
use cigoadmin\model\User;
use cigoadmin\validate\CtrlContent as ValidateCtrlContent;
use cigoadmin\validate\ListPage;
use think\facade\Db;
use think\facade\Request;

trait CtrlContent
{
    use ApiCommon;

    protected function makeCtrlModel($ctrlType = '')
    {
        $model = null;
        switch ($ctrlType) {
            case 'report':
                $model = Db::table('cg_user_report');
                break;
            case 'collection':
                $model = Db::table('cg_user_collection');
                break;
            case 'like':
                $model = Db::table('cg_user_like');
                break;
            case 'view':
                $model = Db::table('cg_user_view');
                break;
            default:
                break;
        }
        return $model;
    }

    protected function ctrlContent()
    {
        (new ValidateCtrlContent())->runCheck();

        if ($this->args['ctrlType']  == 'view' && empty(Request::instance()->userInfo)) {
            return $this->success('操作成功');
        }

        $model = $this->makeCtrlModel($this->args['ctrlType']);
        if (!$model) {
            return $this->error('操作类型错误');
        }
        if (isset($this->args['flag']) && $this->args['flag'] == 1) {
            //检查是否重复操作
            $res = $model->where([
                'content_type' => $this->args['content_type'],
                'content_id' => $this->args['id'],
                'user_id' => Request::instance()->userInfo->id,
            ])->find();
            if ($res) {
                //忽略浏览操作
                if ($this->args['ctrlType'] == 'view') {
                    return $this->success('操作成功');
                }
                return $this->error('请勿重复操作');
            }

            //执行添加
            $data = [
                'content_type' => $this->args['content_type'],
                'content_id' => $this->args['id'],
                'user_id' => Request::instance()->userInfo->id,
                'create_time' => time()
            ];
            switch ($this->args['ctrlType']) {
                case 'collection':
                    $tips = '收藏成功';
                    break;
                case 'like':
                    $tips = '点赞成功';
                    break;
                case 'report':
                    $tips = '举报成功';
                    $data['reason'] = $this->args['reason'];
                    $data['summary'] = empty($this->args['summary']) ? '' : $this->args['summary'];
                    break;
                case 'view':
                    $tips = '操作成功';
                    break;
                default:
                    return $this->error('未知操作');
            }
            $model->insert($data);
        } else {
            switch ($this->args['ctrlType']) {
                case 'collection':
                    $tips = '收藏取消';
                    break;
                case 'like':
                    $tips = '点赞取消';
                    break;
                default:
                    return $this->error('未知操作');
            }
            $model->where([
                'content_type' => $this->args['content_type'],
                'content_id' => $this->args['id'],
                'user_id' => Request::instance()->userInfo->id,
            ])->delete();
        }
        return $this->success($tips);
    }


    public function mineCtrlContent()
    {
        (new ListPage())->runCheck();

        $model = $this->makeCtrlModel($this->args['ctrlType']);
        if (!$model) {
            return $this->error('操作类型错误');
        }
        if (!empty($this->args['page']) && !empty($this->args['pageSize'])) {
            $model->page(intval($this->args['page']), intval($this->args['pageSize']));
        }
        //TODO 修改为非耦合
        $map = [
            ['user_id', '=', Request::instance()->userInfo->id]
        ];
        if (!empty($this->args['content_type'])) {
            $map[] = ['content_type', 'in', $this->args['content_type']];
        }
        $model->where($map);
        $count = $model->count();
        $model->withAttr('content_data', function ($value, $data) {
            $map = [
                ['id', '=', $data['content_id']],
                ['status', '=', 1]
            ];
            switch ($data['content_type']) {
                case 'news':
                    $model = News::where($map);
                    $append = ['img_info', 'num_view_show'];
                    $hidden = [];
                    break;
                default:
                    return $this->makeAttachContentData($data);
            }
            $data = $model->append($append)->hidden($hidden)->findOrEmpty();
            return $data->isEmpty() ? null : $data;
        })->withAttr('comment_data', function ($value, $data) {
            $map = [
                ['id', '=', $data['content_id']],
                ['status', '=', 1]
            ];
            switch ($data['content_type']) {
                case 'comment':
                    $model = Comment::where($map);
                    $append = ['user_info', 'first_page_interaction', 'is_like', 'is_report'];
                    $hidden = [];
                    break;
                case 'interaction':
                    $model = CommentInteraction::where($map);
                    $append = ['user_info', 'first_page_interaction', 'is_like', 'is_report'];
                    $hidden = [];
                    break;
                default:
                    return null;
            }
            $data = $model->withAttr('user_info', function ($value, $data) {
                $userInfo = User::where('id', $data['user_id'])->append(['img_info'])->visible(['id', 'nickname', 'realname', 'phone', 'img_info'])->findOrEmpty();
                return $userInfo->isEmpty() ? null : $userInfo;
            })
                ->append($append)
                ->hidden($hidden)
                ->findOrEmpty();
            return $data->isEmpty() ? null : $data;
        });

        $dataList = $model->append(['content_data', 'comment_data'])
            ->order('id desc')
            ->select();

        return $this->makeApiReturn('获取成功', [
            'count' => $count,
            'dataList' => $dataList->isEmpty() ? [] : $dataList
        ]);
    }

    protected function makeAttachContentData($data = [])
    {
        return null;
    }
}

<?php

namespace cigoadmin\controller;

use cigoadmin\library\ErrorCode;
use cigoadmin\library\HttpReponseCode;
use cigoadmin\library\traites\ApiCommon;
use cigoadmin\model\Comment as CommentModel;
use cigoadmin\model\CommentInteraction;
use cigoadmin\validate\AddComment;
use cigoadmin\validate\ListPage;
use think\facade\Request;

/**
 * Trait Manager
 * @package cigoadmin\controller
 */
trait Comment
{
    use ApiCommon;

    protected function addComment()
    {
        (new AddComment())->runCheck();

        $model = null;
        $addData = [
            'comment' => $this->args['comment'],
            'user_id' => Request::instance()->userInfo->id,
            'create_time' => time()
        ];
        switch ($this->args['mode']) {
            case 1: //回复评论
                {
                    $commentData = CommentModel::where('id', $this->args['target_id'])->findOrEmpty();
                    if ($commentData->isEmpty()) {
                        return $this->makeApiReturn('被回复评论不存在', [], ErrorCode::ClientError_ArgsWrong, HttpReponseCode::ClientError_BadRequest);
                    }
                    //检查是否回复自己
                    if ($commentData->user_id == Request::instance()->userInfo->id) {
                        return $this->makeApiReturn('不允许回复自己评论', [], ErrorCode::ClientError_ArgsWrong, HttpReponseCode::ClientError_BadRequest);
                    }
                    $addData['comment_id'] = $commentData->id;
                    $addData['target_user_id'] = $commentData->user_id;
                    $model = new CommentInteraction();
                }
                break;
            case 2: //回复交互
                {
                    $interactionData = CommentInteraction::where('id', $this->args['target_id'])->findOrEmpty();
                    if ($interactionData->isEmpty()) {
                        return $this->makeApiReturn('被回复交互不存在', [], ErrorCode::ClientError_ArgsWrong, HttpReponseCode::ClientError_BadRequest);
                    }
                    //检查是否回复自己
                    if ($interactionData->user_id == Request::instance()->userInfo->id) {
                        return $this->makeApiReturn('不允许回复自己', [], ErrorCode::ClientError_ArgsWrong, HttpReponseCode::ClientError_BadRequest);
                    }
                    $addData['comment_id'] = $interactionData->comment_id;
                    $addData['pid'] = $interactionData->id;
                    $addData['target_user_id'] = $interactionData->user_id;
                    $model = new CommentInteraction();
                }
                break;
            case 0: //评论内容
            default:
                $model = new CommentModel();
                $addData['content_type'] = $this->args['type'];
                $addData['content_id'] = $this->args['target_id'];
                break;
        }

        $model->save($addData);
        $id = $model->id;
        $data = $model->findOrEmpty($id);
        return $this->makeApiReturn('评论成功', $data->isEmpty() ? [] : $this->ctrlResponseData($this->args['mode'], $data));
    }

    /**
     * @param int $mode
     * @param string $data
     * @return mixed
     */
    protected abstract function ctrlResponseData($mode = 0, $data = '');

    protected function getCommentList($checkArgs = '')
    {
        if ($checkArgs) {
            $checkArgs();
        }
        (new ListPage())->runCheck();

        $map = [
            ['content_type', '=', $this->args['type']],
            ['content_id', '=', $this->args['id']],
            ['status', '=', 1],
        ];

        $model = CommentModel::where($map);
        $count = $model->count();
        if (!empty($this->args['page']) && !empty($this->args['pageSize'])) {
            $model->page(intval($this->args['page']), intval($this->args['pageSize']));
        }
        $dataList = $model->order('id desc')->append(['user_info', 'first_page_interaction', 'is_like', 'is_report'])->select();
        return $this->makeApiReturn('获取成功', [
            'count' => $count,
            'dataList' =>
            $dataList->isEmpty() ? [] : $dataList
        ]);
    }

    protected function getCommentInteractionList()
    {
        (new ListPage())->runCheck();

        if (empty($this->args['comment_id'])) {
            return $this->makeApiReturn('请提供评论编号', [], ErrorCode::ClientError_ArgsWrong, HttpReponseCode::ClientError_BadRequest);
        }

        $comment = CommentModel::where('id', $this->args['comment_id'])->findOrEmpty();
        if ($comment->isEmpty()) {
            return $this->makeApiReturn('回复评论不存在', [], ErrorCode::ClientError_ArgsWrong, HttpReponseCode::ClientError_BadRequest);
        }

        $map = [
            ['comment_id', '=', $this->args['comment_id']],
            ['status', '=', 1],
        ];

        $model = CommentInteraction::where($map);
        $count = $model->count();
        if (!empty($this->args['page']) && !empty($this->args['pageSize'])) {
            $model->page(intval($this->args['page']), intval($this->args['pageSize']));
        }
        $dataList = $model->order('id desc')->withAttr('comment_info', function ($value, $data) {
            $res = CommentModel::where('id', $data['comment_id'])->visible(['id', 'comment'])->findOrEmpty();
            return $res->isEmpty() ? null : $res;
        })
            ->append(['comment_info', 'user_info', 'target_user_info', 'is_like', 'is_report'])
            ->select();
        return $this->makeApiReturn('获取成功', [
            'count' => $count,
            'dataList' => $dataList->isEmpty() ? [] : $dataList
        ]);
    }
}

<?php

namespace cigoadmin\controller;

use cigoadmin\library\ErrorCode;
use cigoadmin\library\HttpReponseCode;
use cigoadmin\library\traites\ApiCommon;
use cigoadmin\library\traites\Tree;
use cigoadmin\model\UserMgAuthGroup;
use cigoadmin\model\UserMgAuthRule;
use cigoadmin\validate\AddAuthGroup;
use cigoadmin\validate\AddAuthRule;
use cigoadmin\validate\EditAuthGroup;
use cigoadmin\validate\EditAuthRule;
use cigoadmin\validate\Status;

/**
 * Trait AuthSetting
 * @package cigoadmin\controller
 */
trait AuthSetting
{
    use Tree;
    use ApiCommon;

    /**
     * 添加权限节点
     */
    protected function addAuthRule()
    {
        (new AddAuthRule())->runCheck();

        $node = (new UserMgAuthRule())->where('component_name', $this->args['component_name'])->findOrEmpty();
        if (!$node->isEmpty()) {
            return $this->makeApiReturn('组件名已存在', ['component_name' => $this->args['component_name']], ErrorCode::ClientError_ArgsWrong, HttpReponseCode::ClientError_BadRequest);
        }

        // 添加节点
        $rule = UserMgAuthRule::create($this->args);
        $rule = (new UserMgAuthRule())->where('id', $rule->id)->find();
        return $this->makeApiReturn('添加成功', $rule);
    }

    /**
     * 修改权限节点
     */
    protected function editAuthRule()
    {
        (new EditAuthRule())->runCheck();

        //检查节点是否存在
        $node = (new UserMgAuthRule())->where('id', $this->args['id'])->findOrEmpty();
        if ($node->isEmpty()) {
            return $this->makeApiReturn('节点不存在', ['id' => $this->args['id']], ErrorCode::ClientError_ArgsWrong, HttpReponseCode::ClientError_BadRequest);
        }
        //修改节点
        UserMgAuthRule::update($this->args);

        return $this->makeApiReturn('修改成功');
    }

    /**
     * 设置权限节点状态
     */
    protected function setAuthRuleStatus()
    {
        (new Status())->runCheck();

        //检查节点是否存在
        $node = (new UserMgAuthRule())->where('id', $this->args['id'])->findOrEmpty();
        if ($node->isEmpty() || $node->status == -1) {
            return $this->makeApiReturn('节点不存在', ['id' => $this->args['id']], ErrorCode::ClientError_ArgsWrong, HttpReponseCode::ClientError_BadRequest);
        }
        if ($node->status == $this->args['status']) {
            return $this->makeApiReturn('无需重复操作', ['id' => $this->args['id'], 'status' => $this->args['status']], ErrorCode::ClientError_ArgsWrong, HttpReponseCode::ClientError_BadRequest);
        }
        //状态
        UserMgAuthRule::update([
            'id' => $this->args['id'],
            'status' => $this->args['status'],
        ]);
        return $this->makeApiReturn($this->makeStatusTips());
    }


    /**
     * 获取权限节点列表
     */
    protected function getAuthRuleList()
    {
        isset($this->args['status'])
            ? $map[] = ['status', 'in', $this->args['status']]
            : $map[] = ['status', '<>', -1];
        $map[] = ['module', '=', empty($this->args['module']) ? 'admin' : $this->args['module']];
        $map[] = ['type', 'in', empty($this->args['type']) ? '0' : $this->args['type']];

        $model = (new UserMgAuthRule())->where($map);
        $count = $model->count();
        $dataList = $model->order('pid asc, sort desc, id asc')->select();
        $treeList = [];
        if ($dataList) {
            $this->convertToTree($dataList, $treeList, 0, 'pid', false);
        }
        return $this->makeApiReturn('获取成功', [
            'count' => $count,
            'dataList' => $treeList
        ]);
    }

    /**
     * 添加权限分组
     */
    protected function addAuthGroup()
    {
        (new AddAuthGroup())->runCheck();

        //添加节点
        $group = UserMgAuthGroup::create([
            'module' => empty($this->args['module']) ? 'admin' : $this->args['module'],
            'title' => $this->args['title'],
            'pid' => $this->args['pid'],
            'path' => $this->args['path'],
            'sort' => $this->args['sort'],
            'rules' => $this->args['rules'],
            'summary' => $this->args['summary'],
        ]);
        $group = (new UserMgAuthGroup())->where('id', $group->id)->find();
        return $this->makeApiReturn('添加成功', $group);
    }

    /**
     * 修改权限分组
     */
    protected function editAuthGroup()
    {
        (new EditAuthGroup())->runCheck();

        //检查节点是否存在
        $group = (new UserMgAuthGroup())->where('id', $this->args['id'])->findOrEmpty();
        if ($group->isEmpty()) {
            return $this->makeApiReturn('角色不存在', ['id' => $this->args['id']], ErrorCode::ClientError_ArgsWrong, HttpReponseCode::ClientError_BadRequest);
        }
        //修改角色
        $group = UserMgAuthGroup::update([
            'id' => $this->args['id'],
            'title' => empty($this->args['title']) ? $group->title : $this->args['title'],
            'pid' => empty($this->args['pid']) ? $group->pid : $this->args['pid'],
            'path' => empty($this->args['path']) ? $group->path : $this->args['path'],
            'rules' => empty($this->args['rules']) ? $group->rules : $this->args['rules'],
            'sort' => empty($this->args['sort']) ? $group->sort : $this->args['sort'],
            'summary' => empty($this->args['summary']) ? $group->summary : $this->args['summary'],
        ]);

        return $this->makeApiReturn('修改成功', $group);
    }

    /**
     * 设置权限分组状态
     */
    protected function setAuthGroupStatus()
    {
        (new Status())->runCheck();

        //检查角色是否存在
        $group = (new UserMgAuthGroup())->where('id', $this->args['id'])->findOrEmpty();
        if ($group->isEmpty() || $group->status == -1) {
            return $this->makeApiReturn('角色不存在', ['id' => $this->args['id']], ErrorCode::ClientError_ArgsWrong, HttpReponseCode::ClientError_BadRequest);
        }
        if ($group->status == $this->args['status']) {
            return $this->makeApiReturn('无需重复操作', ['id' => $this->args['id'], 'status' => $this->args['status']], ErrorCode::ClientError_ArgsWrong, HttpReponseCode::ClientError_BadRequest);
        }
        //更新状态
        UserMgAuthGroup::update([
            'id' => $this->args['id'],
            'status' => $this->args['status'],
        ]);
        return $this->makeApiReturn($this->makeStatusTips());
    }

    /**
     * 获取权限分组列表
     */
    protected function getAuthGroupList()
    {
        isset($this->args['status'])
            ? $map[] = ['status', 'in', $this->args['status']]
            : $map[] = ['status', '<>', -1];
        $map[] = ['module', '=', empty($this->args['module']) ? 'admin' : $this->args['module']];

        $model = (new UserMgAuthGroup())->where($map);
        $count = $model->count();
        $dataList = $model->order('pid asc, sort desc, id asc')->select();
        $treeList = [];
        if ($dataList) {
            $this->convertToTree($dataList, $treeList, 0, 'pid', false);
        }
        return $this->makeApiReturn('获取成功', [
            'count' => $count,
            'dataList' => $treeList
        ]);
    }
}

<?php

declare(strict_types=1);

namespace cigoadmin\controller;

use cigoadmin\library\traites\ApiCommon;
use cigoadmin\model\News as ModelNews;
use cigoadmin\validate\AddNews;
use cigoadmin\validate\EditNews;
use cigoadmin\validate\IdCheck;
use cigoadmin\validate\ListPage;
use cigoadmin\validate\Status;
use think\facade\Db;

trait News
{
    use ApiCommon;

    //Tips_Flag //trait函数名命名规范
    protected function addNews()
    {
        (new AddNews())->runCheck();
        $this->args['create_time'] = time();
        $data = ModelNews::create($this->args);
        $data = (new ModelNews())->where('id', $data->id)->append(['img_info', 'num_view_show'])->findOrEmpty();
        return $this->success('添加成功', $data->isEmpty() ? null : $data);
    }

    public function editNews()
    {
        (new EditNews())->runCheck();
        $data = (new ModelNews())->where('id', $this->args['id'])->findOrEmpty();
        if ($data->isEmpty()) {
            return $this->error('新闻不存在', ['id' => $this->args['id']]);
        }
        $this->args['update_time'] = time();
        ModelNews::update($this->args);
        $data = (new ModelNews())->where('id', $data->id)->append(['img_info', 'num_view_show'])->findOrEmpty();
        return $this->success('修改成功', $data->isEmpty() ? null : $data);
    }

    protected function getNewsList()
    {
        (new ListPage())->runCheck();

        $map = [];
        isset($this->args['status'])
            ? $map[] = ['status', 'in', $this->args['status'] . '']
            : $map[] = ['status', '<>', -1];

        if (!empty($this->args['keywords'])) {
            $map[] = ['name', 'like', '%' . $this->args['keywords'] . '%'];
        }
        $model = (new ModelNews())->where($map);
        $count = $model->count();
        if (!empty($this->args['page']) && !empty($this->args['pageSize'])) {
            $model->page(intval($this->args['page']), intval($this->args['pageSize']));
        }
        $dataList = $model->append(['img_info', 'num_view_show'])->order('id asc')->select();
        return $this->makeApiReturn('获取成功', [
            'count' => $count,
            'dataList' => $dataList->isEmpty() ? [] : $dataList
        ]);
    }


    protected function setStatus()
    {
        (new Status())->runCheck();

        $data = (new ModelNews())->where('id', $this->args['id'])->findOrEmpty();
        if ($data->isEmpty() || $data->status == -1) {
            return $this->error('新闻不存在', ['id' => $this->args['id']]);
        }
        if ($data->status == $this->args['status']) {
            return $this->error('无需重复操作', ['id' => $this->args['id'], 'status' => $this->args['status']]);
        }
        ModelNews::update([
            'id' => $this->args['id'],
            'status' => $this->args['status'],
        ]);
        return $this->success($this->makeStatusTips());
    }
}

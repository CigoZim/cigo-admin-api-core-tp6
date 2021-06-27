<?php

declare(strict_types=1);

namespace cigoadmin\controller;

use cigoadmin\library\traites\ApiCommon;
use cigoadmin\model\UserFeedback;
use cigoadmin\validate\AddFeedBack;
use think\facade\Request;

trait Feedback
{
    use ApiCommon;

    protected function addFeedBack()
    {
        (new AddFeedBack())->runCheck();

        $this->args['user_id'] = Request::instance()->userInfo->id;
        $this->args['create_time'] = time();
        UserFeedback::create($this->args);
        return $this->makeApiReturn('反馈成功');
    }
}

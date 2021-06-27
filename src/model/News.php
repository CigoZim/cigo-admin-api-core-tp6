<?php

declare(strict_types=1);

namespace cigoadmin\model;

use cigoadmin\controller\FileUpload;
use think\Model;

/**
 * @mixin Model
 */
class News  extends Model
{
    use FileUpload;

    protected $table = 'cg_news';

    public function getImgInfoAttr($value, $data)
    {
        return $this->getFileInfo($data['img']);
    }

    public function getNumViewShowAttr($value, $data)
    {
        return UserView::where([
            ['content_type', '=', 'news'],
            ['content_id', '=', $data['id']],
        ])->count() + $data['num_view'];
    }
}

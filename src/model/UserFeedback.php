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
class UserFeedback extends Model
{
    use FileUpload;

    protected $table = 'cg_user_feedback';

    public function getImgMultiInfoAttr($value, $data)
    {
        $imgIds = json_decode($data['img_multi'], true);
        if (!$imgIds) {
            return [];
        }
        $imgMultiInfo = [];
        foreach ($imgIds as $imgId) {
            $imgInfo = $this->getFileInfo($imgId);
            $imgMultiInfo[] = $imgInfo;
        }
        return $imgMultiInfo;
    }
}

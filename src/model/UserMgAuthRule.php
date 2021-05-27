<?php

declare(strict_types=1);

namespace cigoadmin\model;

use cigoadmin\library\traites\Tree;
use think\Collection;
use think\db\exception\DataNotFoundException;
use think\db\exception\DbException;
use think\db\exception\ModelNotFoundException;
use think\Model;

/**
 * Class UserMgAuthRule
 * @package cigoadmin\model
 */
class UserMgAuthRule extends Model
{
    protected $table = 'cg_user_mg_auth_rule';

    use Tree;

    /**
     * 获取层级菜单数据
     * @param array $map
     * @return array
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function menuTree($map = array())
    {
        if (empty($map)) {
            $map = [
                ['module', '=', 'admin'],
                ['type', 'in', '0,2'],
                ['status', '<>', -1]
            ];
        }
        $dataList = $this->where($map)
            ->order('pid asc, group_sort desc, group asc, sort desc, id asc')
            ->select();
        $treeList = [];
        if ($dataList) {
            $this->convertToTree($dataList, $treeList, 0, 'pid', true);
        }
        return $treeList;
    }

    /**
     * 获取菜单基础数据
     * @param array $map
     * @return array|Collection
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function menuBase($map = array())
    {
        if (empty($map)) {
            $map = [
                ['module', '=', 'admin'],
                ['type', 'in', '0,2'],
                ['status', '<>', -1]
            ];
        }
        $baseMap = $this->where($map)
            ->order('pid asc, group_sort desc, group asc, sort desc, id asc')
            ->select();

        return $baseMap ? $baseMap : [];
    }
}

<?php

declare(strict_types=1);

namespace cigoadmin\model;

use cigoadmin\library\ErrorCode;
use cigoadmin\library\HttpReponseCode;
use cigoadmin\library\traites\ApiCommon;
use cigoadmin\library\traites\Tree;
use ClosedGeneratorException;
use think\Collection;
use think\db\exception\DataNotFoundException;
use think\db\exception\DbException;
use think\db\exception\ModelNotFoundException;
use think\facade\Request;
use think\Model;

/**
 * Class UserMgAuthRule
 * @package cigoadmin\model
 */
class UserMgAuthRule extends Model
{
    use ApiCommon;

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
        $userInfo = Request::instance()->userInfo;
        if (empty($map)) {
            $map = [
                ['module', '=', 'admin'],
                ['type', 'in', '0,2'],
                ['status', '=', 1]
            ];
        }
        if (
            ($userInfo['role_flag'] & User::ROLE_FLAGS_MAIN_ADMIN) === 0
        ) {
            // 根据权限分组获取所有权限编号
            $authGroupIds = json_decode($userInfo['auth_group'], true);
            $authGroupList = (new UserMgAuthGroup())
                ->where([
                    ['id', 'in', $authGroupIds],
                    ['status', '=', 1]
                ])->select();
            $authRuleIds = [];
            foreach ($authGroupList as $key => $item) {
                $rules = json_decode($item['rules'], true);
                if (is_array($rules)) {
                    $authRuleIds =  array_merge($authRuleIds,  $rules);
                }
            }
            $authRuleIds = array_unique($authRuleIds);
            sort($authRuleIds);

            // 限制查询有权操作的惨淡
            $map[] = ['id', 'in', $authRuleIds];
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

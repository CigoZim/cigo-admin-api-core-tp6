<?php

namespace cigoadmin\library\traites;

/**
 * Trait Tree
 * @package cigoadmin\library\traites
 * @summary 树形结构处理类
 */
trait Tree
{
    protected function convertToTree(&$srcDataList = array(), &$treeList = array(), $pid = 0, $pidKey = 'pid', $checkGroup = true, $subListKey = "sub_list")
    {
        $groupName = '';
        $groupItemIndex = -1;
        foreach ($srcDataList as $key => $item) {
            //判断当前层级
            if (isset($item[$pidKey]) && $item[$pidKey] == $pid) {
                //处理分组
                if ($checkGroup && isset($item['group']) && !empty($item['group'])) {
                    if ($groupName != $item['group']) {
                        $groupItemIndex = count($treeList);
                        $treeList[] = array(
                            'group_flag' => true,
                            'title' => $item['group'],
                            'sub_item_num' => 1,
                            'sub_item_enable_num' => $item['status'] == 1 ? 1 : 0,
                        );
                        $groupName = $item['group'];
                    } else {
                        $treeList[$groupItemIndex]['sub_item_num']++;
                        $treeList[$groupItemIndex]['sub_item_enable_num'] += $item['status'] == 1 ? 1 : 0;
                    }
                }
                // 处理当前项
                $subList = array();
                $this->convertToTree($srcDataList, $subList, $item['id'], $pidKey, $checkGroup, $subListKey);
                if (!empty($subList)) {
                    $item[$subListKey] = $subList;
                }

                $treeList[] = $item;
                unset($srcDataList[$key]);
            }
        }
    }
}

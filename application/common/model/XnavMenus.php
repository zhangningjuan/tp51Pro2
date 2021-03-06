<?php

namespace app\common\model;

use app\common\validate\XnavMenu;
use think\Db;
use \think\Model;

/**
 * Created by PhpStorm.
 * User: moTzxx
 * Date: 2018/1/11
 * Time: 16:45
 */
class XnavMenus extends BaseModel
{
    protected $validate;

    public function __construct($data = [])
    {
        parent::__construct($data);
        $this->validate = new XnavMenu();
    }

    /**
     * 获取所有正常状态的菜单列表
     * @return mixed
     */
    public function getNavMenus()
    {
        $res = $this
            ->field('*')
            ->where([['id', '>', 0], ['parent_id', '=', 0], ['status', '=', 1]])
            ->order('list_order', 'asc')
            ->select();
        foreach ($res as $key => $value) {
            $parent_id = $value['id'];
            $childRes = $this
                ->where([['parent_id', '=', $parent_id], ["status", '=', 1], ["type", '=', 0]])
                ->order('list_order', 'asc')
                ->select();
            $res[$key]['child'] = $childRes;
        }
        return isset($res)?$res->toArray():[];
    }

    /**
     * 检查当前的菜单是否在 该管理员的权限内
     * @param int $nav_id 菜单ID
     * @param int $cmsAID 管理员用户ID
     * @return bool
     */
    public function checkNavMenuMan($nav_id = 0, $cmsAID = 0)
    {
        $str = $this->getAdminMenus($cmsAID);
        $navMenus = explode('|', $str);
        $tag = in_array($nav_id, $navMenus);
        return $tag;
    }

    /**
     * 获取当前管理员权限下的 导航菜单
     * @param int $cmsAID
     * @return mixed
     */
    public function getNavMenusShow($cmsAID = 0)
    {
        if (!$cmsAID) {
            return null;
        } else {
            $str = $this->getAdminMenus($cmsAID);
            $arr = explode('|', $str);
            $rootMenus = $this->getAllRootMenus();
            $res = $this->dealForAdminShowMenus($rootMenus, $arr);
            return is_array($res) ? $res: null;
        }
    }

    /**
     * 根据管理员 获取其权限下的 菜单组合
     * @param int $id
     * @return mixed
     */
    public function getAdminMenus($id = 1)
    {
        $nav_menu_ids = Db('xadmins')
            ->alias('a')
            ->join('xadmin_roles ar', 'ar.id = a.role_id')
            ->where('a.id', $id)
            ->value('nav_menu_ids');
        return $nav_menu_ids;
    }

    /**
     * 获取所有可显示的 根级菜单
     * @return array|\PDOStatement|string|\think\Collection
     */
    public function getAllRootMenus()
    {
        $res = $this
            ->field('*')
            ->where([['id', '>', 0], ['parent_id', '=', 0], ['status', '=', 1], ['type', '=', 0]])
            ->order('list_order', 'asc')
            ->select();
        return isset($res)?$res->toArray():[];
    }

    /**
     * 处理管理员 权限所要展示的菜单项
     * @param $rootMenus
     * @param $arr
     * @return mixed
     */
    public function dealForAdminShowMenus($rootMenus, $arr)
    {
        foreach ($rootMenus as $key => $value) {
            $parent_id = $value['id'];
            if (!in_array($parent_id, $arr)) {
                unset($rootMenus[$key]);
            } else {
                $childRes = $this
                    ->where('parent_id', $parent_id)
                    ->where('status', 1)
                    ->order('list_order', 'asc')
                    ->select();
                $childRes = $this->dealForAdminShowMenus2($childRes, $arr);
                $rootMenus[$key]['child'] = $childRes;
            }
        }
        return $rootMenus;
    }

    /**
     * 嘚瑟 进一步处理
     * @param $res
     * @param $arr
     * @return mixed
     */
    public function dealForAdminShowMenus2($res, $arr)
    {
        foreach ($res as $key => $value) {
            $parent_id = $value['id'];
            if (!in_array($parent_id, $arr)) {
                unset($res[$key]);
            }
        }
        return $res;
    }


    /**
     * 获取全部可修改状态的 导航菜单数据
     * @param int $id 导航菜单 ID 标识
     * @return array|null|\PDOStatement|string|Model
     */
    public function getNavMenuByID($id = 0)
    {
        $res = $this
            ->alias('nm')
            ->field('nm.*')
            //->join('xnav_menus nm2', 'nm.parent_id = nm2.id')
            ->where('nm.id', $id)
            ->find();
        return isset($res)?$res->toArray():[];
    }

    /**
     * 获取 符合条件的 菜单数量
     * @param null $search
     * @param string $navType
     * @return float|string
     */
    public function getNavMenusCount($search = null,$navType = "F")
    {
        $where = [['id', '>', '0'], ["status", '=', 1], ["type", '=', 0]];
        if ($navType == "F"){
            $where[] = ['parent_id','=',0];
        }else{
            $where[] = ['parent_id','>',0];
        }
        $res = $this
            ->field('*')
            ->where($where)
            ->whereLike('name', '%' . $search . '%')
            ->count();
        return $res;
    }

    /**
     * 分页获取 菜单数据
     * @param $curr_page
     * @param $limit
     * @param null $search
     * @param string $navType
     * @return array|\PDOStatement|string|\think\Collection
     */
    public function getNavMenusForPage($curr_page, $limit, $search = null,$navType = "F")
    {
        $where = [['id', '>', '0'], ["status", '=', 1], ["type", '=', 0]];
        if ($navType == "F"){
            $where[] = ['parent_id','=',0];
        }else{
            $where[] = ['parent_id','>',0];
        }
        $res = $this
            ->field('*')
            ->where($where)
            ->whereLike('name', '%' . $search . '%')
            ->order(['list_order' => 'asc', 'created_at' => 'desc'])
            ->limit($limit * ($curr_page - 1), $limit)
            ->select();
        foreach ($res as $key => $v) {
            $parentData = $this->getNavMenuByID($v['parent_id']);
            if ($v['status'] == 1) {
                $res[$key]['status_tip'] = "<span class=\"layui-badge layui-bg-blue\">正常</span>";
            } else {
                $res[$key]['status_tip'] = "<span class=\"layui-badge layui-bg-cyan\">删除</span>";
            }
            $res[$key]['parent_name'] = $v['parent_id'] == 0 ? "根级菜单" : $parentData['name'];
            $res[$key]['icon'] = imgToServerView($v['icon']);
        }
        return isset($res)?$res->toArray():[];
    }

    /**
     * 添加菜单数据
     * @param $data
     * @param int $parent_id
     * @param int $authTag 是否为权限菜单
     * @return array
     */
    public function addNavMenu($data, $parent_id = 0,$authTag = 0)
    {
        if (!$parent_id){
            $navType = isset($data['navType'])?$data['navType']:"F";
            $strNavType = "parent_id_".$navType;
            $parent_id = isset($data[$strNavType])?$data[$strNavType]:0;
        }
        $addData = [
            'name' => isset($data['name']) ? $data['name'] : '',
            'parent_id' => $parent_id ? $parent_id : 0,
            'action' => empty($data['action']) ?'/': $data['action'],
            'icon' => isset($data['icon']) ? $data['icon'] : '/',
            'created_at' => date("Y-m-d H:i:s", time()),
            'list_order' => isset($data['list_order']) ? intval($data['list_order']) : 0,
            'status' => isset($data['status']) ? $data['status'] : 1,
            'type' => $authTag
        ];
        $tokenData = ['__token__' => isset($data['__token__']) ? $data['__token__'] : '',];
        $validateRes = $this->validate($this->validate, $addData, $tokenData);
        if ($validateRes['tag']) {
            $tag = $this->insert($addData);
            $validateRes['tag'] = $tag;
            $validateRes['message'] = $tag ? '菜单添加成功' : '添加失败';
        }
        return $validateRes;
    }

    /**
     * 更新菜单数据
     * @param $id
     * @param $data
     * @return array
     */
    public function editNavMenu($id, $data)
    {
       $opTag = isset($data['tag']) ? $data['tag'] : 'edit';
        $tag = 0;
        if ($opTag == 'del') {
            $tag = $this
                ->where('id', $id)
                ->update(['status' => -1]);
            $validateRes['message'] = $tag ? '删除成功' : '已删除';
        } else {
            $navType = isset($data['navType'])?$data['navType']:"F";
            $strNavType = "parent_id_".$navType;
            $parent_id = isset($data[$strNavType])?$data[$strNavType]:0;

            $saveData = [
                'name' => isset($data['name']) ? $data['name'] : '',
                'icon' => isset($data['icon']) ? $data['icon'] : '',
                'list_order' => intval($data['list_order']),
                'parent_id' => intval($parent_id),
                'action' => empty($data['action']) ?'/': $data['action'],
                'status' => intval($data['status']),
            ];
            $tokenData = ['__token__' => isset($data['__token__']) ? $data['__token__'] : '',];
            $validateRes = $this->validate($this->validate, $saveData, $tokenData);
            if ($validateRes['tag']) {
                $tag = $this
                    ->where('id', $id)
                    ->update($saveData);
                $validateRes['message'] = $tag ? '菜单修改成功' : '数据无变动';
            }

        }
        $validateRes['tag'] = $tag;
        return $validateRes;
    }

    /**
     * 获取子集导航菜单
     * @param int $parentID
     * @return array
     */
    public function getAuthChildNavMenus($parentID = 0)
    {
        $res = $this
            ->field('name,action,id')
            ->where([["parent_id", '=', $parentID], ['type', '=', 1], ['status', '=', 1]])
            ->select();
        return isset($res)?$res->toArray():[];
    }
}
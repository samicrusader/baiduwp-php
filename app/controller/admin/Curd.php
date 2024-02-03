<?php

namespace app\controller\admin;

use app\BaseController;
use app\Request;
use think\facade\Db;

class Curd extends BaseController
{
    protected $protected_field = [];
    private $tables = [
        'record' => 'records',
        'account' => 'account',
        'ip' => 'ip',
    ];
    public function list(Request $request, $page = 1, $table = '')
    {
        $table = $this->tables[$table];
        $page = (int)($page < 1 ? 1 : $page);
        if (!$table) {
            return json([
                'error' => 1,
                'msg' => 'Table name error',
            ]);
        }
        $data = Db::table($table)->order('id', 'desc')->page($page, 10)->select()->toArray();
        foreach ($this->protected_field as $field) {
            foreach ($data as $key => $value) {
                unset($data[$key][$field]);
            }
        }
        return json([
            'error' => 0,
            'data' => $data,
            'page' => $page,
        ]);
    }
    public function delete(Request $request, $id = 0, $table = '')
    {
        $table = $this->tables[$table];
        $id = (int)$id;
        if (!$table || !$id) {
            return json([
                'error' => 1,
                'msg' => 'Table name or ID field must be specified',
            ]);
        }
        $count = Db::table($table)->where('id', $id)->delete();
        return json([
            'error' => ($count === 1) ? 0 : -1,
            'msg' => "Deleted {$count} records",
        ]);
    }
    public function add(Request $request, $data = [], $table = '')
    {
        $table = $this->tables[$table];
        if (!$table) {
            return json([
                'error' => 1,
                'msg' => 'Table name must be specified',
            ]);
        }
        $count = Db::table($table)->insert($data);
        return json([
            'error' => ($count === 1) ? 0 : -1,
            'msg' => "Added {$count} records",
        ]);
    }
    protected function constructData($post, $match)
    {
        $data = [];
        foreach ($match as $key => $post_key) {
            $data[$key] = $post[$post_key] ?? '';
        }
        return $data;
    }
}

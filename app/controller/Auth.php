<?php

namespace app\controller;

use app\BaseController;
use app\Request;

class Auth extends BaseController
{
    /**
     * 检查密码
     */
    public function status()
    {
        $password = config('baiduwp.password');
        if (empty($password)) {
            return json([
                'status' => 0,
                'msg' => 'No password is required',
            ]);
        }
        if (session('Password') === $password) {
            return json([
                'status' => 2,
                'msg' => 'Logged in!',
            ]);
        }
        return json([
            'status' => 1,
            'msg' => 'Not logged in'
        ]);
    }

    public static function checkPassword(Request $request)
    {
        $password = config('baiduwp.password');
        if (empty($password)) {
            return true;
        }
        $session_password = session('Password');
        if (!empty($session_password) && $session_password === $password) {
            return true;
        }
        $pwd = $request->post('password');
        if ($pwd === $password) {
            session('Password', $pwd);
            return true;
        }
        return false;
    }
}

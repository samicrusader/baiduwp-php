<?php

namespace app\middleware;

use app\controller\Auth;
use app\Request;

class CheckPassword
{
    public function handle(Request $request, \Closure $next)
    {
        if (!Auth::checkPassword($request)) {
            // 密码错误
            return json([
                'error' => -1,
                'msg' => 'Access password is incorrect',
            ]);
        }
        return $next($request);
    }
}

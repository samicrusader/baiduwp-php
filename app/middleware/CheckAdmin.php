<?php

namespace app\middleware;

use app\Request;

class CheckAdmin
{
    public function handle(Request $request, \Closure $next)
    {
        // 检查是否要登录
        if ($request->controller() === 'admin.Index' && $request->action() === 'login') {
            return $next($request); 
        }

        // 检查是否登录
        if (!session('admin')) {
            // 判断是否是 json 请求
            if ($request->isJson()) {
                return json(['error' => 403, 'msg' => 'Please refresh and try again']);
            }
            return redirect('/admin/login');
        }

        return $next($request);
    }
}

<?php

namespace app\middleware;

use app\Request;

class CheckDb
{
    public function handle(Request $request, \Closure $next)
    {
        if (config('baiduwp.db')) {
            return $next($request);
        } else {
            return json(['error' => 500, 'msg' => 'Database isn\'t enabled, feature won\'t work.']);
        }
    }
}
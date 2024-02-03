<?php

namespace app\middleware;

use app\Request;

class CheckInstall
{
    public function handle(Request $request, \Closure $next)
    {
        if (file_exists('./../.env')) {
            $title = 'Already installed';
            $content = 'This site can\'t be reconfigured from the web interface after initial installation. Please either edit the .env file or delete it and hit up /install.';
            if ($request->isJson()) {
                return json([
                    'error' => -1,
                    'msg' => $content,
                ]);
            }
            session('msg_title', $title);
            session('msg_content', $content );
            return redirect('/message');
        }

        return $next($request);
    }
}
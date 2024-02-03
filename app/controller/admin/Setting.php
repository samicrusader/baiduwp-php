<?php

namespace app\controller\admin;

use app\BaseController;
use app\Request;

class Setting extends BaseController
{
    public static $setting = [
        'site_name' => ['Website name', 'text', 'Set the website title'],
        'program_version' => ['Version', 'readonly', ''],
        'footer' => ['Footer text', 'textarea', 'Change the website footer (HTML tags are supported)'],

        'admin_password' => ['Admin password', 'text', 'Change the password associated with this website'],
        'password' => ['Access password', 'text', 'Change the password required to enable link parsing (helps with public access)'],

        'db' => ['Database enabled?', 'readonly', 'If enabled, extra management features are used like account lists and statistics (requires reconfiguration of .env to change)'],
        'link_expired_time' => ['Link session expiration (in hours)', 'number', 'Change how long until a session associated with a parsed link expires'],
        'times' => ['Daily link parse limit (by IP)', 'number', 'Change the amount of times an IP address can parse a link per day'],
        'flow' => ['Daily data limit (by IP, in GB)', 'number', 'Change the amount of data an IP address can transfer per day'],

        'check_speed_limit' => ['Detect speed limits', 'radio', 'If this option and the database are enabled, files over 50 MB will be checked for speed limiting (retard note: why?). When all other accounts are limited, the fallback account in the config will be used and this feature will not function.'],
        'random_account' => ['Randomize account', 'radio', 'If enabled, random accounts in the account list will be used for link parsing. If disabled, accounts in the list will be used sequentially. If there is no available accounts, the fallback account in the config will be used.'],

        'cookie' => ['Standard account cookie', 'textarea', 'Add the account cookie used for link parsing and downloading.'],
        'svip_cookie' => ['SVIP account cookie', 'textarea', 'Add the account cookie for high-speed downloads. If database function is enabled, this account will become the fallback account if no other accounts are available.'],
    ];
    public function list(Request $request)
    {
        $data = [];
        foreach (self::$setting as $key => $value) {
            $data[] = [
                'key' => $key,
                'name' => $value[0],
                'value' => config('baiduwp.' . $key),
                'type' => $value[1],
                'description' => $value[2],
            ];
        }
        return json([
            'error' => 0,
            'msg' => 'success',
            'data' => $data,
        ]);
    }
    public function update(Request $request)
    {
        $data = $request->post();
        self::updateConfig($data);
        return json([
            'error' => 0,
            'msg' => 'Saved',
        ]);
    }
    public static function updateConfig($data, $force = false)
    {
        $default = [
            'site_name' => 'PanDownload',
            'program_version' => \app\controller\Index::$version,
            'password' => '',
            'admin_password' => env('ADMIN_PASSWORD'),
            'db' => env('DB', false),
            'link_expired_time' => 8,
            'times' => 20,
            'flow' => 10,
            'check_speed_limit' => true,
            'random_account' => false,
            'cookie' => '',
            'svip_cookie' => '',
            'footer' => '',
        ];

        $config = config('baiduwp');
        if (!$config) {
            $config = $default;
        }
        foreach ($data as $key => $value) {
            if (array_key_exists($key, self::$setting)) {
                if (self::$setting[$key][1] == 'number') {
                    $value = (int)$value;
                }
                if (self::$setting[$key][1] == 'radio') {
                    $value = $value === 'true' ? true : false;
                }
                if (self::$setting[$key][1] == 'text' || self::$setting[$key][1] == 'textarea') {
                    $value = trim($value);
                }
                if (self::$setting[$key][1] == 'readonly') {
                    if (!$force) continue;
                    if ($value === 'true') $value = true;
                    if ($value === 'false') $value = false;
                }
                $config[$key] = $value;
            }
        }
        $config = var_export($config, true);

        // 写入配置文件
        $config = <<<PHP
<?php
// +----------------------------------------------------------------------
// | Baiduwp-php 应用设置
// +----------------------------------------------------------------------
//
// 本文件由程序自动生成，请勿随意修改，以免失效！
return {$config};
PHP;
        file_put_contents('../config/baiduwp.php', $config);
    }
}

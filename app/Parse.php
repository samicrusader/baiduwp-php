<?php

namespace app;

use app\Req;
use app\Tool;
use think\facade\Db;

/**
 * PanDownload 网页复刻版，PHP 语言版
 *
 * @author Yuan_Tuo <yuantuo666@gmail.com>
 * @link https://github.com/yuantuo666/baiduwp-php
 */
class Parse
{
	public static function getSign(string $surl = "", string $share_id = "", string $uk = ""): array
	{
		// construct url
		$params = "";
		if (substr($surl, 0, 1) == "2") {
			$surl_2 = substr($surl, 1);
			$surl_2 = base64_decode($surl_2);
			[$uk, $share_id] = explode("&", $surl_2);
			$surl = "";
		}
		if ($surl) $params .= "&surl=$surl";
		if ($share_id) $params .= "&shareid=$share_id";
		if ($uk) $params .= "&uk=$uk";
		$url = "https://pan.baidu.com/share/tplconfig?$params&fields=sign,timestamp&channel=chunlei&web=1&app_id=250528&clienttype=0";
		$header = [
			"User-Agent: netdisk;pan.baidu.com",
			"Cookie: " . config('baiduwp.cookie'),
		];
		$result = Req::GET($url, $header);
		$result = json_decode($result, true, 512, JSON_BIGINT_AS_STRING);
		if (($result["errno"] ?? 1) == 0) {
			$sign = $result["data"]["sign"];
			$timestamp = $result["data"]["timestamp"];
			return [0, $sign, $timestamp];
		} else {
			return [-1, $result["show_msg"] ?? "", ""];
		}
	}

	public static function decodeSceKey($seckey)
	{
		$seckey = str_replace("-", "+", $seckey);
		$seckey = str_replace("~", "=", $seckey);
		return str_replace("_", "/", $seckey);
	}

	public static function decryptMd5($md5)
	{
		if (preg_match('/^.{9}[a-f0-9]/', $md5) && ctype_xdigit(substr($md5, 9, 1))) {
			return $md5;
		}
		$key = dechex(ord(substr($md5, 9, 1)) - ord('g'));
		$key2 = substr($md5, 0, 9) . $key . substr($md5, 10, strlen($md5));
		$key3 = "";
		for ($a = 0; $a < strlen($key2); $a++) {
			$key3 .= dechex(hexdec($key2[$a]) ^ (15 & $a));
		}
		return substr($key3, 8, 8) . substr($key3, 0, 8) . substr($key3, 24, 8) . substr($key3, 16, 8);
	}

	public static function getList($surl, $pwd, $dir, $sign = "", $timestamp = ""): array
	{
		$message = [];
		if (!$sign || !$timestamp) {
			list($status, $sign, $timestamp) = self::getSign($surl);
			if ($status !== 0) {
				$sign = '';
				$timestamp = '1';
				$message[] = "无传入，自动获取sign和timestamp失败, $sign";
			} else {
				$message[] = "无传入，自动获取sign和timestamp成功: $sign, $timestamp";
			}
		}

		$IsRoot = $dir == "";
		$file_list = [];
		$Page = 1;
		// 获取所有文件 fix #86
		while (true) {
			$Filejson = self::getListApi($surl, $dir, $IsRoot, $pwd, $Page);
			if (config('app.debug')) $message[] = json_encode($Filejson);
			if ($Filejson["errno"] ?? 999 !== 0) {
				return self::listError($Filejson, $message);
			}
			foreach ($Filejson['data']['list'] as $v) {
				$file_list[] = $v;
			}
			if (count($Filejson['data']["list"]) < 1000) break;
			$Page++;
		}
		$randSk = urlencode(self::decodeSceKey($Filejson["data"]["seckey"]));
		$shareid = $Filejson["data"]["shareid"];
		$uk = $Filejson["data"]["uk"];

		// breadcrumb
		$DirSrc = [];
		if (!$IsRoot) {
			$Dir_list = explode("/", $dir);

			for ($i = 1; $i <= count($Dir_list) - 2; $i++) {
				if ($i == 1 and strstr($Dir_list[$i], "sharelink")) continue;
				$fullsrc = strstr($dir, $Dir_list[$i], true) . $Dir_list[$i];
				$DirSrc[] = array("isactive" => 0, "fullsrc" => $fullsrc, "dirname" => $Dir_list[$i]);
			}
			$DirSrc[] = array("isactive" => 1, "fullsrc" => $dir, "dirname" => $Dir_list[$i]);
		}
		$Filenum = count($file_list);
		$FileData = [];
		$RootData = array(
			"src" => $DirSrc,
			"timestamp" => $timestamp,
			"sign" => $sign,
			"randsk" => $randSk,
			"shareid" => $shareid,
			"surl" => $surl,
			"pwd" => $pwd,
			"uk" => $uk,
		);

		foreach ($file_list as $file) {
			if ($file["isdir"] == 0) { // 根目录返回的居然是字符串 #255
				//文件
				$FileData[] = array(
					"isdir" => 0,
					"name" => $file["server_filename"],
					"fs_id" => number_format($file["fs_id"], 0, '', ''),
					"size" => $file["size"],
					"uploadtime" => $file["server_ctime"],
					"md5" => $file["md5"],
					"dlink" => $file["dlink"]
				);
			} else {
				//文件夹
				$FileData[] = array(
					"isdir" => 1,
					"name" => $file["server_filename"],
					"path" => $file["path"],
					"size" => $file["size"],
					"uploadtime" => $file["server_ctime"]
				);
			}
		}

		return array(
			"error" => 0,
			"isroot" => $IsRoot,
			"dirdata" => $RootData,
			"filenum" => $Filenum,
			"filedata" => $FileData,
			"message" => $message
		);
	}

	public static function download($fs_id, $timestamp, $sign, $randsk, $share_id, $uk)
	{
		if (!$fs_id || !$randsk || !$share_id || !$uk) {
			return [
				'error' => -1,
				'msg' => 'Parameter error',
			];
		}
		$message = [];

		$ip = Tool::getIP();
		$isipwhite = FALSE;
		if (config('baiduwp.db')) {
			$data = Db::connect()->table("ip")->where('ip', $ip)->find();
			if ($data) {
				// 存在 判断类型
				if ($data->type === -1) {
					// 黑名单
					return array("error" => -1, "msg" => "Your IP address is blacklisted, please contact the site operator to delist.", "ip" => $ip);
				} elseif ($data->type === 0) {
					// 白名单
					$message[] = "IP is whitelisted~ $ip";
					$isipwhite = TRUE;
				}
			}
		}

		// check if the timestamp is valid
		if (time() - $timestamp > 300) {
			// try to get the timestamp and sign
			list($_status, $sign, $timestamp) = self::getSign("", $share_id, $uk);
			if ($_status !== 0) {
				$message[] = "超时，自动获取sign和timestamp失败, $sign";
			} else {
				$message[] = "超时，自动获取sign和timestamp成功: $sign, $timestamp";
			}
		}

		$json4 = self::getDlink($fs_id, $timestamp, $sign, $randsk, $share_id, $uk);
		$errno = $json4["errno"] ?? 999;
		if ($errno !== 0) {
			if (config('app.debug')) {
				$message[] = "Failed to parse download link: " . json_encode($json4);
			} else {
				$message[] = "Failed to parse download link: " . ($json4["error_msg"] ?? "unknown error");
			}
			return self::downloadError($json4, $message);
		}

		$dlink = $json4["list"][0]["dlink"] ?? "";
		// 获取文件相关信息
		$md5 = $json4["list"][0]["md5"];
		$md5 = self::decryptMd5($md5); // 尝试解密md5
		$filename = $json4["list"][0]["server_filename"] ?? "";
		$size = $json4["list"][0]["size"] ?? "0";
		$path = $json4["list"][0]["path"] ?? "";
		$server_ctime = (int)$json4["list"][0]["server_ctime"] ?? 28800; // 服务器创建时间 +8:00

		$FileData = array(
			"filename" => $filename,
			"size" => $size,
			"path" => $path,
			"uploadtime" => $server_ctime,
			"md5" => $md5
		);

		if ($size <= 5242880) { // 5MB
			return array("error" => 0, "filedata" => $FileData, "directlink" => $dlink, "user_agent" => "LogStatistic", "message" => $message);
		}

		list($ID, $cookie) = ["-1", config('baiduwp.cookie')];

		if (config('baiduwp.db')) {
			$link_expired_time = config('baiduwp.link_expired_time') ?? 8;

			// 查询数据库中是否存在已经保存的数据
			$data = Db::connect()
				->table("records")
				->where('md5', $md5)
				->where('time', '>', date('Y-m-d H:i:s', time() - (int)($link_expired_time * 3600)))
				->find();

			if ($data) {
				// 存在
				$realLink = $data['link'];
				return array("error" => 0, "usingcache" => true, "filedata" => $FileData, "directlink" => $realLink, "user_agent" => "LogStatistic", "message" => $message);
			}

			// 判断今天内是否获取过文件
			if (!$isipwhite) { // 白名单跳过
				// 获取解析次数
				$count = Db::connect()->table('records')->where('ip', $ip)
					->where('time', '>', date('Y-m-d 00:00:00', time()))
					->count();

				if ($count > config('baiduwp.times')) {
					// 提示无权继续
					return array("error" => 1, "msg" => "This site's limit of link parses have been exceeded, try again tomorrow.", "ip" => $ip);
				}
				// 获取解析流量
				$flow = Db::connect()->table('records')->where('ip', $ip)
					->where('time', '>', date('Y-m-d 00:00:00', time()))
					->sum('size');
				if ($flow > config('baiduwp.flow') * 1024 * 1024 * 1024) {
					// 提示无权继续
					return array("error" => 1, "msg" => "This site's limit of link parses have been exceeded, try again tomorrow.", "ip" => $ip);
				}
			}

			$query = Db::connect()->table("account")->where('status', 0);
			if (config('baiduwp.random_account')) {
				$query = $query->order('last_used_at', 'asc');
			} else {
				$query = $query->order('created_at', 'asc');
			}
			$data = $query->find();
			if ($data) {
				list($ID, $cookie) = [$data['id'], $data['cookie']];
				// 更新最后使用时间
				Db::connect()->table('account')->where('id', $ID)->update(['last_used_at' => date('Y-m-d H:i:s', time())]);
			}
		}

		$SVIP_BDUSS = Tool::getSubstr($cookie, 'BDUSS=', ';');

		// 开始获取真实链接
		$headerArray = array('User-Agent: LogStatistic', 'Cookie: BDUSS=' . $SVIP_BDUSS . ';');

		$header = Req::HEAD($dlink, $headerArray); // 禁止重定向
		if (!strstr($header, "Location")) {
			// fail
			$message[] = "real_link acquisition failed: $header";
		}
		$header = str_replace("https://", "http://", $header);
		$real_link = Tool::getSubstr($header, "Location: ", "\r\n"); // delete http://

		// 1. 使用 dlink 下载文件   2. dlink 有效期为8小时   3. 必需要设置 User-Agent 字段   4. dlink 存在 HTTP 302 跳转
		if (!$real_link || strlen($real_link) < 20) {
			$body = Req::GET($dlink, $headerArray);
			$body_decode = json_decode($body, true);

			$message[] = "failed to acquire download real_link：" . json_encode($body_decode);

			if (config('baiduwp.db') && config('baiduwp.check_speed_limit') && $ID != "-1") {
				$result = Db::connect()->table('account')->where('id', $ID)->update(['status' => -1]);
				if ($result) {
					return array("error" => -1, "msg" => "SVIP account was switched, please retry!", "message" => $message);
				} else {
					return array("error" => -1, "msg" => "SVIP account switching failed, please try again later", "message" => $message);
				}
			}
			return self::realLinkError($body_decode, $message); // 获取真实链接失败
		}
		if (str_contains($real_link, "qdall01") || !str_contains($real_link, 'tsl=0')) {
			// 账号限速
			$message[] = "SVIP账号限速";
			if (config('baiduwp.db') && config('baiduwp.check_speed_limit') && $ID != "-1") {
				$result = Db::connect()->table('account')->where('id', $ID)->update(['status' => -1]);
				if ($result) {
					return array("error" => -1, "msg" => "SVIP account was switched, please retry!", "message" => $message);
				} else {
					return array("error" => -1, "msg" => "SVIP account switching failed, please try again later", "message" => $message);
				}
			}
		}

		// 记录下使用者ip，下次进入时提示
		if (config('baiduwp.db')) {
			$result = Db::connect()->table("records")->insert([
				'ip' => $ip,
				'ua' => request()->header('User-Agent'),
				'name' => $filename,
				'size' => $size,
				'md5' => $md5,
				'link' => $real_link,
				'time' => date('Y-m-d H:i:s', time()),
				'account' => $ID
			]);

			if (!$result) {
				// 保存错误
				return array("error" => -1, "msg" => "Database write error", "message" => $message);
			}
		}

		return array("error" => 0, "filedata" => $FileData, "directlink" => $real_link, "user_agent" => "LogStatistic", "message" => $message);
	}

	private static function getListApi(string $Shorturl, string $Dir, bool $IsRoot, string $Password, int $Page = 1)
	{
		$Url = 'https://pan.baidu.com/share/wxlist?channel=weixin&version=2.2.2&clienttype=25&web=1';
		$Root = ($IsRoot) ? "1" : "0";
		$Dir = urlencode($Dir);
		if (substr($Shorturl, 0, 1) == "2") {
			$Shorturl = substr($Shorturl, 1);
			$Shorturl = base64_decode($Shorturl);
			[$uk, $share_id] = explode("&", $Shorturl);
			$params = "&uk=$uk&shareid=$share_id";
		} else {
			$params = "&shorturl=$Shorturl";
		}
		$Data = "$params&dir=$Dir&root=$Root&pwd=$Password&page=$Page&num=1000&order=time";
		$BDUSS = Tool::getSubstr(config('baiduwp.cookie'), 'BDUSS=', ';');
		$header = ["User-Agent: netdisk", "Cookie: BDUSS=$BDUSS", "Referer: https://pan.baidu.com/disk/home"];
		return json_decode(Req::POST($Url, $Data, $header), true);
	}

	private static function getDlink(string $fs_id, string $timestamp, string $sign, string $randsk, string $share_id, string $uk, int $app_id = 250528)
	{ // 获取下载链接
		$url = 'https://pan.baidu.com/api/sharedownload?app_id=' . $app_id . '&channel=chunlei&clienttype=12&sign=' . $sign . '&timestamp=' . $timestamp . '&web=1'; // 获取下载链接

		if (strstr($randsk, "%")) $randsk = urldecode($randsk);
		$data = "encrypt=0" . "&extra=" . urlencode('{"sekey":"' . $randsk . '"}') . "&fid_list=[$fs_id]" . "&primaryid=$share_id" . "&uk=$uk" . "&product=share&type=nolimit";
		$header = array(
			"User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/110.0.0.0 Safari/537.36 Edg/110.0.1587.69",
			"Cookie: " . config('baiduwp.cookie'),
			"Referer: https://pan.baidu.com/disk/home"
		);
		return json_decode(Req::POST($url, $data, $header), true);
		// 没有 sekey 参数就 118, -20出现验证码
	}

	private static function listError($Filejson, $message): array
	{
		if (empty($Filejson)) {
			return [
				"error" => -1,
				"title" => "File listing is empty",
				"msg" => "Please check if your cookies are set correctly and if the server has a working Internet connection",
				"message" => $message
			];
		}
		// 解析异常
		$ErrorCode = $Filejson["errtype"] ?? ($Filejson["errno"] ?? 999);
		$ErrorMessage = [
			"mis_105" => "Requested link does not exist",
			"mispw_9" => "提取码错误",
			"mispwd-9" => "提取码错误",
			"mis_2" => "Directory does not exist",
			"mis_4" => "Directory does not exist",
			5 => "The requested link does not exist or the share code is invalid",
			3 => "The requested link was blocked by Baidu because it violates the terms of use",
			0 => "The shared files has been deleted by the owner",
			10 => "This share has expired",
			8001 => "Upstream download restriction is in place, try again later...",
			9013 => "Upstream download restriction is in place, try again later...",
			9019 => "Upstream download account is broken, try again later...",
			999 => "Unknown error: -> " . json_encode($Filejson)
		];
		return [
			"error" => -1,
			"title" => "Link parser error ($ErrorCode)",
			"msg" => $ErrorMessage[$ErrorCode] ?? "Unknown error, if this keeps occuring contact the site operator.",
			"message" => $message
		];
	}

	private static function downloadError($json4, $message): array
	{
		$errno = $json4["errno"] ?? 999;
		$error = [
			999 => ["Upstream error (999)", "An error occured while accessing Baidu, please try again later. If this keeps occuring contact the site operator."], // generic
			-20 => ["Upstream error (-20)", "An error occured while accessing Baidu, please try again later. If this keeps occuring contact the site operator."], // captcha
			-9 => ["File does not exist (-9)", "The requested file does not exist. Try reparsing the link."],
			-6 => ["Account error (-6)", "Upstream account is not logged in, please try again later. If this keeps occuring contact the site operator."],
			-1 => ["Download blocked (-1)", "This download was blocked by Baidu because the requested file violates the terms of use"],
			2 => ["Download failure (2)", "An error occured while resolving the download, please try again later."],
			112 => ["Timed out (112)", "A time out of 5 minutes is in place per download. Please reparse the link and try again."],
			113 => ["Upstream error (113)", "An error occured while resolving the download, please try again later. If this keeps occuring contact the site operator."], // GET failure, check param(s)
			116 => ["Download failure (116)", "The requested link does not exist"],
			118 => ["Account error (118)", "An error occured while resolving the download, please try again later. If this keeps occuring contact the site operator."], // no download permission, check sekey param(s)
			110 => ["Upstream error (110)", "An error occured while resolving the download, please try again later. If this keeps occuring contact the site operator."], // IP is probably blocked
			121 => ["Too many files (121)", "Too many files have been selected, please try again with less."],
			8001 => ["Account error (8001)", "Upstream download restriction is in place, try again later..."],
			9013 => ["Account error (9013)", "Upstream download restriction is in place, try again later..."],
			9019 => ["Account error (9019)", "Upstream download account is broken, try again later..."],
		];

		if (isset($error[$errno])) return [
			"error" => -1,
			"title" => $error[$errno][0],
			"msg" => $error[$errno][1],
			"message" => $message
		];
		else return [
			"error" => -1,
			"title" => "Download link resolver error ($errno)",
			"msg" => "Unknown error：" . json_encode($json4),
			"message" => $message
		];
	}

	private static function realLinkError($body_decode, $message): array
	{
		$ErrorCode = $body_decode["errno"] ?? ($body_decode["error_code"] ?? 999);
		$ErrorMessage = [
			8001 => "This site's SVIP account is restricted, please try again later.", // check cookie
			9013 => "This site's SVIP account is restricted, please try again later.",
			9019 => "This site's SVIP account is restricted, please try again later.", // check cookie
			31360 => "Please try again later.", // link resolve timed out, check cookie
			31362 => "Please try again later.", //Signature check failed, check if user agent is correct
			999 => "Unknown error -> " . json_encode($body_decode)
		];

		return [
			"error" => -1,
			"title" => "Upstream error ($ErrorCode)",
			"msg" => $ErrorMessage[$ErrorCode] ?? "Unknown error: " . json_encode($body_decode),
			"message" => $message
		];
	}
}

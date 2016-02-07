<?php

define('PATH_VIEWS', 'views');
define('DEFAULT_LAYOUT', 'main');

$HT_DEFAULT_FMTS = [
	'year_0' => "An year ago",
	'years_0' => "%d years ago",
	'year_1' => "An year from now",
	'years_1' => "%d years from now",
	'month_0' => "A month ago",
	'months_0' => "%d months ago",
	'month_1' => "A month from now",
	'months_1' => "%d months from now",
	'day_0' => "A day ago",
	'days_0' => "%d days ago",
	'day_1' => "A day from now",
	'days_1' => "%d days from now",
	'hour_0' => "An hour ago",
	'hours_0' => "%d hours ago",
	'hour_1' => "An hour from now",
	'hours_1' => "%d hours from now",
	'minute_0' => "A minute ago",
	'minutes_0' => "%d minutes ago",
	'minute_1' => "A minute from now",
	'minutes_1' => "%d minutes from now",
	'second_0' => "A second ago",
	'seconds_0' => "%d seconds ago",
	'second_1' => "A second from now",
	'seconds_1' => "%d seconds from now",
];

$HT_DIVS = [
	'year' => 60*60*24*365,
	'month' => 60*60*24*31,
	'day' => 60*60*24,
	'hour' => 60*60,
	'minute' => 60,
	'second' => 0
];

$dblink = null;

function route($method, $route, $func = null) {
        static $routes = [];
        if(!$func) {
                $r = null;
                $n = '';
                $argv = [];
                foreach(explode('/', $route) as $arg) {
                        $n .= ($n == '/' ? $arg : "/$arg");
                        if($r)
                                $argv[] = $arg;
			if(isset($routes[$method][$n])) {
                                $r = $routes[$method][$n];
				$argv = [];
				if($route == $n)
					break;
			}
			if(isset($routes[$method]["$n/"])) {
                                $r = $routes[$method]["$n/"];
				$argv = [];
			}
                }
		if(!$r || (count($argv) < $r['mandatory']) || (count($argv) > $r['argc'])) {
			header($_SERVER['SERVER_PROTOCOL'].' 404 Not Found'); 
			header("Status: 404 Not Found"); /* CGI */
			exit(1);
		}
                return call_user_func_array($r['func'], $argv);
        }
        $name = [];
        $argc = 0;
        $mandatory = 0;
        foreach(explode('/', $route) as $arg) {
                switch(@$arg[0]) {
                        case '!': ++$argc; ++$mandatory; break;
                        case '?': ++$argc; break;
                        default: $name[] = $arg; break;
                }
        }
        $name = implode('/', $name);
	if($argc)
		$name .= '/';
        $routes[$method][$name] = ['func' => $func, 'argc' => $argc, 'mandatory' => $mandatory];
        return 0;
}

function dbopen($host, $user, $pass, $dbname) {
	if(!($r = @mysqli_connect($host, $user, $pass, $dbname)))
		die('database error');
	global $dblink;
	$dblink = $r;
	return $r;
}

function dbquery($sql, $limit = 1, $multi = false) {
	if(!is_string($sql) || !($sql = trim($sql)))
		return false;

	$ck = md5($sql);
	if(($ret = cache($ck)))
		return $ret;

	global $dblink;
	$cn = "$sql-$limit-$multi";

	$cmd = strtolower(substr($sql, 0, strpos($sql, ' ')));

	if($cmd == 'select') {
		if($limit == -1)
			$limit = '18446744073709551615';
		$sql .= " limit $limit";
	}

	if($multi)
		$res = mysqli_multi_query($dblink, $sql);
	else
		$res = mysqli_query($dblink, $sql);
	if(!$res)
		return false;

	if($multi) {
		$ret = [];

		for($res = mysqli_use_result($dblink); $res; $res = mysqli_store_result($dblink)) {
			$r = [];
			while(($t = mysqli_fetch_assoc($res)))
				$r[] = $t;
			$ret[] = $r;
			mysqli_free_result($res);
			mysqli_next_result($dblink);
		}
	}
	else {
		switch($cmd) {
			case 'select':
			case 'call':
				if($cmd == 'select' && $limit == '1') {
					$ret = mysqli_fetch_assoc($res);
					break;
				}

				$ret = [];
				while(($t = mysqli_fetch_assoc($res)))
					$ret[] = $t;
				break;

			case 'insert':
				$ret = mysqli_insert_id($dblink);
				if(!$ret)
					$ret = true;
				break;

			case 'delete':
				$ret = mysqli_affected_rows($dblink);
				break;

			default:
				$ret = $res;
				break;
		}
	}

	return $ret;
}

function dbping($l = null) {
        if(!$l) {
                global $dblink;
                $l = $dblink;
        }
	return mysqli_ping($l);
}

function dberror($l = null) {
        if(!$l) {
                global $dblink;
                $l = $dblink;
        }
        return mysqli_error($l);
}

function dbin($s) {
	return addslashes(htmlentities($s, ENT_QUOTES, 'UTF-8'));
}

function dbout($s) {
	return stripslashes(html_entity_decode($s, ENT_QUOTES, 'UTF-8'));
}

function dbins($tbl, $info) {
	$vals = [];
	foreach($info as $k => $v) {
		$v = dbin($v);
		$vals["`$k`"] = "'$v'";
	}
	$sql = 'insert into `'.$tbl.'`
		('.implode(',', array_keys($vals)).')
		values ('.implode(',', array_values($vals)).')';
	return dbquery($sql);
}

function dbupd($tbl, $info, $ids = NULL) {
	$vals = [];
	foreach($info as $k => $v) {
		$v = dbin($v);
		if($v == NULL)
			$v = 'NULL';
		if($v != 'NULL' && $v != 'CURRENT_TIMESTAMP')
			$v = "'$v'";
		$vals[] = "`$k` = $v";
	}
	$vals = implode(',', $vals);
	$sql = "update `$tbl` set $vals";
	if($ids) {
		if(is_array($ids))
			$sql .= " where id IN(".implode(',', $ids).")";
		else
			$sql .= " where id = $ids";
	}
	return dbquery($sql);
}

function dbdel($tbl, $id) {
	$sql = "delete from `$tbl` where id = $id";
	return dbquery($sql);
}

function sizefitbox($src, $dst) {
	list($ow, $oh) = explode('x', $src);
	list($tow, $toh) = explode('x', $dst);

	$rw = $tow / $ow;
	$rh = $toh / $oh;
	$ratio = min($rw, $rh);

	$w = $ow * $ratio;
	$h = $oh * $ratio;

	$w = (int)$w;
	$h = (int)$h;

	return "${w}x${h}";
}

function thumb($src, $size = '64x64', $force = 0) {
	if(strpos($size, 'x') === false)
		$size = (int)$size.'x'.(int)$size;
	$pi = pathinfo($src);
	/* Since imgresize() use the extension to identify the file type, it's
	 * faster to returns here. */
	if(!@$pi['extension'])
		return null;
	/* name.ext > name.size.ext */
	$thumb = "${pi['dirname']}/${pi['filename']}.${size}.${pi['extension']}";
	if(!$force && file_exists($thumb))
		return $thumb;
	$t = imgresize($src, $thumb, $size);
	if($t)
		return $t;
	return $thumb;
}

function imgresize($src, $saveas, $whxy = '64x64-0,0', $opts = null) {
	$in = null;
	$out = null;
	$transparency = false;
	$ext = strtolower((string)@pathinfo($src)['extension']);
	switch($ext) {
		case 'jpg':
		case 'jpeg':
			$in = 'imagecreatefromjpeg';
			$out = 'imagejpeg';
			break;
		case 'gif':
			$in = 'imagecreatefromgif';
			$out = 'imagegif';
			/* imagegif() doesn't take a third param */
			if($opts !== null)
				$opts = null;
			break;
		case 'bmp':
			$in = 'imagecreatefromwbmp';
			$out = 'imagewbmp';
			break;
		case 'png':
			$in = 'imagecreatefrompng';
			$out = 'imagepng';
			$transparency = true;
			break;
		default: /* unsupported image */ return -1;
	}
	if(!($oi = $in($src)))
		return 2;

	$t = explode('-', $whxy);
	$wh = $t[0];
	$wh = explode('x', $wh);
	$xy = isset($t[1]) ? $t[1] : '0,0';
	$xy = explode(',', $xy);

	$w = (int)@$wh[0];
	$h = isset($wh[1]) ? $wh[1] : $w;
	$x = (int)@$xy[0];
	$y = (int)@$xy[1];

	list($iw, $ih) = getimagesize($src);
	$ratio = [$iw / $ih, $w / $h];
	if($x != 0 || $y != 0) {
		$crop = imagecreatetruecolor($w, $h);
		$cropW = $w;
		$cropH = $h;

		imagecopy($crop, $oi, 0, 0, (int)$x, (int)$y, $w, $h);
		if($transparency) {
			imagealphablending($crop, false);
			imagesavealpha($crop, true);  
		}
	}
	else if($ratio[0] != $ratio[1]) {
		$scale = min((float)($iw / $w), (float)($ih / $h));
		$cropX = (float)($iw - ($scale * $w));
		$cropY = (float)($ih - ($scale * $h));
		$cropW = (float)($iw - $cropX);
		$cropH = (float)($ih - $cropY);
		$crop = imagecreatetruecolor($cropW, $cropH);
		if($transparency) {
			imagealphablending($crop, false);
			imagesavealpha($crop, true);  
		}
		imagecopy($crop, $oi, 0, 0, (int)($cropX / 2), (int)($cropY / 2), $cropW, $cropH);
	}
	$ni = imagecreatetruecolor($w, $h);
	if($transparency) {
		imagealphablending($ni, false);
		imagesavealpha($ni, true);  
	}
	if(isset($crop)) {
		imagecopyresampled($ni, $crop, 0, 0, 0, 0, $w, $h, $cropW, $cropH);
		imagedestroy($crop);
	}
	else {
		imagecopyresampled($ni, $oi, 0, 0, 0, 0, $w, $h, $iw, $ih);
	}
	imagedestroy($oi);
	if($opts !== null)
		$r = $out($ni, $saveas, $opts);
	else
		$r = $out($ni, $saveas);
	imagedestroy($ni);
	if($r === false)
		return 1;
	return 0;
}

function jsonerr($v) {
	json('ko', $v);
}

function jsonok($v) {
	json('ok', $v);
}

function json($state, $res) {
	die(json_encode(['state' => $state, 'res' => $res]));
}

function sendmail($from, $to, $subj, $message, $files = null) {
	ini_set('sendmail_from', $from);
	$headers =      "From: $from\n" .
			"Return-Path: <$from>\r\n" .
			"MIME-Version: 1.0\n";

	if(!(is_array($files) || count($files))) {
		$headers .=     "Content-Type: text/html; charset=\"UTF-8\"\n" .
				"Content-Transfer-Encoding: 7bit\n\n";
	}
	else {
		$semi_rand = md5(time());
		$mime_boundary = "==Multipart_Boundary_x{$semi_rand}x";
		$headers .=     "Content-Type: multipart/mixed; boundary=\"{$mime_boundary}\"\n";
		$message =      "--{$mime_boundary}\n" .
				"Content-Type: text/plain; charset=\"UTF-8\"\n" .
				"Content-Transfer-Encoding: 7bit\n\n" .
				$message . "\n\n";
		foreach($files as $fn) {
			$f = basename($fn);
			if(!is_file($fn))
				continue;
			$data = chunk_split(base64_encode(file_get_contents($fn)));

			$message .=     "--{$mime_boundary}\n" .
					"Content-Type: application/octet-stream; name=\"$f\"\n" .
					"Content-Description: $f\n" .
					"Content-Disposition: attachment; filename=\"$f\"; size=".filesize($fn).";\n" .
					"Content-Transfer-Encoding: base64\n\n" . $data . "\n\n";
		}
		$message .= "--{$mime_boundary}--";
	}

	return @mail($to, $subj, $message, $headers);
}

function _store(&$to, $k, $v) {
	$r = isset($to[$k]) ? $to[$k] : null;
	if($v !== null)
		$to[$k] = $v;
	return $r;
}

function cache($k, $v = null) {
	static $__cache = [];
	return _store($__cache, $k, $v);
}

function sess($k, $v = null) {
	$r = isset($_SESSION[$k]) ? $_SESSION[$k] : null;
	return _store($_SESSION, $k, $v);
}

function pre($d) {
	echo '<pre>';
	print_r($d);
	echo '</pre>';
}

function humanstime($timestamp, $fmts = null) {
	global $HT_DIVS;

	$divs = $HT_DIVS;
	$diff = time() - $timestamp;
	$isfuture = ($diff < 0);
	if($isfuture)
		$diff = -$diff;

	foreach($divs as $name => $delta) {
		if($diff >= $delta)
			break;
	}

	$unit = $delta ? $diff / $delta : $diff;
	$ht = [
		'name' => $name.($unit > 1 ? 's' : ''),
		'unit' => $unit,
		'isfuture' => $isfuture
	];

	if(!$fmts) {
		global $HT_DEFAULT_FMTS;
		$fmts = $HT_DEFAULT_FMTS;
	}

	$k = $ht['name'].'_'.(int)$ht['isfuture'];
	return sprintf($fmts[$k], $ht['unit']);
}

function curl_post($uri, $curlopts = []) {
	$c = curl_init();

	curl_setopt($c, CURLOPT_RETURNTRANSFER, 1);
	curl_setopt($c, CURLOPT_URL, $uri);
	curl_setopt($c, CURLOPT_POST, true);

	if($curlopts)
		foreach($curlopts as $k => $v)
			curl_setopt($c, $k, $v);

	$ret = curl_exec($c);
	curl_close($c);

	return $ret;
}

function prepare_form() {
	$key = key($_FILES);
	if(!$key)
		return;
	$files = @$_FILES[$key];
	if(@$_POST[$key]) {
		$files = array_merge($files, $_POST[$key]);
		unset($_POST[$key]);
	}
	$ret = [];
	foreach($files as $k => $unused) {
		foreach($files[$k] as $i => $v) {
			if(!isset($ret[$i]))
				$ret[$i] = [];
			$ret[$i][$k] = $v;
		}
	}
	$_FILES = $ret;
}

function buildhier($items, $pk = 'parent_id', $ck = 'id', $subk = 'children') {
	$map = [];
	foreach($items as $k => &$item) {
		$c = $item[$ck];
		$map[$c] = &$item;
	}
	unset($item);
	foreach($items as $item) {
		$p = $item[$pk];
		if(!$p)
			continue;
		$c = $item[$ck];
		if(!isset($map[$p][$subk]))
			$map[$p][$subk] = [];
		$map[$p][$subk][$c] = $map[$c];
		$map[$c]['__rm'] = 1;
		$map[$c] = &$map[$p][$subk][$c];
	}
	foreach($items as $k => $item)
		if(@$item['__rm'])
			unset($items[$k]);
	return $items;
}

function viewinc($name, $data = []) {
	if(!defined('PATH_VIEWS'))
		die('PATH_VIEWS not defined');
        foreach($data as $k => $v)
                ${$k} = $v;
        $view = PATH_VIEWS.'/'.implode('/', explode('.', $name)).'.php';
        ob_start();
        require($view);
        $d = ob_get_contents();
        ob_end_clean();
        return $d;
}

function viewlinc($name, $data = [], $layout = null, $layoutdata = []) {
	if(!$layout) {
		if(!defined('DEFAULT_LAYOUT'))
			die('DEFAULT_LAYOUT not defined');
		$layout = DEFAULT_LAYOUT;
	}
	$layoutdata['content'] = viewinc($name, $data);
	return viewinc($layout, $layoutdata);
}

function view($name, $data = [], $layout = null, $layoutdata = []) {
	return viewlinc($name, $data, $layout, $layoutdata);
}

function bored_run() {
	echo route($_SERVER['REQUEST_METHOD'], (string)@explode('?', $_SERVER['REQUEST_URI'])[0]);
}

function bored_init() {
	prepare_form();
	session_start();
	if(defined('DBHOST') && defined('DBUSER') && defined('DBPASS') && defined('DBNAME'))
		dbopen(DBHOST, DBUSER, DBPASS, DBNAME);
	register_shutdown_function(function() {
		global $dblink;
		if($dblink)
			mysqli_close($dblink);
		session_write_close();
	});
}

?>

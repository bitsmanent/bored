<?php

$HT_DEFAULT_FMTS = [
	'year_0' => "An year ago",
	'years_0' => "%d years ago",
	'year_1' => "An year from now",
	'years_1' => "%d years from now",
	'month_0' => "A month ago",
	'months_0' => "%d months ago",
	'month_1' => "A month from now",
	'months_1' => "%d months from now",
	'week_0' => "A week ago",
	'weeks_0' => "%d weeks ago",
	'week_1' => "A week from now",
	'weeks_1' => "%d weeks from now",
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
	'now_0' => "Just now",
	'nows_0' => "Few seconds ago",
	'now_1' => "Just now",
	'nows_1' => "In a bit",
];

$HT_DIVS = [
	'year' => 60*60*24*365,
	'month' => 60*60*24*30,
	'week' => 60*60*24*7,
	'day' => 60*60*24,
	'hour' => 60*60,
	'minute' => 60,
	'second' => 30,
	'now' => 0,
];

$dblink = NULL;

function route($method, $route, $func = NULL) {
        static $routes = [];
        if(!$func) {
                $r = NULL;
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
		if(!$r || (count($argv) < $r['mandatory']) || (count($argv) > $r['argc']))
			exit(http_response_code(404));
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
	global $dblink;

	if(!($r = @mysqli_connect($host, $user, $pass, $dbname)))
		die('database error');
	$dblink = $r;
	return $r;
}

function dbquery($sql, $limit = 1, $multi = false) {
	global $dblink;

	if(!is_string($sql) || !($sql = trim($sql)))
		return false;

	$ck = md5($sql);
	if(($ret = cache($ck)))
		return $ret;

	$cn = "$sql-$limit-$multi";
	$cmd = strtolower(substr($sql, 0, strpos($sql, ' ')));
	if($cmd == 'select') {
		if($limit == -1)
			$limit = '18446744073709551615';
		$sql .= " limit $limit";
	}

	$res = $multi ? mysqli_multi_query($dblink, $sql) : mysqli_query($dblink, $sql);
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
		return $ret;
	}
	switch($cmd) {
	case 'select':
	case 'call':
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
	return $limit == 1 ? $ret[0] : $ret;
}

function dbping($l = NULL) {
	global $dblink;

        if(!$l)
                $l = $dblink;
	return mysqli_ping($l);
}

function dberr($l = NULL) {
	global $dblink;

        if(!$l)
                $l = $dblink;
        return mysqli_error($l);
}

function dbin($s) {
	if($s === NULL)
		return "NULL";
	if($s == "CURRENT_TIMESTAMP")
		return $s;
	return "'".addslashes(htmlentities($s, ENT_QUOTES, 'UTF-8'))."'";
}

function dbout($s) {
	return stripslashes(html_entity_decode($s, ENT_QUOTES, 'UTF-8'));
}

function dbids() {
	/* Assuming transactional DB (InnoDB) */
	$sql = "select LAST_INSERT_ID() as s,LAST_INSERT_ID() + ROW_COUNT() - 1 as e";
	$t = dbquery($sql)[0];
	return range($t["s"], $t["e"]);
}

function dbins($tbl, $items) {
	$values = [];
	$fields = array_keys($items[0]);
	foreach($items as $item) {
		$vals = [];
		foreach($item as $k => $v)
			$vals[] = dbin($v);
		$values[] = "(".implode(',', $vals).")";
	}
	$sql = "insert into `$tbl` (".implode(',', $fields).") values ".implode(',', $values);
	return dbquery($sql);
}

function dbupd($tbl, $items, $pk = "id") {
	$when = [];
	$keys = array_keys($items[0]);
	foreach($items as $item) {
		$pv = $item[$pk];
		foreach($keys as $k) {
			if($k == $pk)
				continue;
			$v = dbin($item[$k]);
			if(!isset($when[$k]))
				$when[$k] = [];
			$when[$k][] = "when $pv then $v";
		}
	}
	$sets = [];
	foreach($when as $k => $w)
		$sets[] = "$k = case `$pk` ".implode(' ', $w)." else `$k` end";
	$sql = "update `$tbl` set ".implode(',', $sets);
	return dbquery($sql);
}

function dbdel($tbl, $ids, $pk = "id") {
	$sql = "delete from `$tbl` where `$pk` IN(".implode(',', $ids).")";
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

function imgresize($src, $saveas, $whxy = '64x64-0,0', $opts = NULL) {
	$in = NULL;
	$out = NULL;
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
		if($opts !== NULL)
			$opts = NULL;
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
	if($opts !== NULL)
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

function jsonok($v = "") {
	json('ok', $v);
}

function json($state, $res) {
	die(json_encode(['state' => $state, 'res' => $res]));
}

function sendmail($from, $to, $subj, $message, $files = NULL) {
	ini_set('sendmail_from', $from);
	$headers = "From: $from\n" .
		   "Return-Path: <$from>\r\n" .
		   "MIME-Version: 1.0\n";
	if(!(is_array($files) || count($files))) {
		$headers .= "Content-Type: text/html; charset=\"UTF-8\"\n" .
			    "Content-Transfer-Encoding: 7bit\n\n";
	}
	else {
		$semi_rand = md5(time());
		$mime_boundary = "==Multipart_Boundary_x{$semi_rand}x";
		$headers .= "Content-Type: multipart/mixed; boundary=\"{$mime_boundary}\"\n";
		$message = "--{$mime_boundary}\n" .
			   "Content-Type: text/plain; charset=\"UTF-8\"\n" .
			   "Content-Transfer-Encoding: 7bit\n\n" .
			   $message . "\n\n";
		foreach($files as $fn) {
			$f = basename($fn);
			if(!is_file($fn))
				continue;
			$data = chunk_split(base64_encode(file_get_contents($fn)));
			$message .= "--{$mime_boundary}\n" .
				    "Content-Type: application/octet-stream; name=\"$f\"\n" .
				    "Content-Description: $f\n" .
				    "Content-Disposition: attachment; filename=\"$f\"; size=".filesize($fn).";\n" .
				    "Content-Transfer-Encoding: base64\n\n" . $data . "\n\n";
		}
		$message .= "--{$mime_boundary}--";
	}
	return @mail($to, $subj, $message, $headers);
}

function store(&$to, $k, $v) {
	$r = isset($to[$k]) ? $to[$k] : NULL;
	if($v !== NULL)
		$to[$k] = $v;
	return $r;
}

function cache($k, $v = NULL) {
	static $__cache = [];
	return store($__cache, $k, $v);
}

function sess($k, $v = NULL) {
	$r = isset($_SESSION[$k]) ? $_SESSION[$k] : NULL;
	return store($_SESSION, $k, $v);
}

function pre($d) {
	echo '<pre>'.print_r($d,1).'</pre>';
}

function humanstime($timestamp, $fmts = NULL) {
	global $HT_DEFAULT_FMTS, $HT_DIVS;

	$divs = $HT_DIVS;
	$diff = time() - $timestamp;
	$isfuture = ($diff < 0);
	if($isfuture)
		$diff = -$diff;
	foreach($divs as $name => $delta)
		if($diff >= $delta)
			break;
	$unit = (int)($delta ? $diff / $delta : $diff);
	$ht = [
		'name' => $name.($unit > 1 ? 's' : ''),
		'unit' => $unit,
		'isfuture' => $isfuture
	];
	if(!$fmts)
		$fmts = $HT_DEFAULT_FMTS;
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
	if(is_array($files['name'])) {
		foreach($files as $k => $unused) {
			foreach($files[$k] as $i => $v) {
				if(!isset($ret[$i]))
					$ret[$i] = [];
				$ret[$i][$k] = $v;
			}
		}
	}
	else
		$ret = [$files];
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

/* Note: all variables here are accessible by the view (along with globals) */
function viewinc($incname, $viewdata = []) {
	if(!defined("VIEWDIR"))
		die("VIEWDIR not defined");
        $viewfile = VIEWDIR.'/'.implode('/', explode('.', $incname)).'.php';
	if(!file_exists($viewfile))
		return NULL;
        ob_start();
        require($viewfile);
        $d = ob_get_contents();
        ob_end_clean();
        return $d;
}

function lviewinc($name, $data = [], $layout = NULL, $layoutdata = []) {
	if(!$layout) {
		if(!defined('DEFAULT_LAYOUT'))
			die('DEFAULT_LAYOUT not defined');
		$layout = DEFAULT_LAYOUT;
	}
	$content = viewinc($name, $data);
	if($content === NULL)
		return NULL;
	return viewinc($layout, [
		"name" => $name,
		"content" => $content,
		"paths" => array_filter(explode('/', $_SERVER["REQUEST_URI"]), function($dir) {
			return trim($dir);
		})
	]);
}

function view($name, $data = [], $layout = NULL, $layoutdata = NULL) {
	if(!$layoutdata)
		$layoutdata = $data;
	return lviewinc($name, $data, $layout, $layoutdata);
}

function bored_init() {
	setlocale(LC_CTYPE, "");
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

function bored_run($noinit = 0) {
	if(!$noinit)
		bored_init();
	echo route($_SERVER['REQUEST_METHOD'], (string)@explode('?', $_SERVER['REQUEST_URI'])[0]);
}

?>

<?php

function base_convert_bc($value = 0, $base_from = 10, $base_to = 36) {
	$chars = "0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ";
	
	$value = trim((string)$value);
	$base_from = (int)$base_from;
	$base_to = (int)$base_to;
	
	if(bccomp($value, 0) === -1) throw new Exception("Invalid \$value argument");
	if($base_from < 2 || $base_from > strlen($chars)) throw new Exception("Invalid \$base_from argument");
	if($base_to < 2 || $base_to > strlen($chars)) throw new Exception("Invalid \$base_to argument");
	
	if($base_from !== 10) {
		$tmp = "0";
		
		for($i = 0; $i < strlen($value); $i++) {
			$strpos = strpos($chars, $value[$i]);
			if($strpos === -1) return (string)0;
			$tmp = bcadd(bcmul($tmp, $base_from), strpos($chars, $value[$i])); 
		}
		
		$value = $tmp;
	}
	
	if($base_to !== 10) { 
		$tmp = "";
		
		while(bccomp($value, "0") > 0) {
			$tmp = ($chars[bcmod($value, $base_to)] . $tmp);
			$value = bcdiv($value, $base_to);
		}
		
		$value = $tmp;
	} 
	
	return (string)$value;
}

function get_app_useragent() {
	return (APP_NAME . "/" . APP_VERSION);
}

function get_requested_format() {
	global $httpvars;
	
	$p_format = $httpvars["get_n_post"]["format"];
	$p_json = $httpvars["get_n_post"]["json"];
	
	if(strlen($p_format)) {
		if($p_format === "json") return "json";
		if($p_format === "html") return "html";
	}
	
	if(isset($p_json) && $p_json) {
		return "json";
	}
	
	return "html";
}

function is_post_op($post) {
	return ($post["number"] === $post["thread"]);
}

function sanitize_html_id($attr) {
	return str_replace(" ", "+", $attr);
}

function build_dataset($data) {
	$str = "";
	
	foreach($data as $key => $value) {
		if(strlen($str)) {
			$str .= " ";
		}
		
		$str .= ("data-{$key}=\"" . htmlenc($value) . "\"");
	}
	
	return $str;
}

function standardize_newlines($text) {
	return preg_replace("/(\r\n|\r|\n)/", NEWLINE, $text);
}

function check_thread_archived(&$thread) {
	global $db;
	
	if(!$thread["state"]["archived"] && is_thread_archived($thread)) {
		$query = $db->prepare("UPDATE threads SET archived = 1 WHERE (id = :id AND archived = 0)");
		$query->bindValue(":id", $thread["id"]);
		$query->execute();
		
		$thread["state"]["archived"] = true;
	}
}

function is_thread_archived($thread) {
	global $db;
	
	static $threads_on_board = [];
	
	$board = get_board_by_uri($thread["board"]);
	
	$max_thread_alive_time = (int)$board["config"]["pruning"]["max_thread_alive_time"];
	$max_thread_alive_time = ($max_thread_alive_time > 0 ? $max_thread_alive_time : null);
	$max_threads_on_board = (int)$board["config"]["pruning"]["max_alive_threads"];
	$max_threads_on_board = ($max_threads_on_board > 0 ? $max_threads_on_board : null);
	
	
	if(!$board) {
		return false;
	}
	
	if($max_thread_alive_time !== null) {
		if((get_time_msec() - floatval($thread["post"]["time_posted"])) > ($max_thread_alive_time * 1000)) {
			return true;
		}
	}
	
	if($max_threads_on_board !== null) {
		if($threads_on_board[$board["id"]] === null) {
			$query = $db->prepare("SELECT id FROM threads WHERE (board = :board AND archived = 0) ORDER BY time_bumped DESC LIMIT " . (int)$max_threads_on_board);
			$query->bindValue(":board", $board["uri"]);
			$query->execute();
			
			$threads_on_board[$board["id"]] = $query->fetchAll(PDO::FETCH_COLUMN);
		}
		
		if(!in_array($thread["id"], $threads_on_board[$board["id"]])) {
			return true;
		}
	}
	
	return false;
}

function update_time_modified_post($post, $time) {
	global $db;
	
	$query = $db->prepare("UPDATE posts SET time_modified = :time_modified1 WHERE (id = :id AND time_modified <= :time_modified2)");
	$query->bindValue(":time_modified1", $time);
	$query->bindValue(":time_modified2", $time);
	$query->bindValue(":id", $post["id"]);
	$query->execute();
}

function update_time_modified_thread($thread, $time) {
	global $db;
	
	$query = $db->prepare("UPDATE threads SET time_modified = :time_modified1 WHERE (id = :id AND time_modified <= :time_modified2)");
	$query->bindValue(":time_modified1", $time);
	$query->bindValue(":time_modified2", $time);
	$query->bindValue(":id", $thread["id"]);
	$query->execute();
}

function gen_poster_id($key, $salt, $board) {
	$algorithm = CONSTANTS::POSTER_ID_HASH_ALGORITHM;
	$iterations = CONSTANTS::POSTER_ID_HASH_ITERATIONS;
	
	$length = (int)$board["config"]["poster_ids_length"];
	$length = b4k::math_clamp($length, 3, 64);
	
	$hash = $key;
	
	for($i = 0; $i < $iterations; $i++) {
		$hash = hash($algorithm, ($hash . $salt));
	}
	
	return substr($hash, 0, $length);
}

function gen_poster_modid($post, $board) {
	return gen_poster_id($post["ip"], salt("postermodid"), $board);
}

function gen_poster_publicid($post, $board) {
	$per_board = $board["config"]["poster_public_ids"]["per_board"];
	$per_thread = $board["config"]["poster_public_ids"]["per_thread"];
	
	$key = [
		$post["ip"],
	];
	
	if($per_board || $per_thread) {
		$key[] = $post["board"];
		
		if($per_thread) {
			$key[] =  $post["thread"];
		}
	}
	
	return gen_poster_id(serialize($key), salt("posterpublicid"), $board);
}

function get_post_fullno($post, $urlenc = false) {
	$fullno = "/{$post["board"]}/{$post["number"]}";
	
	if($urlenc) {
		$fullno = urlenc($fullno);
		$fullno = str_replace("%2F", "/", $fullno);
	}
	
	return $fullno;
}

function parse_post_fullno($fullno) {
	$match = preg_match(("/^\/?" . CONSTANTS::REGEX_BOARD_URI . "\/(\d+)$/"), $fullno, $matches);
	
	if(!$match) {
		return null;
	}
	
	return [
		"board" => $matches[1],
		"number" => $matches[2]
	];
}

function can_see_post($board, $post) {
	global $user;
	
	if($post["shadow"] && !(is_poster($post) || user_has_permission($user, $board, "see_shadow_posts"))) {
		return false;
	}
	
	if($post["deleted"] && !user_has_permission($user, $board, "see_deleted_posts")) {
		return false;
	}
	
	return true;
}

function is_poster($post) {
	return ($post["ip"] === get_remote_addr());
}

function respond_json($data) {
	b4k::send_response_json($data, false, true);
	exit;
}

function respond_text($data) {
	b4k::send_response_text($data);
	exit;
}

function setup_shutdown_func() {
	register_shutdown_function(function () {
		$badtypes = (E_ERROR | E_PARSE | E_CORE_ERROR | E_COMPILE_ERROR | E_USER_ERROR);
		$lasterror = error_get_last();
		
		if($lasterror !== null) {
			if($lasterror["type"] & $badtypes) {
				error(500);
			}
		}
	});
}

function error($error, $desc = null) {
	global $twig;
	
	twig_init();
	
	$name = b4k::get_status_text($error);
	
	b4k::set_status_code($error);
	
	$twig->display("error.html", [
		"error" => [
			"code" => $error,
			"name" => $name,
			"desc" => $desc
		]
	]);
	
	exit;
}

function tripcode_normal($password) {
	$password = mb_convert_encoding($password, "shift-jis", "utf-8");
	
	$salt = $password;
	$salt = "{$salt}H..";
	$salt = substr($salt, 1, 2);
	$salt = preg_replace("/[^.-z]/", ".", $salt);
	$salt = strtr($salt, ":;<=>?@[\]^_`", "ABCDEFGabcdef");
	
	$trip = crypt($password, $salt);
	$trip = substr($trip, -10);
	
	return $trip;
}

function tripcode_secure($password) {
	global $basepath;
	
	$salt = salt("securetrip");
	
	$chars = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789";
	$chars = str_split($chars);
	
	$hash = hash("sha256", ($password . $salt));
	$hash = unpack("I*", $hash);
	
	$trip = "";
	for($i = 1; $i <= count($hash); $i++) { $trip .= $chars[$hash[$i] % count($chars)]; }
	$trip = substr($trip, 0, 10);
	
	return $trip;
}

function salt($name) {
	global $basepath;
	
	static $salts = [];
	
	$salt_length = CONSTANTS::SALT_GENERATED_LENGTH;
	$salt_path = "{$basepath}/data/salts/{$name}.bin";
	
	if($salts[$name] === null && is_file($salt_path)) {
		$salts[$name] = b4k::file_read($salt_path);
	}
	
	if($salts[$name] === null || (strlen($salts[$name]) < $salt_length)) {
		$salts[$name] = openssl_random_pseudo_bytes($salt_length);
		
		if($salts[$name] === false) {
			throw new Exception("Failed to generate salt, openssl_random_pseudo_bytes() returned false");
		}
		
		b4k::make_dir(dirname($salt_path));
		b4k::file_write($salt_path, $salts[$name], true);
	}
	
	return $salts[$name];
}

function lang($string) {
	# in case I ever decide to implement a translation system
	return $string;
}

function url($path, $is_asset = false) {
	global $basepath;
	global $config;
	
	static $urlp = null;
	
	$path = (strlen($path) ? ltrim($path, "/") : "");
	
	if(preg_match("/^\#/", $path) || strlen(parse_url($path)["scheme"])) {
		return $path;
	}
	
	if($urlp === null) {
		$urlp = [
			"scheme" => (!empty($_SERVER["HTTPS"]) ? "https" : "http"),
			"host" => $_SERVER["HTTP_HOST"],
			"path" => ""
		];
		
		$urlc = $config["general"]["url"];
		$urlc = (strlen($urlc) ? parse_url($urlc) : null);
		
		if($urlc && strlen($urlc["host"])) {
			$urlp["scheme"] = (strlen($urlc["scheme"]) ? $urlc["scheme"] : $urlp["scheme"]);
			$urlp["host"] = $urlc["host"];
			$urlp["path"] = (strlen($urlc["path"]) ? trim($urlc["path"], "/") : $urlp["path"]);
		}
	}
	
	$url = ($urlp["scheme"] . "://" . $urlp["host"] . "/" . (strlen($urlp["path"]) ? ($urlp["path"] . "/") : "") . $path);
	
	if($is_asset) {
		$path_abs = "{$basepath}/web/{$path}";
		
		if(is_file($path_abs)) {
			$url = ($url . "?cb=" . (substr(md5(filemtime($path_abs)), 0, 6)));
		}
	}
	
	return $url;
}

function redirect($url) {
	b4k::send_redirect($url);
}

function urldec($string) {
	return b4k::url_decode($string);
}

function urlenc($string) {
	return b4k::url_encode($string);
}

function htmldec($string) {
	return b4k::html_decode($string);
}

function htmlenc($string) {
	return b4k::html_encode($string);
}

function http_param_bool($param) {
	return b4k::string_to_bool($param);
}

function get_user_agent() {
	return $_SERVER["HTTP_USER_AGENT"];
}

function get_remote_addr() {
	return b4k::get_request_remote_addr();
}

function get_path() {
	return b4k::get_request_path_only();
}

function get_path_full() {
	return b4k::get_request_path();
}

function get_time_sec() {
	return b4k::get_time_sec();
}

function get_time_sec_float() {
	return b4k::get_time_sec_float();
}

function get_time_msec() {
	return b4k::get_time_msec();
}

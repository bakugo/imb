<?php

class b4k {
	const chars_ascii_lower = "abcdefghijklmnopqrstuvwxyz";
	const chars_ascii_upper = "ABCDEFGHIJKLMNOPQRSTUVWXYZ";
	const chars_digits = "0123456789";
	const regex_newline = "\\r?\\n";
	const regex_jsonp_cb = "[0-9a-zA-Z_$\.]+";
	
	private static $mkdir_mode = 0777;
	private static $fopen_mode = 0777;
	private static $html_encoding_spec = ENT_HTML5;
	private static $curl_timeout = 5;
	
	private static $status_codes = [
		100 => "Continue",
		101 => "Switching Protocols",
		102 => "Processing",
		200 => "OK",
		201 => "Created",
		202 => "Accepted",
		203 => "Non-Authorative Information",
		204 => "No Content",
		205 => "Reset Content",
		206 => "Partial Content",
		207 => "Multi-Status",
		208 => "Already Reported",
		226 => "IM Used",
		300 => "Multiple Choices",
		301 => "Moved Permanently",
		302 => "Moved Temporarily",
		303 => "See Other",
		304 => "Not Modified",
		305 => "Use Proxy",
		307 => "Temporary Redirect",
		308 => "Permanent Redirect",
		400 => "Bad Request",
		401 => "Authorization Required",
		402 => "Payment Required",
		403 => "Forbidden",
		404 => "Not Found",
		405 => "Method Not Allowed",
		406 => "Not Acceptable",
		407 => "Proxy Authentication Required",
		408 => "Request Timed Out",
		409 => "Conflicting Request",
		410 => "Gone",
		411 => "Content Length Required",
		412 => "Precondition Failed",
		413 => "Request Entity Too Long",
		414 => "Request URI Too Long",
		415 => "Unsupported Media Type",
		416 => "Requested Range Not Satisfiable",
		417 => "Expectation Failed",
		421 => "Misdirected Request",
		422 => "Unprocessable Entity",
		423 => "Locked",
		424 => "Failed Dependency",
		426 => "Upgrade Required",
		428 => "Precondition Required",
		429 => "Too Many Requests",
		431 => "Request Header Fields Too Large",
		451 => "Unavailable For Legal Reasons",
		500 => "Internal Server Error",
		501 => "Not Implemented",
		502 => "Bad Gateway",
		503 => "Service Unavailable",
		504 => "Gateway Timeout",
		505 => "HTTP Version Not Supported",
		506 => "Variant Also Negotiates",
		507 => "Insufficient Storage",
		508 => "Loop Detected",
		510 => "Not Extended",
		511 => "Network Authentication Required"
	];
	
	private static $mimetypes = [
		"txt" => "text/plain",
		"html" => "text/html",
		"htm" => "text/html",
		"js" => "application/javascript",
		"css" => "text/css",
		"json" => "application/json",
		"xml" => "application/xml",
		"jpg" => "image/jpeg",
		"jpeg" => "image/jpeg",
		"png" => "image/png",
		"gif" => "image/gif",
		"ogg" => "audio/ogg",
		"mp3" => "audio/mpeg",
		"mp4" => "video/mp4",
		"webm" => "video/webm"
	];
	
	
	public static function get_time_sec() {
		return time();
	}
	
	public static function get_time_sec_float() {
		return microtime(true);
	}
	
	public static function get_time_msec() {
		return floor(microtime(true) * 1000);
	}
	
	public static function get_request_remote_addr() {
		return $_SERVER["REMOTE_ADDR"];
	}
	
	public static function get_request_protocol() {
		return ($_SERVER["HTTPS"] ? "https" : "http");
	}
	
	public static function get_request_host() {
		return $_SERVER["HTTP_HOST"];
	}
	
	public static function get_request_port() {
		return (int)$_SERVER["SERVER_PORT"];
	}
	
	public static function get_request_path() {
		return $_SERVER["REQUEST_URI"];
	}
	
	public static function get_request_path_only() {
		return parse_url($_SERVER["REQUEST_URI"], PHP_URL_PATH);
	}
	
	public static function get_request_query() {
		return parse_url($_SERVER["REQUEST_URI"], PHP_URL_QUERY);
	}
	
	public static function get_request_url() {
		return (($_SERVER["HTTPS"] ? "https" : "http") . "://" . $_SERVER["HTTP_HOST"] . $_SERVER["REQUEST_URI"]);
	}
	
	public static function get_request_params($types) {
		$params = [];
		
		$types = (($types !== null) ? $types : "get,post");
		$types = (is_string($types) ? preg_split("/( |,|;)+/", $types) : $types);
		
		if(is_array($types) && (count($types) > 0)) {
			foreach($types as $type) {
				$type = trim($type);
				$type = strtolower($type);
				
				switch($type) {
					case "get": $params = array_merge($params, $_GET); break;
					case "post": $params = array_merge($params, $_POST); break;
					case "cookie": $params = array_merge($params, $_COOKIE); break;
					case "files": $params = array_merge($params, $_FILES); break;
				}
			}
		}
		
		return $params;
	}
	
	public static function get_request_headers($lowerkeys = true) {
		$headers = getallheaders();
		
		if($lowerkeys) {
			foreach($headers as $key => $value) {
				unset($headers[$key]);
				$headers[strtolower($key)] = $value;
			}
		}
		
		return $headers;
	}
	
	public static function send_response($data = "") {
		echo $data;
		exit;
	}
	
	public static function send_response_text($data = "") {
		self::set_header("content-type", "text/plain");
		self::send_response($data);
	}
	
	public static function send_response_json($data = null, $prettyprint = false, $jsonp = true) {
		$data = self::json_encode($data, $prettyprint);
		
		$callback = null;
		
		if($jsonp) {
			foreach(["callback", "jsonp"] as $key) {
				if(isset($_GET[$key])) {
					$value = $_GET[$key];
					
					if(strlen($value) && preg_match(("/^" . self::regex_jsonp_cb . "$/"), $value)) {
						$callback = $value;
					}
				}
			}
		}
		
		if(strlen($callback)) {
			self::set_header("content-type", "application/javascript");
			self::send_response($callback . "(" . $data . ");");
		} else {
			self::set_header("content-type", "application/json");
			self::send_response($data);
		}
	}
	
	public static function send_response_file($path, $chunksize = (64 * 1024)) {
		if(!is_file($path)) {
			throw new Exception("File does not exist");
			exit;
		}
		
		ob_end_clean();
		
		$size = filesize($path);
		$mtime = filemtime($path);
		$sizereq = $size;
		
		$range_start = null;
		$range_end = null;
		
		if(isset($_SERVER["HTTP_RANGE"])) {
			preg_match("/bytes=(\d+)-(\d+)?/", $_SERVER["HTTP_RANGE"], $matches);
			
			$range_start = (int)$matches[1];
			$range_end = (int)$matches[2];
			
			$range_start = (($range_start > 0) ? $range_start : 0);
			$range_end = (($range_end > 0) ? $range_end : ($size - 1));
			
			$sizereq = ($range_end - $range_start + 1);
			
			self::set_status_code(206);
			self::set_header("content-range", ("bytes " . $range_start . "-" . $range_end . "/" . $size));
		}
		
		self::set_header("accept-ranges", "bytes");
		self::set_header("cache-control", "public");
		self::set_header("content-type", self::get_mimetype_file($path));
		self::set_header("last-modified", (gmdate("D, d M Y H:i:s", $mtime) . " GMT"));
		self::set_header("content-length", $sizereq);
		
		$file = fopen($path, "r");
		
		if($range_start !== null) {
			fseek($file, $range_start);
		}
		
		$sent = 0;
		
		while(!feof($file) && !connection_aborted() && ($sent < $sizereq)) {
			$buffer = fread($file, $chunksize);
			
			echo $buffer;
			
			flush();
			
			$sent += strlen($buffer);
		}
		
		fclose($file);
		
		exit;
	}
	
	public static function set_header($header, $value) {
		$header = preg_replace(("/" . self::regex_newline . "/"), " ", $header);
		$value = preg_replace(("/" . self::regex_newline . "/"), " ", $value);
		
		header($header . ":" . $value);
	}
	
	public static function unset_header($header) {
		header_remove($header);
	}
	
	public static function send_redirect($location) {
		self::set_header("location", $location);
		exit;
	}
	
	public static function get_status_code() {
		return (int)http_response_code();
	}
	
	public static function set_status_code($code = 200) {
		http_response_code($code);
		
		if($code >= 400) {
			self::set_header("cache-control", "no-cache");
		}
		
		return self::get_status_code();
	}
	
	public static function get_status_text($code) {
		if(isset(self::$status_codes[$code])) {
			return self::$status_codes[$code];
		}
		
		return null;
	}
	
	public static function random_float() {
		return (mt_rand() / mt_getrandmax());
	}
	
	public static function random_int($min = null, $max = null, $cryptosec = false) {
		$min = ($min !== null ? $min : 0);
		$max = ($min !== null ? $max : PHP_INT_MAX);
		
		$min = self::math_clamp($min, -PHP_INT_MAX, PHP_INT_MAX);
		$max = self::math_clamp($max, -PHP_INT_MAX, PHP_INT_MAX);
		
		if($max < $min) {
			throw new Exception("Maximum is less than minimum");
			return null;
		}
		
		if($cryptosec) {
			if(!function_exists("random_int")) {
				throw new Exception("Function random_int() doesn't exist");
				return null;
			}
			
			return random_int($min, $max);
		} else {
			return mt_rand($min, $max);
		}
	}
	
	public static function random_item($array, $cryptosec = false) {
		$array = array_values($array);
		
		$index = self::random_int(0, (count($array) - 1), $cryptosec);
		
		return $array[$index];
	}
	
	public static function random_string($length = 64, $chars = null, $cryptosec = false) {
		if($chars === null) {
			$chars = [self::chars_ascii_lower, self::chars_ascii_upper, self::chars_digits];
		}
		
		if(is_array($chars)) {
			$chars = implode("", $chars);
		}
		
		$chars = trim($chars);
		
		if(strlen($chars) === 0) {
			throw new Exception("No characters to generate string from");
			return null;
		}
		
		$chars = str_split($chars);
		
		$string = "";
		
		for($i = 0; $i < $length; $i++) {
			$string .= self::random_item($chars, $cryptosec);
		}
		
		return $string;
	}
	
	public static function math_clamp($number, $min = PHP_INT_MIN, $max = PHP_INT_MAX) {
		if($max < $min) {
			throw new Exception("Maximum is less than minimum");
			return null;
		}
		
		$number = max($number, $min);
		$number = min($number, $max);
		
		return $number;
	}
	
	public static function trim_slashes($string, $left = false, $right = true) {
		if($left) {
			$string = ltrim($string, "\\/");
		}
		
		if($right) {
			$string = rtrim($string, "\\/");
		}
		
		return $string;
	}
	
	public static function add_leading_zeroes($number, $chars = 2) {
		$chars = max($chars, 0);
		
		$length = mb_strlen($number);
		
		if($length < $chars) {
			for($i = 0; $i < ($chars - $length); $i++) {
				$number = ("0" . $number);
			}
		}
		
		return $number;
	}
	
	public static function bool_to_string($bool = false, $numbers = false) {
		return ($numbers ? ($bool ? "1" : "0") : ($bool ? "true" : "false"));
	}
	
	public static function string_to_bool($string = "", $strict = false, $casesens = false) {
		$string = (string)$string;
		$string = trim($string);
		$string = ($casesens ? $string : strtolower($string));
		
		if($strict) {
			return ($string === "1" || $string === "true");
		} else {
			return (strlen($string) && $string !== "0" && $string !== "false");
		}
	}
	
	public static function format_string($string, $variables, $method = null) {
		if($method === null) {
			$method = "{.}";
		}
		
		foreach($variables as $key => $value) {
			$string = str_replace(str_replace(".", $key, $method), (string)$value, $string);
		}
		
		return $string;
	}
	
	public static function format_bytes($bytes, $precision = 2, $yotsubamode = false) {
		$units = ["B", "KB", "MB", "GB", "TB"];
		
		$pow = floor(($bytes ? log($bytes) : 0) / log(1024));
		$pow = min($pow, (count($units) - 1));
		
		if($yotsubamode) {
			if($pow < 2) {
				$precision = 0;
			}
		}
		
		return (round(($bytes / pow(1024, $pow)), $precision) . " " . $units[$pow]);
	}
	
	public static function url_encode($string) {
		return rawurlencode($string);
	}
	
	public static function url_decode($string) {
		return rawurldecode($string);
	}
	
	public static function html_encode($string, $all = false) {
		if($all) {
			return htmlentities($string, (ENT_QUOTES | self::$html_encoding_spec));
		} else {
			return htmlspecialchars($string, (ENT_QUOTES | self::$html_encoding_spec));
		}
	}
	
	public static function html_decode($string) {
		return html_entity_decode($string, (ENT_QUOTES | self::$html_encoding_spec));
	}
	
	public static function json_encode($data, $prettyprint = false) {
		return json_encode($data, ($prettyprint ? JSON_PRETTY_PRINT : 0));
	}
	
	public static function json_decode($string) {
		return json_decode($string, true);
	}
	
	public static function make_dir($path) {
		@mkdir($path, self::$mkdir_mode, true);
	}
	
	public static function scan_dir($path, $fullpath = false) {
		$path = self::trim_slashes($path, false, true);
		
		if(!is_dir($path)) {
			return null;
		}
		
		$files = scandir($path);
		
		$files = array_values(array_diff($files, ["..", "."]));
		
		if($fullpath) {
			foreach($files as &$file) {
				$file = ($path . "/" . $file);
			}
		}
		
		return $files;
	}
	
	public static function scan_dir_recursive($path, $fullpath = false) {
		$path = self::trim_slashes($path, false, true);
		
		$files_root = self::scan_dir($path, false);
		
		if($files_root === null) {
			return null;
		}
		
		$files = [];
		
		foreach($files_root as $file) {
			if(is_dir($path . "/" . $file)) {
				$files_sub = self::scan_dir_recursive(($path . "/" . $file), false);
				
				foreach($files_sub as $file_sub) {
					$files[] = ($file . "/" . $file_sub);
				}
			} else {
				$files[] = $file;
			}
		}
		
		if($fullpath) {
			foreach($files as &$file) {
				$file = ($path . "/" . $file);
			}
		}
		
		return $files;
	}
	
	public static function file_read($path, $timeout = null) {
		$data = null;
		
		if(preg_match("/^(https?:)?\/\//", $path)) {
			if(preg_match("/^\/\//", $path)) {
				$path = ("http:" . $path);
			}
			
			$ch = curl_init();
			
			curl_setopt_array($ch, [
				CURLOPT_RETURNTRANSFER => true,
				CURLOPT_URL => $path,
				CURLOPT_TIMEOUT => ($timeout !== null ? $timeout : self::$curl_timeout),
				CURLOPT_FOLLOWLOCATION => true,
				CURLOPT_AUTOREFERER => true,
				CURLOPT_HEADER => false
			]);
			
			$response = curl_exec($ch);
			
			$status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
			
			if($status > 0 && $status < 400) {
				$data = $response;
			}
			
			curl_close($ch);
		} else {
			if(preg_match("/^php:\/\//", $path) || is_file($path)) {
				$data = file_get_contents($path);
			}
		}
		
		return $data;
	}
	
	public static function file_write($path, $contents = null, $clear = false) {
		self::make_dir(dirname($path));
		
		$file = fopen($path, ($clear ? "w" : "a"));
		
		if(strlen($contents)) {
			fwrite($file, $contents);
		}
		
		fclose($file);
		
		return true;
	}
	
	public static function get_mimetype_file($path) {
		if(is_file($path)) {
			$finfo = finfo_open(FILEINFO_MIME_TYPE);
			
			try {
				return finfo_file($finfo, $path, FILEINFO_MIME_TYPE);
			} catch(Exception $e) {
				
			}
		}
		
		return null;
	}
	
	public static function get_mimetype_filename($filename) {
		if(strpos(".", $filename) !== false) {
			$extension = pathinfo($filename, PATHINFO_EXTENSION);
		} else {
			$extension = $filename;
		}
		
		if(isset(self::$mimetypes[$extension])) {
			return self::$mimetypes[$extension];
		}
		
		return null;
	}
	
	public static function get_file_data_url($path) {
		$data = self::file_read($path);
		
		if($data === null) {
			return null;
		}
		
		$mimetype = self::get_mimetype_file($path);
		
		if($mimetype === null) {
			$mimetype = "";
		}
		
		$data = base64_encode($data);
		
		return ("data:" . $mimetype . ";base64," . $data);
	}
}

<?php

function get_file_type($extension, $board) {
	global $config;
	
	foreach($board["config"]["file_types"] as $file_type) {
		if((is_array($file_type["ext"]) && in_array($extension, $file_type["ext"], true)) || $file_type["ext"] === $extension) {
			return $file_type;
		}
	}
}

function get_mime_from_ext($ext) {
	if(strlen(CONSTANTS::MIMETYPES_OF_EXTENSIONS[$ext])) {
		return CONSTANTS::MIMETYPES_OF_EXTENSIONS[$ext];
	}
	
	return null;
}

function get_ext_from_mime($mime) {
	foreach(CONSTANTS::MIMETYPES_OF_EXTENSIONS as $i_ext => $i_mime) {
		if($i_mime === $mime) {
			return $i_ext;
		}
	}
	
	return null;
}

function get_temp_file_path() {
	return stream_get_meta_data(tmpfile())["uri"];
}

function get_thumb_dimensions($dimensions, $is_op, $board, $in_catalog = false, $force_max = false) {
	$key = ($in_catalog ? "catalog" : ($is_op ? "op" : "reply"));
	$max = $board["config"]["thumbnail_dimensions"][$key];
	
	if($force_max) {
		return [
			"width" => $max,
			"height" => $max
		];
	}
	
	$largest = max($dimensions["width"], $dimensions["height"]);
	
	$ratio = (min($max, $largest) / $largest);
	
	return [
		"width" => round($ratio * $dimensions["width"]),
		"height" => round($ratio * $dimensions["height"])
	];
}

function get_file_path_local($info, $deleted = false) {
	global $config;
	
	$path = ".";
	
	$path .= ("/". ($deleted ? "deleted" : "alive"));
	$path .= ("/" . ($info["is_thumb"] ? "thb" : "src"));
	$path .= ("/" . substr($info["hash"], 0, 2) . "/" . substr($info["hash"], 2, 2));
	$path .= ("/file-" . substr($info["hash"], 0, CONSTANTS::FILE_HASH_USED_LENGTH));
	$path .= ("-" . ($info["is_thumb"] ? ($info["is_thumb_op"] ? "thb-op" : "thb-rp") : "src"));
	$path .= ("." . ($info["is_thumb"] ? "jpg" : $info["extension"]));
	
	#var_dump($path);
	
	return $path;
}

function get_file_path_web($info, $nameonly = false) {
	global $config;
	
	$prefix = $config["general"]["file_prefix"];
	$extfiles = $config["general"]["external_files"];
	
	$path = "";
	
	if (!$nameonly) {
		if($extfiles["enabled"] && strlen($extfiles["web_path"])) {
			$path .= (rtrim($extfiles["web_path"], "/") . "/");
		} else {
			$path .= "/files/";
		}
	}
	
	$path .= (strlen($prefix) ? ($prefix . "-") : "");
	$path .= (substr($info["hash"], 0, CONSTANTS::FILE_HASH_USED_LENGTH));
	$path .= ("-" . ($info["is_thumb"] ? ($info["is_thumb_op"] ? "thb-op" : "thb-rp") : "src"));
	$path .= ("." . ($info["is_thumb"] ? "jpg" : $info["extension"]));
	
	return $path;
}

function parse_file_request($path) {
	preg_match(("/^" . CONSTANTS::REGEX_FILE_REQUEST . "$/"), $path, $matches);
	
	if(count($matches) > 0) {
		return [
			"hash" => $matches[1],
			"extension" => $matches[3],
			"is_thumb" => in_array($matches[2], ["thb-op", "thb-rp"]),
			"is_thumb_op" => ($matches[2] === "thb-op")
		];
	}
	
	return null;
}

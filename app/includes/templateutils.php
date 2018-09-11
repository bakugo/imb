<?php

function get_favicon() {
	global $basepath;
	
	$url = null;
	
	if($url === null) {
		if(is_file("{$basepath}/web/assets-custom/favicon.png")) {
			$url = "/assets-custom/favicon.png";
		}
	}
	
	if($url === null) {
		$url = "/assets/favicon.png";
	}
	
	return $url;
}

function get_banner() {
	global $basepath;
	
	$url = null;
	
	if($url === null) {
		if(is_dir("{$basepath}/web/assets-custom/banners/")) {
			$files = scandir("{$basepath}/web/assets-custom/banners/");
			$files = array_values(array_diff($files, [".", ".."]));
			
			if($files && count($files)) {
				$url = ("/assets-custom/banners/" . $files[mt_rand(0, (count($files) - 1))]);
			}
		}
	}
	
	if($url === null) {
		if(is_file("{$basepath}/web/assets-custom/banner.png")) {
			$url = "/assets-custom/banner.png";
		}
	}
	
	if($url === null) {
		$url = "/assets/banner.png";
	}
	
	return $url;
}

function get_debug_info() {
	global $timestart;
	
	return [
		"time" => round((get_time_sec_float() - $timestart), 3),
		"memory" => round((memory_get_peak_usage(true) / pow(1024, 2)), 2),
		"incfiles" => count(get_included_files())
	];
}

function get_user_template($template, $prefix = "", $suffix = "") {
	global $basepath;
	global $twig;
	
	$path = "{$basepath}/config/html/{$template}.html";
	
	if(is_file($path)) {
		return ($prefix . $twig->createTemplate(b4k::file_read($path))->render([]) . $suffix);
	}
	
	return null;
}

<?php

function process_user(&$user) {
	$user["id"] = (int)$user["id"];
	$user["level"] = (int)$user["level"];
	$user["enabled"] = (bool)$user["enabled"];
	
	return $user;
}

function process_board(&$board) {
	global $config;
	
	$board["id"] = (int)$board["id"];
	$board["locked"] = (bool)$board["locked"];
	$board["post_number"] = (int)$board["post_number"];
	$board["config"] = call_user_func(function() use($board, $config) {
		$board_config = $config["boards"]["*"];
		
		if($config["boards"][$board["uri"]]) {
			$board_config = array_replace_recursive($board_config, $config["boards"][$board["uri"]]);
		}
		
		return $board_config;
	});
	
	return $board;
}

function process_thread(&$thread) {
	$thread["id"] = (int)$thread["id"];
	$thread["number"] = (int)$thread["number"];
	$thread["time_bumped"] = (float)$thread["time_bumped"];
	$thread["time_modified"] = (float)$thread["time_modified"];
	$thread["state"] = [
		"stickied" => (bool)$thread["stickied"],
		"locked" => (bool)$thread["locked"],
		"bumplocked" => (bool)$thread["bumplocked"],
		"archived" => (bool)$thread["archived"]
	];
	
	return $thread;
}

function process_thread_stats(&$stats) {
	$stats["posts"] = (int)$stats["posts"];
	$stats["files"] = (int)$stats["files"];
	$stats["posters"] = (int)$stats["posters"];
	
	return $stats;
}

function process_post(&$post) {
	$post["id"] = (int)$post["id"];
	$post["number"] = (int)$post["number"];
	$post["thread"] = (int)$post["thread"];
	$post["time_posted"] = (float)$post["time_posted"];
	$post["time_modified"] = (float)$post["time_modified"];
	$post["user"] = ($post["user"] ? (int)$post["user"] : null);
	$post["tripcode"] = ((strlen($post["tripcode"]) && preg_match("/^\!\!?/", $post["tripcode"])) ? $post["tripcode"] : null);
	$post["sage"] = (bool)$post["sage"];
	$post["enable_html"] = (bool)$post["enable_html"];
	$post["public_ban"] = (bool)$post["public_ban"];
	$post["shadow"] = (bool)$post["shadow"];
	$post["deleted"] = (bool)$post["deleted"];
	
	return $post;
}

function process_post_file(&$file) {
	$file["id"] = (int)$file["id"];
	$file["number"] = (int)$file["number"];
	$file["filesize"] = (int)$file["filesize"];
	$file["duration"] = ($file["duration"] ? (int)$file["duration"] : null);
	$file["spoiler"] = (bool)$file["spoiler"];
	$file["deleted"] = (bool)$file["deleted"];
	$file["dimensions"] = call_user_func(function() use($file) {
		if($file["dimensions"] !== null) {
			if(preg_match("/^(\d+)x(\d+)$/", $file["dimensions"], $matches)) {
				return [
					"width" => (int)$matches[1],
					"height" => (int)$matches[2]
				];
			}
		}
		
		return null;
	});
	
	return $file;
}

function process_ban(&$ban, $time) {
	$ban["id"] = (int)$ban["id"];
	$ban["time"] = (float)$ban["time"];
	$ban["creator"] = ($ban["creator"] ? (int)$ban["creator"] : null);
	$ban["post"] = ($ban["post"] ? (int)$ban["post"] : null);
	$ban["length"] = ($ban["length"] ? (float)$ban["length"] : null);
	$ban["seen"] = (bool)$ban["seen"];
	$ban["active"] = (($ban["type"] === "banperm") || ($ban["type"] === "bantemp" && (($ban["time"] + $ban["length"]) > $time)));
	$ban["mustsee"] = (!$ban["seen"] || $ban["active"]);
	
	return $ban;
}

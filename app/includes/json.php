<?php

function json_process_user($user) {
	# nice meme
}

function json_process_board($board) {
	$board_new = [];
	
	$board_new["id"] = $board["id"];
	$board_new["uri"] = $board["uri"];
	$board_new["title"] = $board["title"];
	$board_new["subtitle"] = $board["subtitle"];
	$board_new["locked"] = $board["locked"];
	$board_new["post_number"] = $board["post_number"];
	#$board_new["config"] = $board["config"];
	
	return $board_new;
}

function json_process_thread($board, $thread, $mode = null, $render = false) {
	global $user;
	
	$thread_new = [];
	
	$thread_new["board"] = $thread["board"];
	$thread_new["number"] = $thread["number"];
	$thread_new["time_posted"] = ($thread["post"] ? $thread["post"]["time_posted"] : null);
	$thread_new["time_modified"] = $thread["time_modified"];
	$thread_new["time_bumped"] = $thread["time_bumped"];
	$thread_new["summary"] = (strlen($thread["summary"]) ? $thread["summary"] : null);
	$thread_new["state"] = json_process_thread_state($board, $thread["state"]);
	
	if($thread["stats"]) {
		$thread_new["stats"] = $thread["stats"];
	}
	
	if($thread["replies_after"] !== null) {
		$thread_new["replies_after"] = $thread["replies_after"];
	}
	
	if($thread["post"]) {
		$thread_new["post"] = json_process_post($board, $thread["post"], $thread, $mode, $render);
	}
	
	if($thread["replies"] !== null) {
		$thread_new["replies"] = json_process_post_arr($board, $thread["replies"], $mode, $render);
	}
	
	if($render) {
		if($mode === "index_catalog") {
			$thread_new["html"] = render_catalog_thread($thread);
		} else {
			$thread_new["html"] = render_thread($thread, ($mode === "index_paged"), ($mode === "index_paged"), true);
		}
	}
	
	return $thread_new;
}

function json_process_thread_state($board, $state) {
	global $user;
	
	$state_new = [];
	
	$state_new["stickied"] = $state["stickied"];
	$state_new["locked"] = $state["locked"];
	
	if(user_has_permission($user, $board, "see_thread_bumplock")) {
		$state_new["bumplocked"] = $state["bumplocked"];
	}
	
	$state_new["archived"] = $state["archived"];
	
	return $state_new;
}

function json_process_post($board, $post, $thread = null, $mode = null, $render = false) {
	global $user;
	global $config;
	
	$post_new = [];
	
	$post_new["board"] = $post["board"];
	$post_new["number"] = $post["number"];
	$post_new["thread"] = $post["thread"];
	$post_new["time_posted"] = $post["time_posted"];
	$post_new["time_modified"] = $post["time_modified"];
	
	$post_new["author"] = [];
	
	if(user_has_permission($user, $board, "see_poster_ips")) {
		$post_new["author"]["ip"] = $post["ip"];
	}
	
	if(user_has_permission($user, $board, "see_poster_users")) {
		$post_new["author"]["user"] = $post["user"];
	}
	
	$post_new["author"]["name"] = $post["name"];
	$post_new["author"]["tripcode"] = $post["tripcode"];
	$post_new["author"]["capcode"] = $post["capcode"];
	
	if($board["config"]["poster_country_flags"]["enabled"]) {
		if($post["ip"] && (!strlen($post["capcode"]) || !$board["config"]["poster_country_flags"]["hide_if_capcoded"])) {
			$post_new["author"]["country"] = geoip_country($post["ip"]);
		} else {
			$post_new["author"]["country"] = null;
		}
	}
	
	$post_new["subject"] = $post["subject"];
	$post_new["comment_raw"] = $post["comment"];
	$post_new["comment_html"] = (strlen($post["comment"]) ? render_post_comment($post, ($mode === "index_catalog")) : $post["comment"]);
	
	if(!is_post_op($post) && user_has_permission($user, $board, "see_post_sage")) {
		$post_new["sage"] = $post["sage"];
	}
	
	$post_new["enable_html"] = !!$post["enable_html"];
	$post_new["public_ban"] = !!$post["public_ban"];
	
	if(user_has_permission($user, $board, "see_deleted_posts")) {
		$post_new["deleted"] = $post["deleted"];
	}
	
	if(user_has_permission($user, $board, "see_shadow_posts")) {
		$post_new["shadow"] = $post["shadow"];
	}
	
	if($post["file"]) {
		$post_new["file"] = json_process_file($board, $post["file"], is_post_op($post));
	}
	
	if($render && $mode !== "index_catalog") {
		$post_new["html"] = render_post($post, $thread, in_array($mode, ["index_paged", "index_catalog", "thread"]), ($mode === "thread"));
	}
	
	return $post_new;
}

function json_process_file($board, $file, $is_op = false) {
	$file_new = [];
	
	$file_new["md5"] = $file["md5"];
	$file_new["webpath_src"] = get_file_path_web(["hash"=>$file["md5"],"extension"=>$file["extension"],"is_thumb"=>false], true);
	$file_new["webpath_thb"] = get_file_path_web(["hash"=>$file["md5"],"extension"=>$file["extension"],"is_thumb"=>true,"is_thumb_op"=>$is_op], true);
	$file_new["extension"] = $file["extension"];
	$file_new["mimetype"] = get_mime_from_ext($file["extension"]);
	$file_new["filesize"] = $file["filesize"];
	$file_new["filename"] = $file["filename"];
	$file_new["dimensions"] = "{$file["dimensions"]["width"]}x{$file["dimensions"]["height"]}";
	$file_new["duration"] = $file["duration"];
	$file_new["spoiler"] = $file["spoiler"];
	$file_new["deleted"] = $file["deleted"];
	
	return $file_new;
}

function json_process_thread_arr($board, $threads, $mode = null, $render = false) {
	$threads_new = [];
	
	foreach($threads as $thread) {
		$threads_new[] = json_process_thread($board, $thread, $mode, $render);
	}
	
	return $threads_new;
}

function json_process_post_arr($board, $posts, $mode = null, $render = false) {
	$posts_new = [];
	
	foreach($posts as $post) {
		$posts_new[] = json_process_post($board, $post, null, $mode, $render);
	}
	
	return $posts_new;
}

<?php

function route_board($board, $path) {
	global $boards;
	global $user;
	
	if(!$board) {
		return false;
	}
	
	if(!user_has_permission($user, $board, "access_board")) {
		if (user_has_permission($user, $board, "see_board")) {
			error(403, "You do not have access to this board.");
			return true;
		} else {
			return false;
		}
	}
	
	if($path === "") {
		redirect(url("/{$board["uri"]}/")); return true;
	}
	
	if($path === "/") {
		return route_board_index($board, "paged");
	}
	
	if($path === "/info") {
		return route_board_info($board);
	}
	
	if(preg_match("/^\/(index|paged|catalog)$/", $path, $matches)) {
		return route_board_index($board, $matches[1]);
	}
	
	if(preg_match("/^\/post\/(\d+?)$/", $path, $matches)) {
		return route_board_post($board, $matches[1]);
	}
	
	if(preg_match("/^\/(?:thread|res)\/(\d+?)(\/[a-zA-Z0-9-]*)?$/", $path, $matches)) {
		return route_board_thread($board, $matches[1]);
	}
	
	return false;
}

function route_board_info($board) {
	respond_json([
		"board" => json_process_board($board)
	]);
	
	return true;
}

function route_board_index($board, $index_mode) {
	global $httpvars;
	global $db;
	global $twig;
	
	twig_init();
	
	$page_index = 0;
	$max_thread_count = 0;
	
	if($index_mode === "index") {
		$index_mode = "paged";
	}
	
	switch($index_mode) {
		case "index":
		case "paged":
			if($index_mode === "index") { $index_mode = "paged"; }
			$page_index = ((int)$httpvars["get"]["page"] - 1);
			$max_thread_count = (int)$board["config"]["index_thread_count_per_page"];
			break;
		
		case "catalog":
			$max_thread_count = (int)$board["config"]["catalog_thread_count"];
			break;
	}
	
	$page_index = min(max($page_index, 0), 1000000);
	$max_thread_count = min(max($max_thread_count, 0), 1000);
	
	if($max_thread_count === 0) {
		$max_thread_count = 100;
	}
	
	$thread_offset = ($page_index * $max_thread_count);
	
	$query = $db->prepare("SELECT * FROM threads FORCE INDEX(board) WHERE (board = :board) ORDER BY stickied DESC, time_bumped DESC, number DESC LIMIT {$max_thread_count} OFFSET {$thread_offset}");
	$query->bindValue(":board", $board["uri"]);
	$query->execute();
	
	$threads = $query->fetchAll(PDO::FETCH_ASSOC);
	
	foreach($threads as &$thread) {
		process_thread($thread);
	}
	
	load_threads_posts($threads, ($index_mode === "paged"));
	
	$posts = [];
	
	foreach($threads as $i => &$thread) {
		check_thread_archived($thread);
		
		if(!$thread["post"] || !can_see_post($board, $thread["post"])) {
			unset($threads[$i]);
			continue;
		}
		
		$posts[] = &$thread["post"];
		
		if($thread["replies"]) {
			foreach($thread["replies"] as $i => &$reply) {
				if(!can_see_post($board, $reply)) {
					unset($thread["replies"][$i]);
					continue;
				}
				
				$posts[] = &$reply;
			}
			
			$thread["replies"] = array_values($thread["replies"]);
		}
	}
	
	$threads = array_values($threads);
	
	load_posts_files($posts);
	
	load_threads_stats($threads);
	
	foreach($threads as &$thread) {
		$thread["summary"] = render_post_summary($thread["post"]);
	}
	
	if($index_mode === "paged") {
		foreach($threads as $i => &$thread) {
			$max_shown_replies = $board["config"]["index_shown_replies" . ($thread["state"]["stickied"] ? "_sticky" : "")];
			$max_shown_replies = min(intval($max_shown_replies), CONSTANTS::BOARD_INDEX_SHOWN_REPLIES_MAX);
			
			if(count($thread["replies"]) >= $max_shown_replies) {
				$thread["replies"] = array_slice($thread["replies"], (count($thread["replies"]) - $max_shown_replies));
			}
		}
	}
	
	if(get_requested_format() === "json") {
		respond_json([
			"threads" => json_process_thread_arr($board, $threads, "index_{$index_mode}", http_param_bool($httpvars["get"]["render"]))
		]);
	} else {
		$twig->display("board_index.html", [
			"board" => $board,
			"index_mode" => $index_mode,
			"data_html" => call_user_func(function() use ($threads, $index_mode) {
				$html = "";
				
				foreach($threads as $i => $thread) {
					switch($index_mode) {
						case "paged":
							$html .= ((($i > 0) ? "<hr>" : "") . render_thread($thread, true, true, false));
							break;
						
						case "catalog":
							$html .= render_catalog_thread($thread);
							break;
					}
				}
				
				return $html;
			})
		]);
	}
	
	return true;
}

function route_board_post($board, $number) {
	global $httpvars;
	global $db;
	
	$query = $db->prepare("SELECT * FROM posts FORCE INDEX (board__number) WHERE (board = :board AND number = :number)");
	$query->bindValue(":board", $board["uri"]);
	$query->bindValue(":number", $number);
	$query->execute();
	
	$post = $query->fetch(PDO::FETCH_ASSOC);
	
	if($post) {
		process_post($post);
	}
	
	if(!$post || !can_see_post($board, $post)) {
		return false;
	}
	
	$query = $db->prepare("SELECT * FROM threads FORCE INDEX (board__number) WHERE (board = :board AND number = :number)");
	$query->bindValue(":board", $post["board"]);
	$query->bindValue(":number", $post["thread"]);
	$query->execute();
	
	$thread = $query->fetch(PDO::FETCH_ASSOC);
	
	if(!$thread) {
		return false;
	}
	
	process_thread($thread);
	
	if(is_post_op($post)) {
		$thread["post"] = $post;
	} else {
		$query = $db->prepare("SELECT * FROM posts FORCE INDEX (board__number) WHERE (board = :board AND number = :number)");
		$query->bindValue(":board", $post["board"]);
		$query->bindValue(":number", $post["thread"]);
		$query->execute();
		
		$thread["post"] = $query->fetch(PDO::FETCH_ASSOC);
		
		if(!$thread["post"]) {
			return false;
		}
		
		process_post($thread["post"]);
	}
	
	if($thread["post"] && !can_see_post($board, $thread["post"])) {
		return false;
	}
	
	load_post_file($post);
	
	if(get_requested_format() === "json") {
		respond_json([
			"post" => json_process_post($board, $post, $thread, "post", http_param_bool($httpvars["get"]["render"]))
		]);
	} else {
		$url = url("/{$post["board"]}/thread/{$post["thread"]}" . (!is_post_op($post) ? "#p{$post["number"]}" : ""));
		redirect($url);
	}
	
	return true;
}

function route_board_thread($board, $number) {
	global $httpvars;
	global $db;
	global $twig;
	
	twig_init();
	
	$after_number = floatval($httpvars["get"]["after_number"]);
	$after_time = floatval($httpvars["get"]["after_time"]);
	
	$query = $db->prepare("SELECT * FROM threads FORCE INDEX (board__number) WHERE (board = :board AND number = :number)");
	$query->bindValue(":board", $board["uri"]);
	$query->bindValue(":number", $number);
	$query->execute();
	
	$thread = $query->fetch(PDO::FETCH_ASSOC);
	
	if(!$thread) {
		return false;
	}
	
	process_thread($thread);
	
	check_thread_archived($thread);
	
	$query = $db->prepare("SELECT * FROM posts FORCE INDEX (board__thread__number) WHERE (board = :board AND number = :number)");
	$query->bindValue(":board", $thread["board"]);
	$query->bindValue(":number", $thread["number"]);
	$query->execute();
	
	$thread["post"] = $query->fetch(PDO::FETCH_ASSOC);
	
	if($thread["post"]) {
		process_post($thread["post"]);
	}
	
	if(!$thread["post"] || !can_see_post($board, $thread["post"])) {
		return false;
	}
	
	load_thread_stats($thread);
	
	$query = $db->prepare("SELECT * FROM posts FORCE INDEX (board__thread) WHERE (board = :board AND thread = :thread AND number != thread) ORDER BY number ASC");
	$query->bindValue(":board", $thread["board"]);
	$query->bindValue(":thread", $thread["number"]);
	$query->execute();
	
	$thread["replies"] = $query->fetchAll(PDO::FETCH_ASSOC);
	
	foreach($thread["replies"] as $i => &$reply) {
		process_post($reply);
		
		if(!can_see_post($board, $reply)) {
			unset($thread["replies"][$i]);
			continue;
		}
		
		if(($after_number || $after_time) && !(($after_number && ($reply["number"] > $after_number)) || ($after_time && ($reply["time_modified"] > $after_time))))  {
			unset($thread["replies"][$i]);
			continue;
		}
	}
	
	$thread["replies"] = array_values($thread["replies"]);
	
	$posts = [];
	
	$posts[] = &$thread["post"];
	
	foreach($thread["replies"] as &$reply) {
		$posts[] = &$reply;
	}
	
	load_posts_files($posts);
	
	$latest_number = 0;
	$latest_time = 0;
	
	foreach($posts as $post) {
		$latest_number = max($latest_number, $post["number"]);
		$latest_time = max($latest_time, $post["time_modified"]);
	}
	
	$thread["summary"] = render_post_summary($thread["post"]);
	
	$thread["image"] = call_user_func(function() use($thread) {
		if($thread["post"]["file"]) {
			return url(get_file_path_web([
				"hash" => $thread["post"]["file"]["md5"],
				"extension" => $thread["post"]["file"]["extension"],
				"is_thumb" => in_array($thread["post"]["file"]["extension"], ["jpg", "png"]),
				"is_thumb_op" => true
			]));
		}
		
		return null;
	});
	
	if(get_requested_format() === "json") {
		respond_json([
			"thread" => json_process_thread($board, $thread, "thread", http_param_bool($httpvars["get"]["render"]))
		]);
	} else {
		$twig->display("board_thread.html", [
			"board" => $board,
			"thread" => $thread,
			"image" => $thread["image"],
			"data_html" => render_thread($thread, false, false, false),
			"page_data" => [
				"thread_stats" => [
					"posts" => intval($thread["stats"]["posts"]),
					"files" => intval($thread["stats"]["files"]),
					"posters" => intval($thread["stats"]["posters"])
				],
				"thread_updater" => [
					"updateLocation" => url("/" . urlenc($post["board"]) . "/thread/" . $thread["number"]),
					"latestNumber" => $latest_number,
					"latestTime" => $latest_time,
					"threadState" => json_process_thread_state($board, $thread["state"])
				]
			]
		]);
	}
	
	return true;
}

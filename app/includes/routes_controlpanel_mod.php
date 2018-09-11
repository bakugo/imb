<?php

function route_controlpanel_mod($path) {
	if($path === "/recent-posts") {
		if(route_controlpanel_mod_recentposts()) return true;
	}
	
	if($path === "/reports") {
		return true;
	}
	
	if($path === "/ban-list") {
		return true;
	}
	
	if($path === "/ban-appeals") {
		return true;
	}
	
	if($path === "/add-ban") {
		if(route_controlpanel_mod_addban()) return true;
	}
	
	if($path === "/poster-info") {
		return true;
	}
	
	if($path === "/post-action") {
		if(route_controlpanel_mod_postaction()) return true;
	}
	
	if($path === "/edit-post") {
		if(route_controlpanel_mod_editpost()) return true;
	}
	
	return false;
}

function route_controlpanel_mod_recentposts() {
	global $db;
	global $twig;
	global $httpvars;
	
	twig_init();
	
	$count = intval($httpvars["get"]["count"]);
	$offset = intval($httpvars["get"]["offset"]);
	
	$count = max($count, 0);
	$offset = max($offset, 0);
	
	if($count < 1) {
		$count = 20;
	}
	
	$count = min($count, 1000);
	
	$query = $db->prepare("SELECT * FROM posts ORDER BY time_posted DESC LIMIT {$count} OFFSET {$offset}");
	$query->execute();
	
	$posts = $query->fetchAll(PDO::FETCH_ASSOC);
	
	foreach($posts as $i => &$post) {
		process_post($post);
		
		if(!get_board_by_uri($post["board"])) {
			unset($posts[$i]);
		}
	}
	
	load_posts_files($posts);
	
	$posts_html = call_user_func(function() use($posts) {
		$html = "";
		
		foreach($posts as $post) {
			$html .= render_post($post, null, false, false, true);
		}
		
		return $html;
	});
	
	$twig->display("controlpanel_mod_recentposts.html", [
		"count" => $count,
		"offset" => $offset,
		"posts_html" => $posts_html
	]);
	
	return true;
}

function route_controlpanel_mod_postaction() {
	global $user;
	global $db;
	global $twig;
	global $httpvars;
	
	twig_init();
	
	$time = get_time_msec();
	
	$action = $httpvars["get"]["act"];
	$postinfo = parse_post_fullno($httpvars["get"]["post"]);
	
	if(!strlen($action)) {
		exit("no action");
	}
	
	if(!$postinfo) {
		exit("invalid post");
	}
	
	$board = get_board_by_uri($postinfo["board"]);
	
	if(!$board) {
		exit("board doesn't exist");
	}
	
	$query = $db->prepare("SELECT * FROM posts FORCE INDEX (board__number) WHERE (board = :board AND number = :number)");
	$query->bindValue(":board", $board["uri"]);
	$query->bindValue(":number", $postinfo["number"]);
	$query->execute();
	
	$post = $query->fetch(PDO::FETCH_ASSOC);
	
	if(!$post) {
		exit("post doesn't exist");
	}
	
	process_post($post);
	
	$query = $db->prepare("SELECT * FROM files FORCE INDEX (board__number) WHERE (board = :board AND number = :number)");
	$query->bindValue(":board", $post["board"]);
	$query->bindValue(":number", $post["number"]);
	$query->execute();
	
	$post["file"] = $query->fetch(PDO::FETCH_ASSOC);
	
	if($post["file"]) {
		process_post_file($post["file"]);
	}
	
	$query = $db->prepare("SELECT * FROM threads FORCE INDEX (board__number) WHERE (board = :board AND number = :number)");
	$query->bindValue(":board", $post["board"]);
	$query->bindValue(":number", $post["thread"]);
	$query->execute();
	
	$thread = $query->fetch(PDO::FETCH_ASSOC);
	
	if(!$thread) {
		exit("missing thread");
	}
	
	process_thread($thread);
	
	switch($action) {
		case "delete":
			if(!user_has_permission($user, $board, (is_post_op($post) ? "delete_thread" : "delete_reply"))) {
				exit("no permission");
			}
			
			$db->beginTransaction();
			
			$query = $db->prepare("UPDATE posts SET deleted = :deleted WHERE (id = :id)");
			$query->bindValue(":id", $post["id"]);
			$query->bindValue(":deleted", (int)!$post["deleted"]);
			$query->execute();
			
			update_time_modified_post($post, $time);
			update_time_modified_thread($thread, $time);
			
			$db->commit();
			
			break;
		
		case "filespoiler":
			if(!user_has_permission($user, $board, "set_file_spoiler")) {
				exit("no permission");
			}
			
			if(!$post["file"]) {
				exit("no file");
			}
			
			$db->beginTransaction();
			
			$query = $db->prepare("UPDATE files SET spoiler = :spoiler WHERE (id = :id)");
			$query->bindValue(":id", $post["file"]["id"]);
			$query->bindValue(":spoiler", (int)!$post["file"]["spoiler"]);
			$query->execute();
			
			update_time_modified_post($post, $time);
			update_time_modified_thread($thread, $time);
			
			$db->commit();
			
			break;
		
		case "bump":
			if(!user_has_permission($user, $board, "bump_thread_manually")) {
				exit("no permission");
			}
			
			if($thread["number"] !== $post["number"]) {
				exit("not op");
			}
			
			if($thread["time_bumped"] < $time) {
				$query = $db->prepare("UPDATE threads SET time_bumped = :time_bumped WHERE (id = :id)");
				$query->bindValue(":id", $thread["id"]);
				$query->bindValue(":time_bumped", $time);
				$query->execute();
			}
			
			break;
		
		case "sticky":
		case "lock":
		case "bumplock":
			$act_verb = $action;
			
			$act_adjective = [
				"sticky" => "stickied",
				"lock" => "locked",
				"bumplock" => "bumplocked"
			][$act_verb];
			
			if(!user_has_permission($user, $board, ("set_thread_" . $act_verb))) {
				exit("no permission");
			}
			
			if(!is_post_op($post)) {
				exit("not op");
			}
			
			$db->beginTransaction();
			
			$query = $db->prepare("UPDATE threads SET {$act_adjective} = :{$act_adjective} WHERE (id = :id)");
			$query->bindValue(":id", $thread["id"]);
			$query->bindValue(":{$act_adjective}", ($thread["state"][$act_adjective] ? 0 : 1));
			$query->execute();
			
			update_time_modified_thread($thread, $time);
			
			$db->commit();
			
			break;
		
		default:
			exit("unknown action");
	}
	
	echo "<script> window.close(); </script>";
	
	return true;
}

function route_controlpanel_mod_editpost() {
	global $user;
	global $db;
	global $twig;
	global $httpvars;
	
	twig_init();
	
	$time = get_time_msec();
	
	$postinfo = parse_post_fullno($httpvars["get"]["post"]);
	
	if(!$postinfo) {
		exit("invalid post");
	}
	
	$board = get_board_by_uri($postinfo["board"]);
	
	if(!$board) {
		exit("board doesn't exist");
	}
	
	$query = $db->prepare("SELECT * FROM posts FORCE INDEX (board__number) WHERE (board = :board AND number = :number)");
	$query->bindValue(":board", $board["uri"]);
	$query->bindValue(":number", $postinfo["number"]);
	$query->execute();
	
	$post = $query->fetch(PDO::FETCH_ASSOC);
	
	if(!$post) {
		exit("post doesn't exist");
	}
	
	$query = $db->prepare("SELECT * FROM threads FORCE INDEX (board__number) WHERE (board = :board AND number = :number)");
	$query->bindValue(":board", $post["board"]);
	$query->bindValue(":number", $post["thread"]);
	$query->execute();
	
	$thread = $query->fetch(PDO::FETCH_ASSOC);
	
	if(!$thread) {
		exit("missing thread");
	}
	
	if (http_param_bool($httpvars["post"]["submit"])) {
		$name = $httpvars["post"]["name"];
		$subject = $httpvars["post"]["subject"];
		$comment = $httpvars["post"]["comment"];
		
		$name = (strlen($name) ? $name : null);
		$subject = (strlen($subject) ? $subject : null);
		$comment = (strlen($comment) ? $comment : null);
		
		$comment = (strlen($comment) ? standardize_newlines($comment) : $comment);
		
		# check if subject in reply is allowed
		if(strlen($subject) && ($post["thread"] !== $post["number"]) && !$board["config"]["allow_reply_subject"]) {
			exit("error: subject is not allowed in reply");
		}
		
		$max_length_name = min(CONSTANTS::MAXLEN_POST_NAME, $board["config"]["max_length_name"]);
		$max_length_subject = min(CONSTANTS::MAXLEN_POST_SUBJECT, $board["config"]["max_length_subject"]);
		$max_length_comment = min(CONSTANTS::MAXLEN_POST_COMMENT, $board["config"]["max_length_comment"]);
		
		# check argument lengths against limits
		if(strlen($name) > $max_length_name) {
			exit("error: name too long");
		}
		
		if(strlen($subject) > $max_length_subject) {
			exit("error: subject too long");
		}
		
		if(strlen($comment) > $max_length_comment) {
			exit("error: comment too long");
		}
		
		$db->beginTransaction();
		
		$query = $db->prepare("UPDATE posts SET name = :name, subject = :subject, comment = :comment WHERE (id = :id)");
		$query->bindValue(":name", $name);
		$query->bindValue(":subject", $subject);
		$query->bindValue(":comment", $comment);
		$query->bindValue(":id", $post["id"]);
		$query->execute();
		
		update_time_modified_post($post, $time);
		update_time_modified_thread($thread, $time);
		
		$db->commit();
		
		echo "<script> location = location.href; </script>";
	} else {
		$twig->display("controlpanel_mod_editpost.html", [
			"board" => $board,
			"post" => $post
		]);
	}
	
	return true;
}

function route_controlpanel_mod_addban() {
	global $user;
	global $db;
	global $twig;
	global $httpvars;
	
	if(!user_has_permission($user, null, "add_ban")) {
		exit("no permission");
	}
	
	twig_init();
	
	$time = get_time_msec();
	
	$post = null;
	
	$ip = $httpvars["get"]["ip"];
	$postinfo = $httpvars["get"]["post"];
	
	$ip = (strlen($ip) ? $ip : null);
	
	if(strlen($postinfo)) {
		$postinfo = parse_post_fullno($postinfo);
		
		if(!$postinfo) {
			exit("invalid post");
		}
		
		$board = get_board_by_uri($postinfo["board"]);
		
		if(!$board) {
			exit("board doesn't exist");
		}
		
		$query = $db->prepare("SELECT * FROM posts FORCE INDEX (board__number) WHERE (board = :board AND number = :number)");
		$query->bindValue(":board", $board["uri"]);
		$query->bindValue(":number", $postinfo["number"]);
		$query->execute();
		
		$post = $query->fetch(PDO::FETCH_ASSOC);
		
		if(!$post) {
			exit("post doesn't exist");
		}
		
		if(!$post["ip"]) {
			exit("post does not have an associated ip");
		}
		
		$ip = $post["ip"];
	}
	
	if (http_param_bool($httpvars["post"]["submit"])) {
		$data = [
			"ip" => $ip,
			"type" => $httpvars["post"]["type"],
			"length" => (intval($httpvars["post"]["length"]) * 60 * 1000),
			"reason" => $httpvars["post"]["reason"],
			"postpublicban" => http_param_bool($httpvars["post"]["postpublicban"]),
			"postdelete" => http_param_bool($httpvars["post"]["postdelete"]),
			"postdeletefile" => http_param_bool($httpvars["post"]["postdeletefile"])
		];
		
		if(!$data["ip"]) {
			exit("invalid ip");
		}
		
		if(!strlen($data["type"]) || !in_array($data["type"], ["warn", "bantemp", "banperm"])) {
			exit("invalid ban type");
		}
		
		if($data["type"] === "bantemp" && !$data["length"]) {
			exit("invalid length for temporary ban");
		}
		
		$db->beginTransaction();
		
		$query = $db->prepare("INSERT INTO bans (ip,time,creator,post,type,length,reason,seen) VALUES (:ip,:time,:creator,:post,:type,:length,:reason,:seen)");
		$query->bindValue(":ip", $data["ip"]);
		$query->bindValue(":time", $time);
		$query->bindValue(":creator", ($user["id"] ? $user["id"] : null));
		$query->bindValue(":post", ($post ? $post["id"] : null));
		$query->bindValue(":type", $data["type"]);
		$query->bindValue(":length", ($data["type"] === "bantemp" ? $data["length"] : null));
		$query->bindValue(":reason", (strlen($data["reason"]) ? $data["reason"] : null));
		$query->bindValue(":seen", (int)false);
		$query->execute();
		
		if($post) {
			$modified = false;
			
			$query = $db->prepare("SELECT * FROM threads FORCE INDEX (board__number) WHERE (board = :board AND number = :number)");
			$query->bindValue(":board", $post["board"]);
			$query->bindValue(":number", ($post["thread"] ? $post["thread"] : $post["number"]));
			$query->execute();
			
			$thread = $query->fetch(PDO::FETCH_ASSOC);
			
			if($thread) {
				if($data["postpublicban"]) {
					$query = $db->prepare("UPDATE posts SET public_ban = 1 WHERE (id = :id AND public_ban != 1)");
					$query->bindValue(":id", $post["id"]);
					$query->execute();
					
					if($query->rowCount() > 0) {
						$modified = true;
					}
				}
				
				if($data["postdelete"]) {
					$query = $db->prepare("UPDATE posts SET deleted = 1 WHERE (id = :id AND deleted != 1)");
					$query->bindValue(":id", $post["id"]);
					$query->execute();
					
					if($query->rowCount() > 0) {
						$modified = true;
					}
				}
				
				if($data["postdeletefile"]) {
					$query = $db->prepare("UPDATE files FORCE INDEX (board__number) SET deleted = 1 WHERE ((board = :board AND number = :number) AND deleted != 1)");
					$query->bindValue(":board", $post["board"]);
					$query->bindValue(":number", $post["number"]);
					$query->execute();
					
					if($query->rowCount() > 0) {
						$modified = true;
					}
				}
			}
			
			if($modified) {
				update_time_modified_post($post, $time);
				update_time_modified_thread($thread, $time);
			}
		}
		
		$db->commit();
		
		echo "<span>ban successfully created</span>";
		echo "<script> window.close(); </script>";
	} else {
		$twig->display("controlpanel_mod_addban.html", [
			"ip" => $ip,
			"post" => $post
		]);
	}
	
	return true;
}



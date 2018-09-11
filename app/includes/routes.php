<?php

function route($path) {
	# Test GET routes
	if($_GET["page"] === "index") return route_index();
	if($_GET["page"] === "file" && strlen($_GET["file"])) return route_files("src", null, $_GET["file"]);
	if($_GET["page"] === "board" && strlen($_GET["board"])) return route_board(get_board_by_uri($_GET["board"]), "/");
	
	
	if($path === "/") {
		return route_index();
	}
	
	if($path === "/test") {
		return route_test();
	}
	
	if($path === "/favicon.ico") {
		return route_favicon();
	}
	
	if($path === "/robots.txt") {
		return route_robots();
	}
	
	if($path === "/post" || $path === "/postsubmit") {
		return route_post();
	}
	
	if($path === "/report") {
		return route_report();
	}
	
	if($path === "/delete") {
		return route_delete();
	}
	
	if($path === "/banned" || $path === "/warning") {
		return route_banned();
	}
	
	if(preg_match("/^\/cp(\/(?:.*)|)$/", $path, $matches)) {
		return route_controlpanel($matches[1]);
	}
	
	if(preg_match("/^\/files\/(.+?)$/", $path, $matches)) {
		return route_files($matches[1]);
	}
	
	if(preg_match(("/^\/" . CONSTANTS::REGEX_BOARD_URI . "(\/(?:.*)|)$/"), $path, $matches)) {
		if($board = get_board_by_uri($matches[1])) {
			return route_board($board, $matches[2]);
		}
	}
	
	return false;
}

function route_test() {
	global $httpvars;
	global $db;
	
	b4k::set_header("content-type", "text/plain");
	
	switch($httpvars["get"]["act"]) {
		case "clearboard":
			$board = $httpvars["get"]["board"];
			$board = (strlen($board) ? $board : null);
			
			if($board !== null && get_board_by_uri($board)) {
				$db->beginTransaction();
				$db->query("DELETE FROM threads WHERE (board = " . $db->quote($board) . ")");
				$db->query("DELETE FROM posts WHERE (board = " . $db->quote($board) . ")");
				$db->query("DELETE FROM files WHERE (board = " . $db->quote($board) . ")");
				$db->query("UPDATE boards SET post_number = 0 WHERE (uri = " . $db->quote($board) . ")");
				$db->commit();
				
				echo ">> It's all gone." . NEWLINE;
			} else {
				echo ">> I couldn't find that board, sorry." . NEWLINE;
			}
			
			break;
		
		case null:
			echo ">> I don't know what you expect me to do." . NEWLINE;
			break;
		
		default:
			echo ">> I don't know how to do that." . NEWLINE;
			break;
	}
	
	return true;
}

function route_index() {
	global $twig;
	
	twig_init();
	
	#$twig->display("index.html", []);
	redirect(url("/test/"));
	
	return true;
}

function route_favicon() {
	redirect(url(get_favicon(), true));
	
	return true;
}

function route_robots() {
	$text = "";
	
	$text .= "User-agent: *" . NEWLINE;
	$text .= "Disallow: /" . NEWLINE;
	
	b4k::set_header("content-type", "text/plain");
	b4k::set_header("cache-control", "no-cache");
	
	echo $text;
	
	return true;
}

function route_report() {
	return true;
}

function route_delete() {
	return true;
}

function route_files($path) {
	global $basepath;
	
	$info = parse_file_request($path);
	
	if($info !== null) {
		$path = ($basepath . "/data/files/" . get_file_path_local($info));
		
		if(is_file($path)) {
			b4k::set_header("content-disposition", "inline");
			b4k::set_header("cache-control", "public");
			b4k::unset_header("expires");
			b4k::unset_header("pragma");
			
			b4k::send_response_file($path);
			
			return true;
		}
	}
	
	return false;
}

function route_banned() {
	global $db;
	global $twig;
	global $httpvars;
	
	twig_init();
	
	$ip = get_remote_addr();
	$time = get_time_msec();
	
	$bans = get_bans($time, $ip, true, false);
	
	if(http_param_bool($httpvars["get"]["json"])) {
		respond_json([
			"ip" => $ip,
			"banned" => (count($bans) > 0)
		]);
		
		exit;
	}
	
	$db->beginTransaction();
	
	foreach($bans as &$ban) {
		$ban["viewdata"] = [
			"ip" => $ban["ip"],
			"reason" => (strlen($ban["reason"]) ? render_ban_reason($ban["reason"]) : null),
			"time_created" => [
				"attr" => date(DATE_W3C, ($ban["time"] / 1000)),
				"text" => date(CONSTANTS::DATE_FORMAT_BANNED, ($ban["time"] / 1000))
			],
			"time_expires" => ($ban["length"] ? [
				"attr" => date(DATE_W3C, (($ban["time"] + $ban["length"]) / 1000)),
				"text" => date(CONSTANTS::DATE_FORMAT_BANNED, (($ban["time"] + $ban["length"]) / 1000))
			] : null)
		];
		
		if($ban["post"]) {
			$query = $db->prepare("SELECT * FROM posts WHERE (id = :id)");
			$query->bindValue(":id", $ban["post"]);
			$query->execute();
			
			$post = $query->fetch(PDO::FETCH_ASSOC);
			
			if($post) {
				process_post($post);
				load_post_file($post);
				
				$ban["viewdata"]["post"] = [
					"data" => $post,
					"html" => render_post($post, null, false, false, true)
				];
			}
		}
		
		if(!$ban["seen"]) {
			$query = $db->prepare("UPDATE bans SET seen = :seen WHERE (id = :id)");
			$query->bindValue(":id", $ban["id"]);
			$query->bindValue(":seen", 1);
			$query->execute();
		}
	}
	
	$db->commit();
	
	$twig->display("banned.html", [
		"ip" => $ip,
		"banned" => (count($bans) > 0),
		"bans" => $bans
	]);
	
	return true;
}

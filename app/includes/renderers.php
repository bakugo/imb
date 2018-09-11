<?php

function render_post($post, $thread = null, $in_board = false, $in_thread = false, $force_reply_thumb = false) {
	global $config;
	global $user;
	
	$board = get_board_by_uri($post["board"]);
	$contained = (!$in_board || !is_post_op($post));
	$link = ((!$in_thread ? ("/" . urlenc($post["board"]) ."/thread/{$post["thread"]}") : "") . ((!is_post_op($post) || $in_thread) ? "#p{$post["number"]}" : ""));
	$scrolltarget = ($in_board ? "p{$post["number"]}" : ("p-" . htmlenc($post["board"]) . "-{$post["number"]}"));
	
	$capcode = call_user_func(function() use($post, $board) {
		global $config;
		
		if(strlen($post["capcode"])) {
			$capcodes = $config["users"]["capcodes"];
			
			$capcode = $capcodes[$post["capcode"]];
			
			if($capcode) {
				return $capcode;
			}
			
			return [
				"title" => ucfirst($post["capcode"]),
				"desc" => "This user is part of the staff.",
				"color" => null
			];
		}
		
		return null;
	});
	
	$dataset = call_user_func(function() use($post, $board) {
		$data = [];
		
		$data["board"] = $post["board"];
		$data["number"] = $post["number"];
		$data["thread"] = $post["thread"];
		$data["time-posted"] = $post["time_posted"];
		$data["time-modified"] = $post["time_modified"];
		
		if(strlen($post["name"]) || !strlen($post["tripcode"])) {
			$data["name"] = (strlen($post["name"]) ? $post["name"] : $board["config"]["anonymous_name"]);
		}
		
		if(strlen($post["tripcode"])) {
			$data["tripcode"] = $post["tripcode"];
		}
		
		if(strlen($post["capcode"])) {
			$data["capcode"] = $post["capcode"];
		}
		
		if(strlen($post["subject"])) {
			$data["subject"] = $post["subject"];
		}
		
		return $data;
	});
	
	$html_file = render_post_file($post, $board, null, false, $force_reply_thumb);
	$html_modcontrols = render_post_modcontrols($post, $board, false);
	
	$html = "";
	
	$html .= (
		"<div" .
		" id=\"post-" . htmlenc(sanitize_html_id($post["board"])) . "-{$post["number"]}\"" .
		" class=\"post" . (is_post_op($post) ? " op" : " reply") . ($contained ? " contained" : "") . ($post["file"] ? " has-file" : "") . "\"" .
		" " . build_dataset($dataset) .
		">"
	);
	
	$html .= "<div class=\"post-interior\">";
	
	$html .= "<a class=\"scroll-target\" id=\"{$scrolltarget}\"></a>";
	
	if(strlen($html_file) && !$contained) {
		$html .= $html_file;
	}
	
	$html .= "<div class=\"info\">";
	
	if(!$in_board) {
		$html .= ("<span class=\"board-tag\">/" . htmlenc($post["board"]) . "/</span> ");
		
		if(is_post_op($post)) {
			$html .= "<span class=\"op-tag\">(OP)</span> ";
		}
	}
	
	if(strlen($post["subject"])) {
		$html .= ("<span class=\"subject\">" . htmlenc($post["subject"]) . "</span> ");
	}
	
	$html .= "<span class=\"author\">";
	
	$html .= "<span class=\"user-ids\">";
	
	if(strlen($post["name"]) || !strlen($post["tripcode"])) {
		$html .= ("<span class=\"name\">" . (strlen($post["name"]) ? htmlenc($post["name"]) : $board["config"]["anonymous_name"]) . "</span>");
	}
	
	if(strlen($post["name"]) && strlen($post["tripcode"])) {
		$html .= " ";
	}
	
	if(strlen($post["tripcode"])) {
		$html .= ("<span class=\"tripcode\">" . htmlenc($post["tripcode"]) . "</span>");
	}
	
	if($capcode) {
		$html .= (
			" " .
			"<span class=\"capcode\"" .
			" " . build_dataset(["capcode" => $post["capcode"], "capcode-title" => $capcode["title"]]) .
			(strlen($capcode["desc"]) ? (" title=\"" . htmlenc($capcode["desc"]) . "\"") : "") .
			(strlen($capcode["color"]) ? (" style=\"color: " . htmlenc($capcode["color"]) . ";\"") : "") .
			">" .
			htmlenc("## {$capcode["title"]}") .
			"</span>"
		);
	}
	
	$html .= "</span>";
	
	if($post["ip"] && $board["config"]["poster_country_flags"]["enabled"] && (!strlen($post["capcode"]) || !$board["config"]["poster_country_flags"]["hide_if_capcoded"])) {
		$country = geoip_country($post["ip"]);
		
		if($country !== null) {
			$html .= (
				" " .
				"<span class=\"flag\" data-county=\"" . htmlenc(strtolower($country["code"])) . "\" title=\"" . htmlenc($country["name"]) . "\">" . 
				"<span class=\"flag-icon flag-icon-" . htmlenc(strtolower($country["code"])) . "\" alt=\"(" . htmlenc(strtoupper($country["code"])) . ")\">" .
				"</span>" .
				"</span>"
			);
		}
	}
	
	$html .= render_post_ids($post, $board);
	
	$html .= "</span>";
	
	$html .= (
		" " .
		"<span class=\"time\">" .
		"<time datetime=\"" . htmlenc(date(DATE_W3C, ($post["time_posted"] / 1000))) . "\">" .
		htmlenc(date(CONSTANTS::DATE_FORMAT_POST, ($post["time_posted"] / 1000))) .
		"</time>" .
		"</span>"
	);
	
	$html .= (
		" " .
		"<span class=\"number\">" .
		"<a class=\"number-link\" href=\"" . url($link) . "\" title=\"Highlight this post\">No.</a>" .
		"<a class=\"reply-link\" href=\"" . CONSTANTS::LINK_NULL_URL . "\" title=\"Quote this post\">{$post["number"]}</a>" .
		"</span>"
	);
	
	$html .= render_post_icons($post, $board, $thread);
	
	if((is_post_op($post) && $in_board && !$in_thread) || !$in_board) {
		$html .= (" <span class=\"view-link\">[<a href=\"" . url($link) . "\">View</a>]</span>");
	}
	
	$html .= "</div>";
	
	if(strlen($html_file) && $contained) {
		$html .= $html_file;
	}
	
	if(strlen($html_modcontrols) && $contained) {
		$html .= $html_modcontrols;
	}
	
	$html .= "<div class=\"content\">";
	
	if(strlen($post["comment"])) {
		$html .= ("<div class=\"comment\">" . render_post_comment($post) . "</div>");
	}
	
	if(!strlen($post["comment"]) && !$post["file"]) {
		$html .= "<div class=\"no-content\"><em>(no content)</em></div>";
	}
	
	if($post["public_ban"]) {
		$html .= "<div class=\"public-ban\">(USER WAS BANNED FOR THIS POST)</div>";
	}
	
	$html .= "</div>";
	
	if(strlen($html_modcontrols) && !$contained) {
		$html .= $html_modcontrols;
	}
	
	$html .= "</div>";
	
	$html .= "</div>";
	
	return $html;
}

function render_post_ids($post, $board) {
	global $user;
	
	$html = "";
	
	$ids = [];
	
	if($post["ip"] && user_has_permission($user, $board, "see_poster_info")) {
		if(user_has_permission($user, $board, "see_poster_ips")) {
			$ids[] = ("IP: <a class=\"ip-address\" href=\"" . url("/cp/mod/poster?ip={$post["ip"]}") . "\">" . htmlenc($post["ip"]) . "</a>");
		}
		
		#$ids[] = ("MID: <a class=\"mod-id\" href=\"" . url("/cp/mod/poster?post=" . get_post_fullno($post, true)) . "\">" . gen_poster_modid($post, $board) . "</a>");
	}
	
	if($post["ip"] && $board["config"]["poster_public_ids"]["enabled"] && (!strlen($post["capcode"]) || !$board["config"]["poster_public_ids"]["hide_if_capcoded"])) {
		$ids[] = ("ID: <span class=\"public-id\">" . gen_poster_publicid($post, $board) . "</span>");
	}
	
	if($post["user"] && user_has_permission($user, $board, "see_poster_users")) {
		$ids[] = ("USR: <span class=\"user-name\">" . ((get_user_by_id($post["user"]) !== null) ? htmlenc(get_user_by_id($post["user"])["username"]) : "<em>unknown</em>") . "</span>");
	}
	
	if(count($ids)) {
		$html = (" <span class=\"internal-ids\">(" . implode(", ", $ids) . ")</span>");
	}
	
	return $html;
}

function render_post_icons($post, $board, $thread = null) {
	global $user;
	
	$html = "";
	
	if($post) {
		if($post["sage"] && user_has_permission($user, $board, "see_post_sage")) {
			$html .= "<i class=\"icon-sage fa fa-arrow-circle-down\" title=\"Sage\"></i>";
		}
		
		if($post["shadow"] && user_has_permission($user, $board, "see_shadow_posts")) {
			$html .= "<i class=\"icon-shadow fa fa-eye-slash\" title=\"Shadow\"></i>";
		}
		
		if($post["deleted"] && user_has_permission($user, $board, "see_deleted_posts")) {
			$html .= "<i class=\"icon-deleted fa fa-trash-o\" title=\"Deleted\"></i>";
		}
		
		if($post["file"] && $post["file"]["deleted"]) {
			$html .= "<i class=\"icon-file-deleted fa fa-image-o\" title=\"File Deleted\"></i>";
		}
	}
	
	if(is_post_op($post) && $thread && $thread["state"]) {
		if($thread["state"]["stickied"]) {
			$html .= "<i class=\"icon-stickied fa fa-thumb-tack\" title=\"Stickied\"></i>";
		}
		
		if($thread["state"]["locked"]) {
			$html .= "<i class=\"icon-locked fa fa-lock\" title=\"Locked\"></i>";
		}
		
		if($thread["state"]["bumplocked"] && user_has_permission($user, $board, "see_thread_bumplock")) {
			$html .= "<i class=\"icon-bumplocked fa fa-anchor\" title=\"Bumplocked\"></i>";
		}
		
		if($thread["state"]["archived"]) {
			$html .= "<i class=\"icon-archived fa fa-archive\" title=\"Archived\"></i>";
		}
	}
	
	if(strlen($html)) {
		$html = " <span class=\"icons\">{$html}</span>";
	}
	
	return $html;
}

function render_post_modcontrols($post, $board, $in_catalog = false) {
	global $user;
	
	$fullno = get_post_fullno($post, true);
	
	$html = "";
	
	if(user_has_permission($user, $board, ("delete_" . (is_post_op($post) ? "thread" : "reply")))) {
		$html .= ("<a href=\"" . url("/cp/mod/post-action?post={$fullno}&act=delete") . "\" target=\"_blank\" title=\"Delete Post\">[DELE]</a>");
	}
	
	if(!$in_catalog) {
		if(user_has_permission($user, $board, "edit_post")) {
			$html .= ("<a href=\"" . url("/cp/mod/edit-post?post={$fullno}") . "\" target=\"_blank\" title=\"Edit Post\">[EDIT]</a>");
		}
	}
	
	if(user_has_permission($user, $board, "add_ban")) {
		$html .= ("<a href=\"" . url("/cp/mod/add-ban?post={$fullno}") . "\" target=\"_blank\" title=\"Ban Poster\">[BAN]</a>");
	}
	
	if($post["file"]) {
		if(!$in_catalog) {
			if(user_has_permission($user, $board, "set_file_spoiler")) {
				$html .= ("<a href=\"" . url("/cp/mod/post-action?post={$fullno}&act=filespoiler") . "\" target=\"_blank\" title=\"Spoiler File\">[SPOIL]</a>");
			}
		}
	}
	
	if(is_post_op($post)) {
		if(user_has_permission($user, $board, "bump_thread_manually")) {
			$html .= ("<a href=\"" . url("/cp/mod/post-action?post={$fullno}&act=bump") . "\" target=\"_blank\" title=\"Bump Thread\">[BUMP]</a>");
		}
		
		if(user_has_permission($user, $board, "set_thread_sticky")) {
			$html .= ("<a href=\"" . url("/cp/mod/post-action?post={$fullno}&act=sticky") . "\" target=\"_blank\" title=\"Sticky Thread\">[STICK]</a>");
		}
		
		if(user_has_permission($user, $board, "set_thread_lock")) {
			$html .= ("<a href=\"" . url("/cp/mod/post-action?post={$fullno}&act=lock") . "\" target=\"_blank\" title=\"Lock Thread\">[LOCK]</a>");
		}
		
		if(user_has_permission($user, $board, "set_thread_bumplock")) {
			$html .= ("<a href=\"" . url("/cp/mod/post-action?post={$fullno}&act=bumplock") . "\" target=\"_blank\" title=\"Bumplock Thread\">[SAGE]</a>");
		}
	}
	
	if(strlen($html)) {
		$html = "<div class=\"mod-controls\">{$html}</div>";
		$html = ($in_catalog ? str_replace("</a><a", "</a><wbr><a", $html) : $html);
	}
	
	return $html;
}

function render_post_file($post, $board, $link = null, $in_catalog = false, $force_reply_thumb = false) {
	global $basepath;
	
	$fnmaxlen = CONSTANTS::MAXLEN_FILE_FILENAME_SHOWN;
	$lazyload = $board["config"]["thumbnail_lazyload"];
	
	$file = $post["file"];
	$file_type = get_file_type($file["extension"], $board);
	
	$file_absent = (!$file);
	$file_deleted = ($file && $file["deleted"]);
	$file_spoiler = ($file && $file["spoiler"]);
	
	if($file_absent && !$in_catalog && !$board["config"]["display_absent_file"]) {
		return "";
	}
	
	$file_class = "standard";
	$file_class = ($file_absent ? "absent" : $file_class);
	$file_class = ($file_deleted ? "deleted" : $file_class);
	$file_class = ($file_spoiler ? "spoiler" : $file_class);
	
	$url_src = null;
	$url_thumb = null;
	
	if((!$file_absent && !$file_deleted)) {
		$url_src = url(get_file_path_web([
			"hash" => $file["md5"],
			"extension" => $file["extension"],
			"is_thumb" => false,
			"is_thumb_op" => false
		]));
		
		if($file_type["thumbnail"] === null) {
			$url_thumb = url(get_file_path_web([
				"hash" => $file["md5"],
				"extension" => $file["extension"],
				"is_thumb" => true,
				"is_thumb_op" => is_post_op($post)
			]));
			
			$file_class .= " thumbnailed";
		} else {
			$url_thumb = (strlen($file_type["thumbnail"]) ? "/assets-custom/file-type-thumbs/{$file_type["thumbnail"]}.png" : null);
			$url_thumb = (($url_thumb !== null && is_file("{$basepath}/web{$url_thumb}")) ? $url_thumb : "/assets/file.png");
			$url_thumb = url($url_thumb, true);
			
			$lazyload = false;
		}
	}
	
	$dimensions_src = ((!$file_absent && $file_type["thumbnail"] === null) ? $file["dimensions"] : null);
	$dimensions_thumb = get_thumb_dimensions($dimensions_src, (!$force_reply_thumb && is_post_op($post)), $board, $in_catalog, ($file_type["thumbnail"] !== null || $file_absent || $file_deleted || $file_spoiler));
	
	
	$html = "";
	
	$html .= (
		"<div" .
		" class=\"file {$file_class}\"" .
		((!$file_absent && !$file_deleted) ? (" " . build_dataset(["md5" => $file["md5"], "extension" => $file["extension"], "filesize" => $file["filesize"]])) : "") .
		">"
	);
	
	if(!$in_catalog && !$file_absent && !$file_deleted) {
		$filename = $file["filename"];
		$filename_full = null;
		$filename_short = null;
		
		$filename_full = htmlenc("{$filename}.{$file[extension]}");
		
		if(mb_strlen($filename) > $fnmaxlen) {
			$filename_short = (htmlenc(mb_substr($filename, 0, $fnmaxlen)) . "(&hellip;)." . htmlenc($file["extension"]));
		}
		
		if($file_spoiler) {
			$filename_short = "Spoiler File";
		}
		
		$html .= (
			"<div class=\"file-info\">" .
			"File:" .
			(" <a class=\"file-link\" href=\"{$url_src}\" target=\"_blank\"" . (strlen($filename_short) ? (" title=\"{$filename_full}\"") : "") . ">" . (strlen($filename_short) ? $filename_short : $filename_full) . "</a>") .
			(" (" . strtoupper($file["extension"]) . ", " . htmlenc(b4k::format_bytes($file["filesize"], 2, true)) . ($dimensions_src ? ", {$dimensions_src["width"]}&times;{$dimensions_src["height"]}" : "") . ($file["spoiler"] ? ", Spoiler" : "") . ")") .
			"</div>"
		);
	}
	
	if(!$file_absent || $in_catalog) {
		$html .= ("<a href=\"" . ($in_catalog ? url($link) : $url_src) . "\"" . (!$in_catalog ? " target=\"_blank\"" : "") . ">");
	}
	
	if($file_spoiler || $file_absent || $file_deleted) {
		$html .= (
			"<div" .
			" class=\"thumb\"" .
			" style=\"width: {$dimensions_thumb["width"]}px; height: {$dimensions_thumb["height"]}px;\"" .
			"></div>"
		);
	} else {
		if($lazyload) {
			$html .=  (
				"<img" .
				" class=\"thumb lazyload\"" .
				" src=\"" . CONSTANTS::IMAGE_NULL_URL . "\" data-src=\"{$url_thumb}\"" .
				" alt=\"" . htmlenc("{$file["filename"]}.{$file[extension]}") . "\"" .
				" style=\"width: {$dimensions_thumb["width"]}px; height: {$dimensions_thumb["height"]}px;\"" .
				" draggable=\"false\"" .
				">" .
				
				"<noscript>" . 
					"<img" .
					" class=\"thumb\"" .
					" src=\"{$url_thumb}\"" .
					" alt=\"" . htmlenc("{$file["filename"]}.{$file[extension]}") . "\"" .
					" style=\"width: {$dimensions_thumb["width"]}px; height: {$dimensions_thumb["height"]}px;\"" .
					" draggable=\"false\"" .
					">" .
				"</noscript>"
			);
		} else {
			$html .=  (
				"<img" .
				" class=\"thumb\"" .
				" src=\"{$url_thumb}\"" .
				" alt=\"" . htmlenc("{$file["filename"]}.{$file[extension]}") . "\"" .
				" style=\"width: {$dimensions_thumb["width"]}px; height: {$dimensions_thumb["height"]}px;\"" .
				" draggable=\"false\"" .
				">"
			);
		}
	}
	
	if(!$file_absent || $in_catalog) {
		$html .= "</a>";
	}
	
	$html .= "</div>";
	
	return $html;
}

function render_thread($thread, $in_index, $show_omitted_replies = false, $container_only = false) {
	$link = "/{$thread["board"]}/thread/{$thread["number"]}";
	
	$dataset = call_user_func(function() use($thread) {
		$data = [];
		
		$data["board"] = $thread["board"];
		$data["number"] = $thread["number"];
		$data["time-posted"] = $thread["post"]["time_posted"];
		$data["time-bumped"] = $thread["time_bumped"];
		$data["time-modified"] = $thread["time_modified"];
		
		return $data;
	});
	
	$posts = [];
	
	if($thread["post"]) {
		$posts[] = $thread["post"];
	}
	
	if($thread["replies"]) {
		$posts = array_merge($posts, $thread["replies"]);
	}
	
	$html = "";
	
	$html .= (
		"<div" .
		" id=\"thread-" . htmlenc(sanitize_html_id($thread["board"])) . "-{$thread["number"]}\"" .
		" class=\"thread\"" .
		" " . build_dataset($dataset) .
		">"
	);
	
	if(!$container_only) {
		foreach($posts as $i => $post) {
			if($i === 1 && $show_omitted_replies && $in_index && $thread["stats"]) {
				$omitted_replies_count = (($thread["stats"]["posts"] - 1) - count($thread["replies"]));
				
				if($omitted_replies_count > 0) {
					$html .= (
						"<div class=\"omitted-replies\">" .
						("&bull; {$omitted_replies_count} " . (($omitted_replies_count === 1) ? "reply" : "replies") . " omitted. Click <a href=\"" . url($link) . "\">here</a> to view.") .
						"</div>"
					);
				}
			}
			
			$html .= render_post($post, $thread, true, !$in_index);
		}
	}
	
	$html .= "</div>";
	
	return $html;
}


function render_catalog_thread($thread) {
	$post = $thread["post"];
	$stats = $thread["stats"];
	
	$board = get_board_by_uri($post["board"]);
	
	$link = ("/" . urlenc($post["board"]) . "/thread/{$post["number"]}");
	
	$dataset = call_user_func(function() use($thread, $post, $board) {
		$data = [];
		
		$data["board"] = $post["board"];
		$data["number"] = $post["number"];
		$data["time-posted"] = $post["time_posted"];
		$data["time-bumped"] = $thread["time_bumped"];
		$data["time-modified"] = $thread["time_modified"];
		
		if(strlen($post["name"]) || !strlen($post["tripcode"])) {
			$data["name"] = (strlen($post["name"]) ? $post["name"] : $board["config"]["anonymous_name"]);
		}
		
		if(strlen($post["tripcode"])) {
			$data["tripcode"] = $post["tripcode"];
		}
		
		if(strlen($post["capcode"])) {
			$data["capcode"] = $post["capcode"];
		}
		
		if(strlen($post["subject"])) {
			$data["subject"] = $post["subject"];
		}
		
		return $data;
	});
	
	
	$html = "";
	
	$html .= (
		"<div" .
		" id=\"thread-" . htmlenc(sanitize_html_id($thread["board"])) . "-{$post["number"]}\"" .
		" class=\"catalog-thread\"" .
		" " . build_dataset($dataset) .
		">"
	);
	
	$html .= render_post_file($post, $board, $link, true);
	
	$html .= "<div class=\"info\">";
	
	$html .= "<span class=\"stats\" title=\"Posts: {$stats["posts"]} / Files: {$stats["files"]} / Posters: {$stats["posters"]}\">{$stats["posts"]} / {$stats["files"]} / {$stats["posters"]}</span>";
	
	$html .= render_post_icons($post, $board, $thread);
	
	$html .= "</div>";
	
	$html .= render_post_modcontrols($post, $board, true);
	
	if(strlen($post["subject"])) {
		$html .= ("<div class=\"subject\">" . htmlenc($post["subject"]) . "</div>");
	}
	
	if(strlen($post["comment"])) {
		$html .= ("<div class=\"comment\">" . render_post_comment($post, true) . "</div>");
	}
	
	$html .= "</div>";
	
	return $html;
}

function render_post_comment($post, $less_line_breaks = false) {
	static $comments = [];
	
	$key = serialize([
		$post["board"],
		$post["number"],
		$less_line_breaks
	]);
	
	if($comments[$key] === null) {
		if(strlen($post["comment"])) {
			$comment = $post["comment"];
			
			# trim whitespace
			$comment = trim($comment);
			
			# standardize newlines
			$comment = standardize_newlines($comment);
			
			# some basic replacements
			$comment = str_replace("[post-board]", $post["board"], $comment);
			$comment = str_replace("[post-number]", $post["number"], $comment);
			$comment = str_replace("[post-timestamp]", $post["time_posted"], $comment);
			
			# encode html
			$comment = htmlenc($comment);
			
			# apply emotes
			$comment = call_user_func(function() use($comment) {
				$emotes = [
					["name" => "SourPls", "url" => "https://cdn.betterttv.net/emote/566ca38765dbbdab32ec0560/3x", "style" => "width: 30px; height: 30px;"]
				];
				
				return preg_replace_callback(
					("/(^| )(" . implode("|", array_map(function($emote) { return preg_quote($emote["name"]); }, $emotes)) . ")($| )/m"),
					
					function($matches) use($emotes)  {
						foreach($emotes as $emote) {
							if($emote["name"] == $matches[2]) {
								return (
									$matches[1] .
									("<img src=\"" . htmlenc($emote["url"]) . "\" alt=\"" . htmlenc($emote["name"]) . "\"" . ($emote["style"] ? (" style=\"" . $emote["style"] . "\"") : "") . ">") .
									$matches[3]
								);
							}
						}
						
						return $matches[0];
					},
					
					$comment
				);
			});
			
			# replace some special chars
			#$comment = str_replace("...", "&hellip;", $comment);
			
			# cites
			$comment = render_post_comment_quotelinks($comment, $post);
			
			# bbcode
			$comment = call_user_func(function() use($comment, $post) {
				$parser = new JBBCode\Parser();
				
				$parser->addCodeDefinition(new BBCode_NoParse());
				$parser->addCodeDefinition(new BBCode_Bold());
				$parser->addCodeDefinition(new BBCode_Italic());
				$parser->addCodeDefinition(new BBCode_Strikethrough());
				$parser->addCodeDefinition(new BBCode_Size());
				$parser->addCodeDefinition(new BBCode_Spoiler());
				$parser->addCodeDefinition(new BBCode_ASCII());
				$parser->addCodeDefinition(new BBCode_LinkText("mk-link"));
				
				if($post["enable_html"]) {
					$parser->addCodeDefinition(new BBCode_HTML());
				}
				
				return $parser->parse($comment)->getAsHTML();
			});
			
			# user links
			$comment = call_user_func(function() use($comment) {
				$linkify = new Misd\Linkify\Linkify([
					"attr" => [
						"class" => "mk-link",
						"target" => "_blank",
						"rel" => "nofollow"
					]
				]);
				
				return $linkify->process($comment);
			});
			
			# split comment lines
			$comment = explode(NEWLINE, $comment);
			
			# process each line
			foreach($comment as &$line) {
				# remove whitespace around line
				$line = trim($line);
				
				# greentext
				if(preg_match("/^&gt;/", strip_tags($line)) && !preg_match("/^<(a)\b/", $line)) {
					$line = (substr($line, 0, strpos($line, "&gt;")) . "<span class=\"mk-quote\">" . substr($line, strpos($line, "&gt;")) . "</span>");
				}
			}
			
			# put lines back toguether
			$comment = implode(NEWLINE, $comment);
			
			# remove dummy bbcode tag
			$comment = preg_replace("/\[(null|nothing|dummy|noquote)\]/", "", $comment);
			
			# remove line breaks at the start and end
			$comment = preg_replace("/(^\n+|\n+$)/", "", $comment);
			
			# remove repetitive whitespace
			$comment = preg_replace("/ +/", " ", $comment);
			$comment = preg_replace(("/" . NEWLINE . "{" . ($less_line_breaks ? 2 : 3) . ",}/"), NEWLINE, $comment);
			
			# convert line breaks to tags
			$comment = str_replace(NEWLINE, "<br>", $comment);
			
			# process paragraphs
			#$comment = ("<p>" . implode("</p><p>", explode("<br><br>", $comment)) . "</p>");
			
			# convert literal tags
			$comment = str_replace("<x-lit-space>", " ", $comment);
			$comment = str_replace("<x-lit-tab>", "\t", $comment);
			$comment = str_replace("<x-lit-newline>", NEWLINE, $comment);
			
			$comments[$key] = $comment;
		}
	}
	
	return $comments[$key];
}

function render_post_comment_quotelinks($comment, $post) {
	$parse_board = function($board_uri) {
		global $boards;
		
		$board_uri = htmldec($board_uri);
		
		if(!is_board_uri_valid($board_uri)) {
			return null;
		}
		
		foreach($boards as $board) {
			if($board["uri"] === $board_uri) {
				return $board_uri;
			}
		}
		
		return null;
	};
	
	$parse_number = function($number) {
		$number = (int)$number;
		
		if($number < 1) {
			return null;
		}
		
		return $number;
	};
	
	# local post links
	$comment = preg_replace_callback("/\&gt\;\&gt\;(\d+)/", function($matches) use($post, $parse_number) {
		$number = $matches[1];
		$number = $parse_number($number);
		
		if($number === null) {
			return $matches[0];
		}
		
		return ("<a href=\"" . url("{$post["board"]}/post/{$number}") . "\" class=\"quote-link to-post local\" data-board=\"{$post["board"]}\" data-number=\"{$number}\">&gt;&gt;{$number}</a>");
	}, $comment);
	
	# cross-board post links
	$comment = preg_replace_callback(("/\&gt\;\&gt\;\&gt\;\/(.+?)\/(\d+)/"), function($matches) use($post, $parse_board, $parse_number) {
		$board = $matches[1];
		$board = $parse_board($board);
		
		$number = $matches[2];
		$number = $parse_number($number);
		
		if($board === null || $number === null) {
			return $matches[0];
		}
		
		$board_urlenc = urlenc($board);
		$board_htmlenc = htmlenc($board);
		
		return "<a href=\"" . url("/{$board_urlenc}/post/{$number}") . "\" class=\"quote-link to-post remote\" data-board=\"{$board_htmlenc}\" data-number=\"{$number}\">&gt;&gt;&gt;/{$board_htmlenc}/{$number}</a>";
	}, $comment);
	
	# board links
	$comment = preg_replace_callback(("/\&gt\;\&gt\;\&gt\;\/(.+?)\/(?:(?!\d)|$)/"), function($matches) use($post, $parse_board, $parse_number) {
		$board = $matches[1];
		$board = $parse_board($board);
		
		if($board === null) {
			return $matches[0];
		}
		
		$board_urlenc = urlenc($board);
		$board_htmlenc = htmlenc($board);
		
		return "<a href=\"" . url("/{$board_urlenc}/") . "\" class=\"quote-link to-board\" data-board=\"{$board_htmlenc}\">&gt;&gt;&gt;/{$board_htmlenc}/</a>";
	}, $comment);
	
	return $comment;
}

function render_post_summary($post) {
	$summary = call_user_func(function() use($post) {
		if(strlen($post["subject"])) {
			return $post["subject"];
		}
		
		if(strlen($post["comment"])) {
			$comment = render_post_comment($post);
			$comment = str_replace("<br>", NEWLINE, $comment);
			$comment = strip_tags($comment);
			$comment = preg_replace(("/" . NEWLINE . "+/"), " ", $comment);
			$comment = preg_replace("/ +/", " ", $comment);
			$comment = htmldec($comment);
			
			if(strlen($comment)) {
				return $comment;
			}
		}
		
		if($post["file"] && strlen($post["file"]["filename"])) {
			return ($post["file"]["filename"] . "." . $post["file"]["extension"]);
		}
		
		return "Thread #{$post["number"]}";
	});
	
	$summary = trim($summary);
	
	$summary = call_user_func(function() use($summary) {
		$length = mb_strlen($summary);
		$maxlength = CONSTANTS::MAXLEN_THREAD_SUMMARY;
		
		if($length > $maxlength) {
			$summary = mb_substr($summary, 0, $maxlength);
			
			$lastspace = mb_strrpos($summary, " ");
			
			if(($maxlength - $lastspace) <= 5) {
				$summary = mb_substr($summary, 0, $lastspace);
			}
		}
		
		return $summary;
	});
	
	return $summary;
}

function render_ban_reason($reason) {
	$reason = trim($reason);
	$reason = standardize_newlines($reason);
	$reason = htmlenc($reason);
	
	$reason = call_user_func(function() use($reason) {
		$parser = new JBBCode\Parser();
		
		$parser->addCodeDefinition(new BBCode_NoParse());
		$parser->addCodeDefinition(new BBCode_Italic());
		$parser->addCodeDefinition(new BBCode_Strikethrough());
		$parser->addCodeDefinition(new BBCode_Size());
		$parser->addCodeDefinition(new BBCode_Link());
		$parser->addCodeDefinition(new BBCode_LinkText());
		
		return $parser->parse($reason)->getAsHTML();
	});
	
	$reason = call_user_func(function() use($reason) {
		$linkify = new Misd\Linkify\Linkify([
			"attr" => [
				"target" => "_blank"
			]
		]);
		
		return $linkify->process($reason);
	});
	
	$reason = str_replace(NEWLINE, "<br>", $reason);
	
	return $reason;
}

function render_board_subtitle($board) {
	$subtitle = $board["subtitle"];
	
	$subtitle = trim($subtitle);
	$subtitle = standardize_newlines($subtitle);
	$subtitle = htmlenc($subtitle);
	
	$subtitle = call_user_func(function() use($subtitle) {
		$parser = new JBBCode\Parser();
		
		$parser->addCodeDefinition(new BBCode_NoParse());
		$parser->addCodeDefinition(new BBCode_Bold());
		$parser->addCodeDefinition(new BBCode_Italic());
		$parser->addCodeDefinition(new BBCode_Strikethrough());
		$parser->addCodeDefinition(new BBCode_Size());
		$parser->addCodeDefinition(new BBCode_Link());
		$parser->addCodeDefinition(new BBCode_LinkText());
		
		return $parser->parse($subtitle)->getAsHTML();
	});
	
	$subtitle = str_replace(NEWLINE, "<br>", $subtitle);
	
	return $subtitle;
}

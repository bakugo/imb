<?php

function route_post() {
	global $basepath;
	global $httpvars;
	global $config;
	global $user;
	global $db;
	global $ftp;
	
	# function that handles responses
	$respond = function($success, $message, $info = []) use(&$board) {
		global $httpvars;
		global $db;
		global $twig;
		
		$db->query("UNLOCK TABLES");
		
		if(!$success) {
			$message = "Error: {$message}";
		}
		
		if(get_requested_format() === "json") {
			respond_json([
				"success" => $success,
				"message" => $message,
				"info" => $info
			]);
		} else {
			if($info["banned"]) {
				redirect($info["banned_url"]);
			} else {
				twig_init();
				
				$twig->display("postsubmit.html", [
					"board" => $board,
					"success" => $success,
					"message" => $message,
					"info" => $info
				]);
			}
		}
		
		return true;
	};
	
	# check if a form was submitted
	if(!($_SERVER["REQUEST_METHOD"] === "POST" && http_param_bool($httpvars["post"]["submit"]))) {
		return $respond(false, "You didn't make a post!");
	}
	
	# check for lockdown mode
	if(is_file("{$basepath}/.lockdown")) {
		return $respond(false, "Posting is currently disabled. Try again later!");
	}
	
	# declare variables
	$board = null;
	$thread = null;
	$board_uri = null;
	$thread_number = null;
	$name = null;
	$tripcode = null;
	$subject = null;
	$comment = null;
	$capcode = null;
	$options = null;
	$spoiler_file = null;
	$enable_html = null;
	$start_stickied = null;
	$start_locked = null;
	$file = null;
	$captcha = null;
	
	# get time at the start of the request
	# this is considered the true time a post was submitted at
	$time = get_time_msec();
	
	# get user ip address and useragent
	$ip = get_remote_addr();
	$ua = get_user_agent();
	
	# get post arguments
	$board_uri = $httpvars["post"]["board"];
	$thread_number = $httpvars["post"]["thread"];
	$name = $httpvars["post"]["name"];
	$subject = $httpvars["post"]["subject"];
	$comment = $httpvars["post"]["comment"];
	$capcode = $httpvars["post"]["capcode"];
	$options = $httpvars["post"]["options"];
	$spoiler_file = $httpvars["post"]["spoiler_file"];
	$sage = $httpvars["post"]["sage"];
	$enable_html = $httpvars["post"]["enable_html"];
	$start_stickied = $httpvars["post"]["start_stickied"];
	$start_locked = $httpvars["post"]["start_locked"];
	$file = $httpvars["files"]["file"];
	$captcha = $httpvars["post"]["g-recaptcha-response"];
	
	# process integer arguments
	$thread_number = max((int)$thread_number, 0);
	
	# process boolean arguments
	$spoiler_file = http_param_bool($spoiler_file);
	$sage = http_param_bool($sage);
	$enable_html = http_param_bool($enable_html);
	$start_stickied = http_param_bool($start_stickied);
	$start_locked = http_param_bool($start_locked);
	
	# trim string arguments
	$name = trim($name);
	$subject = trim($subject);
	$comment = trim($comment);
	
	# replace empty arguments with null
	$name = (strlen($name) ? $name : null);
	$subject = (strlen($subject) ? $subject : null);
	$comment = (strlen($comment) ? $comment : null);
	$capcode = (strlen($capcode) ? $capcode : null);
	$file = (($file && $file["error"] !== UPLOAD_ERR_NO_FILE) ? $file : null);
	$captcha = (strlen($captcha) ? $captcha : null);
	
	# extra string argument manipulation
	$comment = (strlen($comment) ? standardize_newlines($comment) : $comment);
	
	# additional data
	$is_reply = ($thread_number > 0);
	
	# parse options string into array
	$options = call_user_func(function() use($options) {
		$arr = [];
		
		if(strlen($options)) {
			$split = explode(" ", $options);
			
			foreach($split as $part) {
				$part = trim($part);
				$part = strtolower($part);
				
				if(!strlen($part)) {
					continue;
				}
				
				$arr[$part] = true;
			}
		}
		
		return $arr;
	});
	
	# generate tripcodes
	call_user_func(function() use(&$name, &$tripcode) {
		if(!strlen($name)) {
			return;
		}
		
		$hash = null;
		$password = null;
		$secure = null;
		
		# find tripcode
		$name = preg_replace_callback("/#(#)?(.+?)$/", function($matches) use(&$hash, &$password, &$secure) {
			$hash = $matches[0];
			$password = $matches[2];
			$secure = ($matches[1] !== "");
			
			return "";
		}, $name);
		
		if($password !== null) {
			# normalize name (again)
			$name = trim($name);
			$name = (strlen($name) ? $name : null);
			
			# generate tripcode
			$tripcode = ($secure ? tripcode_secure($password) : tripcode_normal($password));
			$tripcode = (($secure ? "!!" : "!") . $tripcode);
		}
	});
	
	# check if poster is banned
	if(count(get_bans($time, $ip, true, false)) > 0) {
		return $respond(false, "You are currently banned and cannot post.", [
			"banned" => true,
			"banned_url" => url("/banned")
		]);
	}
	
	# check if a board was specified
	if(!strlen($board_uri)) {
		return $respond(false, "No board specified.");
	}
	
	# lock tables during post processing, important to prevent inconsistencies
	$db->query("LOCK TABLES boards WRITE, posts WRITE, files WRITE, threads WRITE");
	
	# get board
	$board = get_board_by_uri($board_uri);
	
	if(!$board || !user_has_permission($user, $board, "access_board")) {
		return $respond(false, "Specified board does not exist.");
	}
	
	if(!user_has_permission($user, $board, (!$is_reply ? "post_thread" : "post_reply"))) {
		return $respond(false, ("You are not allowed to post a " . (!$is_reply ? "thread" : "reply") . "."));
	}
	
	# handle captcha
	if(!user_has_permission($user, $board, "post_without_captcha")) {
		if(!captcha_is_set_up()) {
			return $respond(false, "Captcha is not set up properly on this site.");
		}
		
		if(!strlen($captcha)) {
			return $respond(false, "You must solve a captcha to post.");
		}
		
		$captcha_verify = captcha_verify($captcha);
		
		if($captcha_verify === null) {
			return $respond(false, "Something went wrong when verifying your captcha.", [
				"captcha_reset" => true
			]);
		}
		
		if($captcha_verify === false) {
			return $respond(false, "Your captcha was incorrect or expired.", [
				"captcha_reset" => true
			]);
		}
	}
	
	# thread/reply specific checks
	if($is_reply) {
		# fetch thread
		$query = $db->prepare("SELECT * FROM threads FORCE INDEX(board__number) WHERE (board = :board AND number = :number)");
		$query->bindValue(":board", $board["uri"]);
		$query->bindValue(":number", $thread_number);
		$query->execute();
		
		$thread = $query->fetch(PDO::FETCH_ASSOC);
		
		process_thread($thread);
		
		if($thread) {
			$query = $db->prepare("SELECT * FROM posts FORCE INDEX(board__number) WHERE (board = :board AND number = :number)");
			$query->bindValue(":board", $thread["board"]);
			$query->bindValue(":number", $thread["number"]);
			$query->execute();
			
			$thread["post"] = $query->fetch(PDO::FETCH_ASSOC);
			
			if($thread["post"]) {
				process_post($thread["post"]);
			}
		}
		
		if(!$thread || !$thread["post"] || !can_see_post($board, $thread["post"])) {
			return $respond(false, "Specified thread does not exist.");
		}
		
		check_thread_archived($thread);
		
		# thread lock check
		if($thread["state"]["locked"] && !user_has_permission($user, $board, "post_in_locked_thread")) {
			return $respond(false, "This thread is locked, you cannot reply at this time.");
		}
		
		# thread archival check
		if(($thread["state"]["archived"] || is_thread_archived($thread)) && !user_has_permission($user, $board, "post_in_archived_thread")) {
			return $respond(false, "This thread is archived, you cannot reply anymore.");
		}
	} else {
		# thread sticky permission check
		if($start_stickied && !user_has_permission($user, $board, "set_thread_sticky")) {
			return $respond(false, "You are not allowed to sticky threads.");
		}
		
		# thread lock permission check
		if($start_locked && !user_has_permission($user, $board, "set_thread_lock")) {
			return $respond(false, "You are not allowed to lock threads.");
		}
	}
	
	# check if board is locked
	if($board["locked"]) {
		return $respond(false, "This board is locked, you cannot post at this time.");
	}
	
	# check if poster name is allowed
	if(strlen($name)  && !user_has_permission($user, $board, "post_with_name")) {
		return $respond(false, "You are not allowed to post with a name.");
	}
	
	# check if poster tripcode is allowed
	if(strlen($tripcode)  && !user_has_permission($user, $board, "post_with_tripcode")) {
		return $respond(false, "You are not allowed to post with a tripcode.");
	}
	
	# apply anon name if enabled
	if(!strlen($name) && $board["config"]["write_anonymous_name"]) {
		$name = $board["config"]["anonymous_name"];
	}
	
	# check if subject in reply is allowed
	if(strlen($subject) && $is_reply && !$board["config"]["allow_reply_subject"]) {
		return $respond(false, "Subjects are not allowed in replies.");
	}
	
	# check if sage is allowed
	if($sage && !user_has_permission($user, $board, "post_with_sage")) {
		return $respond(false, "You are not allowed to sage threads.");
	}
	
	# check for sage when starting a thread
	if($sage && !$is_reply) {
		return $respond(false, "Sage cannot be used when starting a thread.");
	}
	
	# empty post check
	if(!$file && !strlen($subject) && !strlen($comment)) {
		return $respond(false, "No text or file provided.");
	}
	
	# get argument length limits
	$max_length_name = min(CONSTANTS::MAXLEN_POST_NAME, $board["config"]["max_length_name"]);
	$max_length_subject = min(CONSTANTS::MAXLEN_POST_SUBJECT, $board["config"]["max_length_subject"]);
	$max_length_comment = min(CONSTANTS::MAXLEN_POST_COMMENT, $board["config"]["max_length_comment"]);
	
	# check argument lengths against limits
	if(strlen($name) > $max_length_name) {
		return $respond(false, "Name is too long (max {$max_length_name} chars).");
	}
	
	if(strlen($subject) > $max_length_subject) {
		return $respond(false, "Subject is too long (max {$max_length_subject} chars).");
	}
	
	if(strlen($comment) > $max_length_comment) {
		return $respond(false, "Comment is too long (max {$max_length_comment} chars).");
	}
	
	# check capcode permissions
	if(strlen($capcode) && !user_can_use_capcode($user, $board, $capcode)) {
		return $respond(false, "You are not allowed to post using the \"{$capcode}\" capcode.");
	}
	
	# check html enabling permissions
	if($enable_html && !user_has_permission($user, $board, "post_with_html")) {
		return $respond(false, "You are not allowed to post with HTML enabled.");
	}
	
	# post requirements
	if(!user_has_permission($user, $board, "bypass_post_requirements")) {
		$post_requirements = $board["config"]["post_requirements"];
		$post_requirements = $post_requirements[($is_reply ? "reply" : "thread")];
		
		$post_type_str = ($is_reply ? "reply" : "thread");
		
		if($post_requirements["file"] && !$file) {
			return $respond(false, ("A file is required to post a {$post_type_str}."));
		}
		
		if($post_requirements["subject"] && !strlen($subject) && (!$is_reply || $board["config"]["allow_reply_subject"])) {
			return $respond(false, ("A subject is required to post a {$post_type_str}."));
		}
		
		if($post_requirements["comment"] && !strlen($comment)) {
			return $respond(false, ("A comment is required to post a {$post_type_str}."));
		}
		
		if($post_requirements["subject_or_comment"] && (!strlen($subject) && !strlen($comment))) {
			return $respond(false, ("A subject or comment is required to post a {$post_type_str}."));
		}
	}
	
	# file upload handling
	if($file) {
		# initialize file vars
		$file_dimensions = null;
		$file_path_src = null;
		$file_path_thumb = null;
		$file_dest_src = null;
		$file_dest_thumb = null;
		
		# file uploads enabled check
		if(!user_has_permission($user, $board, "post_with_file")) {
			return $respond(false, "You are not allowed to post a file.");
		}
		
		# spoiler check
		if($spoiler_file && !$board["config"]["allow_spoiler_files"]) {
			return $respond(false, "Spoiler files are not allowed.");
		}
		
		$file_error = $file["error"];
		
		# check for generic php error in upload
		if($file_error !== UPLOAD_ERR_OK) {
			return $respond(false, "File upload failed (error code " . (int)$file_error . ").");
		}
		
		# get file names
		$file_basename = $file["name"]; # user-provided filename
		$file_path_src = $file["tmp_name"]; # temp filename (where the file is actually stored)
		
		# extract some file name info
		$file_name = pathinfo($file_basename, PATHINFO_FILENAME);
		$file_ext = pathinfo($file_basename, PATHINFO_EXTENSION);
		
		# file name length check
		if(strlen($file_name) > CONSTANTS::MAXLEN_FILE_FILENAME) {
			return $respond(false, "File name is too long (max " . CONSTANTS::MAXLEN_FILE_FILENAME . " chars).");
		}
		
		# info from the file itself
		$file_md5 = md5_file($file_path_src);
		$file_filesize = filesize($file_path_src);
		
		# detect file mimetype
		$file_mime = b4k::get_mimetype_file($file_path_src);
		
		# mimetype guessing fail check
		if(!strlen($file_mime)) {
			return $respond(false, "Unable to determine file type of uploaded file.");
		}
		
		# replace mimetype aliases
		foreach(CONSTANTS::MIMETYPE_ALIASES as $alias_key => $alias_value) {
			if($alias_key === $file_mime) {
				$file_mime = $alias_value;
				break;
			}
		}
		
		# guess extension from mimetype
		$file_ext_guessed = get_ext_from_mime($file_mime);
		
		# unrecognized mimetype check
		if(!strlen($file_ext_guessed)) {
			return $respond(false, "Unrecognized file type \"{$file_mime}\".");
		}
		
		# normalize file extension
		$file_ext = strtolower($file_ext);
		$file_ext_guessed = strtolower($file_ext_guessed);
		
		# replace extension aliases
		foreach(CONSTANTS::EXTENSION_ALIASES as $alias_key => $alias_value) {
			if($alias_key === $file_ext) {
				$file_ext = $alias_value;
				break;
			}
		}
		
		# check if user-provided extension matches detected extension
		if($file_ext !== $file_ext_guessed) {
			return $respond(false, "File extension does not match mimetype.");
		}
		
		# get file type options from config
		$file_type = get_file_type($file_ext, $board);
		
		# check if file type exists and is allowed
		if(!$file_type || (!$file_type["allowed"] && !user_has_permission($user, $board, "bypass_file_restrictions"))) {
			return $respond(false, "Files of type \"{$file_ext}\" are not allowed.");
		}
		
		# file size check
		if($file_filesize > $file_type["max_filesize"] && !user_has_permission($user, $board, "bypass_file_restrictions")) {
			return $respond(false, "File is too big (max " . b4k::format_bytes($file_type["max_filesize"]) . ").");
		}
		
		# generate full image destination path
		$file_dest_src = get_file_path_local([
			"hash" => $file_md5,
			"extension" => $file_ext,
			"is_thumb" => false,
			"is_thumb_op" => false
		]);
		
		# check if a thumbnail should be generated
		$file_should_thumbnail = ($file_type["thumbnail"] === null);
		
		# image specific code
		if(in_array($file_ext, ["jpg", "png", "gif"])) {
			if(true /* "block_low_quality_images" */) {
				if($file_name === "image") {
					return $respond(false, "Please do not upload low quality images.");
				}
			}
			
			$file_dimensions = getimagesize($file_path_src);
			
			if(!$file_dimensions) {
				return $respond(false, "Image file processing failed.");
			}
			
			$file_dimensions = [
				"width" => $file_dimensions[0],
				"height" => $file_dimensions[1]
			];
			
			if($file_type["max_size"] &&  max($file_dimensions) > $file_type["max_size"] && !user_has_permission($user, $board, "bypass_file_restrictions")) {
				return $respond(false, "Image file dimensions are too large (max " . $file_type["max_size"] . " px).");
			}
			
			if($file_should_thumbnail) {
				$file_dest_thumb = get_file_path_local([
					"hash" => $file_md5,
					"extension" => null,
					"is_thumb" => true,
					"is_thumb_op" => !$is_reply
				]);
				
				if(!file_exists($file_dest_thumb)) {
					$file_dimensions_thumb = get_thumb_dimensions($file_dimensions, !$is_reply, $board);
					
					$file_path_thumb = get_temp_file_path();
					
					$file_canvas_src = call_user_func(function() use($file_path_src, $file_ext) {
						switch($file_ext) {
							case "jpg":
								return imagecreatefromjpeg($file_path_src);
							case "png":
								return imagecreatefrompng($file_path_src);
							case "gif":
								return imagecreatefromgif($file_path_src);
						}
					});
					
					$file_canvas_thumb = imagecreatetruecolor($file_dimensions_thumb["width"], $file_dimensions_thumb["height"]);
					
					imagefill($file_canvas_thumb, 0, 0, imagecolorallocate($file_canvas_thumb, 255, 255, 255));
					
					imagecopyresampled($file_canvas_thumb, $file_canvas_src, 0, 0, 0, 0, $file_dimensions_thumb["width"], $file_dimensions_thumb["height"], $file_dimensions["width"], $file_dimensions["height"]);
					
					imagejpeg($file_canvas_thumb, $file_path_thumb, 90);
					
					imagedestroy($file_canvas_src);
					imagedestroy($file_canvas_thumb);
				}
			}
		}
		
		# video specific code
		if(in_array($file_ext, ["webm", "mp4"])) {
			$file_video_ffprobe = shell_exec("ffprobe -v quiet -print_format json -show_format -show_streams " . escapeshellarg($file_path_src));
			$file_video_ffprobe = b4k::json_decode($file_video_ffprobe);
			
			if(!$file_video_ffprobe) {
				return $respond(false, "Video file processing failed.");
			}
			
			if(count($file_video_ffprobe["streams"]) === 0) {
				return $respond(false, "Video file does not contain any streams.");
			}
			
			if($file_video_ffprobe["format"]["probe_score"] < 90) {
				return $respond(false, "Video file failed validation (probe score too low).");
			}
			
			if($file_ext === "webm" && $file_video_ffprobe["format"]["format_name"] !== "matroska,webm") {
				return $respond(false, "Video file is invalid (wrong webm format).");
			}
			
			if(count($file_video_ffprobe["streams"]) > 2) {
				return $respond(false, "Video file has more than 2 streams.");
			}
			
			if($file_type["max_duration"] && floatval($file_video_ffprobe["format"]["duration"]) > $file_type["max_duration"]) {
				return $respond(false, "Video is too long (max {$file_type["max_duration"]} sec).");
			}
			
			$file_video_ffprobe_stream_video = null;
			$file_video_ffprobe_stream_audio = null;
			
			foreach($file_video_ffprobe["streams"] as $stream) {
				switch($stream["codec_type"]) {
					case "video":
						$file_video_ffprobe_stream_video = $stream;
						break;
					
					case "audio":
						$file_video_ffprobe_stream_audio = $stream;
						break;
					
					default:
						return $respond(false, "Unrecognized stream type in video file (index {$stream["index"]}, type \"{{$stream["codec_type"]}}\").");
				}
			}
			
			if(!$file_video_ffprobe_stream_video) {
				return $respond(false, "No valid video stream found in video file.");
			}
			
			if($file_video_ffprobe_stream_audio && ($file_type["disallow_audio"] && !user_has_permission($user, $board, "bypass_file_restrictions"))) {
				return $respond(false, "Audio streams are not allowed in video files.");
			}
			
			if($file_ext === "mp4" && ($file_video_ffprobe_stream_video["codec_name"] !== "h264" || ($file_video_ffprobe_stream_audio && $file_video_ffprobe_stream_audio["codec_name"] !== "aac"))) {
				return $respond(false, "Video file is invalid (wrong mp4 codec).");
			}
			
			$file_dimensions = [
				"width" => $file_video_ffprobe_stream_video["width"],
				"height" => $file_video_ffprobe_stream_video["height"]
			];
			
			if($file_type["max_dimensions"] &&  max($file_dimensions) > $file_type["max_dimensions"] && !user_has_permission($user, $board, "bypass_file_restrictions")) {
				return $respond(false, "Video file dimensions are too large (max " . $file_type["max_dimensions"] . " px).");
			}
			
			if($file_should_thumbnail) {
				$file_dest_thumb = get_file_path_local([
					"hash" => $file_md5,
					"extension" => null,
					"is_thumb" => true,
					"is_thumb_op" => !$is_reply
				]);
				
				if(!file_exists($file_dest_thumb)) {
					$file_dimensions_thumb = get_thumb_dimensions($file_dimensions, !$is_reply, $board);
					
					$file_path_thumb = get_temp_file_path();
					
					shell_exec("ffmpeg -i " . escapeshellarg($file_path_src) . " -v quiet -y -an -ss 0 -vframes 1 -q:v 5 -f image2 -vf scale={$file_dimensions_thumb["width"]}:{$file_dimensions_thumb["height"]} " . escapeshellarg($file_path_thumb));
					
					clearstatcache();
					
					if(!file_exists($file_path_thumb) || filesize($file_path_thumb) === 0) {
						return $respond(false, "Video file thumbnailing failed.");
					}
				}
			}
		}
		
		# setup file store queue
		$file_store_queue = [
			[$file_path_src, $file_dest_src],
			[$file_path_thumb, $file_dest_thumb]
		];
		
		# store files
		foreach($file_store_queue as $_file) {
			$_path = $_file[0];
			$_dest = $_file[1];
			
			if(!strlen($_path)) {
				continue;
			}
			
			if(!file_exists($_path)) {
				return $respond(false, "File processing failed.");
			}
			
			$_external_files_enabled = $config["general"]["external_files"]["enabled"];
			$_external_files_only = $config["general"]["external_files"]["only"];
			
			if($_external_files_enabled) {
				$_ftp_path = $config["general"]["external_files"]["ftp_path"];
				$_ftp_path = b4k::trim_slashes($_ftp_path, false, true);
				
				$_dest_this = "{$_ftp_path}/{$_dest}";
				
				_ftp_connect();
				
				_ftp_navdir(dirname($_dest_this));
				
				if(ftp_size($ftp, basename($_dest_this)) !== -1) {
					continue;
				}
				
				$_file_open = fopen($_path, "r");
				
				$_ftp_fput = ftp_fput($ftp, basename($_dest_this), $_file_open, FTP_BINARY);
				
				if(!$_ftp_fput) {
					return $respond(false, "File storage failed.");
				}
			}
			
			if(!$_external_files_enabled || !$_external_files_only) {
				$_dest_this = "{$basepath}/data/files/{$_dest}";
				
				if(file_exists($_dest_this)) {
					continue;
				}
				
				b4k::make_dir(dirname($_dest_this));
				copy($_path, $_dest_this);
			}
		}
		
		# close ftp connection if opened
		_ftp_close();
	}
	
	# get previous post number in board
	$query = $db->prepare("SELECT post_number FROM boards WHERE (uri = :uri)");
	$query->bindValue(":uri", $board["uri"]);
	$query->execute();
	
	$number = $query->fetchColumn();
	$number = (int)$number;
	
	# increment post number
	$number++;
	
	$db->beginTransaction();
	
	# update board with new post number
	$query = $db->prepare("UPDATE boards SET post_number = :number WHERE (id = :id)");
	$query->bindValue(":number", $number);
	$query->bindValue(":id", $board["id"]);
	$query->execute();
	
	# insert post
	$query = $db->prepare("INSERT INTO posts (board,number,thread,time_posted,time_modified,ip,user,name,tripcode,capcode,subject,comment,sage,enable_html) VALUES (:board,:number,:thread,:time_posted,:time_modified,:ip,:user,:name,:tripcode,:capcode,:subject,:comment,:sage,:enable_html)");
	$query->bindValue(":board", $board["uri"]);
	$query->bindValue(":number", $number);
	$query->bindValue(":thread", ($is_reply ? $thread["number"] : $number));
	$query->bindValue(":time_posted", $time);
	$query->bindValue(":time_modified", $time);
	$query->bindValue(":ip", $ip);
	$query->bindValue(":user", ($user["logged_in"] ? $user["id"] : null));
	$query->bindValue(":name", $name);
	$query->bindValue(":tripcode", $tripcode);
	$query->bindValue(":capcode", $capcode);
	$query->bindValue(":subject", $subject);
	$query->bindValue(":comment", $comment);
	$query->bindValue(":sage", (int)$sage);
	$query->bindValue(":enable_html", (int)$enable_html);
	$query->execute();
	
	if(!$is_reply) {
		# insert thread
		$query = $db->prepare("INSERT INTO threads (board,number,time_bumped,time_modified,stickied,locked) VALUES (:board,:number,:time_bumped,:time_modified,:stickied,:locked)");
		$query->bindValue(":board", $board["uri"]);
		$query->bindValue(":number", $number);
		$query->bindValue(":time_bumped", $time);
		$query->bindValue(":time_modified", $time);
		$query->bindValue(":stickied", (int)$start_stickied);
		$query->bindValue(":locked", (int)$start_locked);
		$query->execute();
	} else {
		update_time_modified_thread($thread, $time);
		
		# check if should bump
		if(!$sage && !$thread["bumplocked"] && ($thread["time_bumped"] < $time)) {
			$query = $db->prepare("UPDATE threads SET time_bumped = :time_bumped WHERE (id = :id)");
			$query->bindValue(":time_bumped", $time);
			$query->bindValue(":id", $thread["id"]);
			$query->execute();
		}
	}
	
	if($file) {
		# insert file
		$query = $db->prepare("INSERT INTO files (board,number,md5,extension,filesize,filename,dimensions,duration,spoiler) VALUES (:board,:number,:md5,:extension,:filesize,:filename,:dimensions,:duration,:spoiler)");
		$query->bindValue(":board", $board["uri"]);
		$query->bindValue(":number", $number);
		$query->bindValue(":md5", $file_md5);
		$query->bindValue(":extension", $file_ext);
		$query->bindValue(":filesize", $file_filesize);
		$query->bindValue(":filename", $file_name);
		$query->bindValue(":dimensions", ($file_dimensions ? "{$file_dimensions["width"]}x{$file_dimensions["height"]}" : null));
		$query->bindValue(":duration", ($file_duration ? $file_duration : null));
		$query->bindValue(":spoiler", (int)$spoiler_file);
		$query->execute();
	}
	
	$db->commit();
	
	$db->query("UNLOCK TABLES");
	
	# send post successful response
	return $respond(true, "Post successful!", [
		"post" => [
			"board" => $board["uri"],
			"number" => $number,
			"thread" => ($is_reply ? $thread["number"] : $number),
			"url" => url("/{$board["uri"]}/thread/" . ($is_reply ? "{$thread["number"]}#p{$number}" : $number))
		]
	]);
}

<?php

$config = null;

function config_load() {
	global $config;
	
	$config = [];
	
	config_edit_default($config);
	config_edit_user($config);
}

function config_edit_default(&$config) {
	# database info and credentials
	$config["database"] = [
		"host" => "localhost",
		"user" => "admin",
		"pass" => "admin",
		"dbname" => "imb"
	];
	
	$config["general"] = [
		# base url of this site, null for auto-detect
		"url" => null,
		
		# name of this site, displayed in various places
		"title" => (APP_NAME . " imageboard"),
		
		# html frontend settings
		"frontend" => [
			
		],
		
		# php session settings (login tracking)
		"session" => [
			# name of the cookie used to store login sessions
			"name" => "imb_session",
			
			# lifetime of session cookies - they will always expire after this time
			"lifetime" => (60*60*24*30),
			
			# gc timeout of session cookies - they will be removed if inactive for longer than this
			"timeout" => (60*60*24*7),
			
			# set login session cookie as secure (only available in https), this should not be turned off unless absolutely necessary
			"secure" => true
		],
		
		# file prefix, added to front of download filenames
		"file_prefix" => "imb",
		
		# external file storage using ftp
		"external_files" => [
			# enable external files
			"enabled" => false,
			
			# only store files in external location, set to false to still store them locally
			"only" => false,
			
			# ftp info and credentials
			"ftp_info" => [
				"host" => "localhost",
				"user" => "admin",
				"pass" => "admin"
			],
			
			# path to upload the files to
			"ftp_path" => "/path-to-site-dir/imb-files/",
			
			# path to the files on the web (including domain)
			"web_path" => "//your-site-here.com/imb-files/",
		],
		
		# recaptcha details
		"captcha" => [
			"site_key" => null,
			"secret_key" => null
		]
	];
	
	$config["users"] = [];
	
	# preset user roles - these are not necessary, you can use the level number directly, they're just for convenience
	$config["users"]["roles"] = [
		"default" => 0,
		"user" => 10,
		"mod" => 80,
		"admin" => 90,
		"superadmin" => 100
	];
	
	# user capcodes
	$config["users"]["capcodes"] = [
		"admin" => [
			# level required to use this capcode
			"level" => $config["users"]["roles"]["admin"],
			
			# text that will be displayed as the capcode itself next to the poster name
			"title" => "Admin",
			
			# text that will show up when hovering over this capcode
			"desc" => "This user is an Administrator.",
			
			# color of the capcode text
			"color" => null
		],
		
		"mod" => [
			"level" => $config["users"]["roles"]["mod"],
			"title" => "Mod",
			"desc" => "This user is a Moderator.",
			"color" => null
		]
	];
	
	# global permissions
	$config["permissions"] = [
		"create_board" => $config["users"]["roles"]["admin"],
		"delete_board" => $config["users"]["roles"]["admin"],
		"view_bans" => $config["users"]["roles"]["mod"],
		"add_ban" => $config["users"]["roles"]["mod"],
		"remove_ban" => $config["users"]["roles"]["mod"]
	];
	
	# board configurations
	$config["boards"] = [];
	
	# default board configuration - all boards will use these values unless overwritten on a per-board basis
	$config["boards"]["*"] = [
		# default name displayed when the user doesn't enter a name
		"anonymous_name" => "Anonymous",
		
		# prevent users from using the name field
		"forced_anonymous" => false,
		
		# set this to true to write the default name as a normal name when a post is made
		# in other words, changing anonymous_name won't affect posts made before the change
		"write_anonymous_name" => false,
		
		# length of poster ids
		"poster_ids_length" => 6,
		
		# poster public ids
		"poster_public_ids" => [
			"enabled" => false,
			"hide_if_capcoded" => true,
			"per_board" => true,
			"per_thread" => false
		],
		
		# poster country flags
		"poster_country_flags" => [
			"enabled" => false,
			"hide_if_capcoded" => true
		],
		
		# allow users to use the "sage" option to reply without bumstickyg
		"allow_sage" => true,
		
		# number of threads to display on each view
		"index_thread_count_per_page" => 20,
		"catalog_thread_count" => 100,
		
		# max number of most recent replies shown in the index
		"index_shown_replies" => 5,
		"index_shown_replies_sticky" => 1,
		
		# thread pruning/archival settings (if a thread exceeds these limits, it will be marked as archived)
		"pruning" => [
			"max_thread_alive_time" => null,
			"max_alive_threads" => null
		],
		
		# time (in seconds) before redirecting from the "post successful" screen
		"post_successful_redirect_time" => 1,
		
		# post text limitations
		"max_length_name" => 128,
		"max_length_subject" => 128,
		"max_length_comment" => 5000,
		
		# allow subjects in replies
		"allow_reply_subject" => false,
		
		# requirements for posts (note: if a post has no file, subject or comment, it will always be rejected)
		"post_requirements" => [
			"thread" => [
				"file" => true,
				"subject" => false,
				"comment" => false,
				"subject_or_comment" => false
			],
			
			"reply" => [
				"file" => false,
				"subject" => false,
				"comment" => false,
				"subject_or_comment" => false
			],
		],
		
		# allow files to be posted as spoilers
		"allow_spoiler_files" => true,
		
		# list of file types and their settings
		# "thumbnail" is the thumbnail image to use, use null to generate a thumbnail for each file if possible
		"file_types" => [
			"image" => [
				# extension or array of extensions for this file type
				"ext" => ["jpg", "png", "gif"],
				
				# whether or not this file type is allowed
				"allowed" => true,
				
				# filename of this file type's thumbnail (path "/web/assets-custom/file-type-thumbs/{text}.png")
				# if this is null, a thumbnail will be generated if possible - if not, a default thumbnail will be used
				"thumbnail" => null,
				
				# maximum file size in bytes for this file type
				"max_filesize" => (1024 * 1024 * 4),
				
				# maximum width/height in pixels for this file size, only for images and videos
				"max_dimensions" => 10000
			],
			
			"video" => [
				"ext" => ["webm", "mp4"],
				"allowed" => true,
				"thumbnail" => null,
				"max_filesize" => (1024 * 1024 * 4),
				"max_dimensions" => 4096,
				"max_duration" => (60 * 10), # max length in seconds for videos
				"disallow_audio" => true # enable rejecting of videos with audio tracks
			],
			
			"audio" => [
				"ext" => ["mp3", "ogg"],
				"allowed" => true,
				"thumbnail" => "audio",
				"max_filesize" => (1024 * 1024 * 4)
			],
			
			"flash" => [
				"ext" => "swf",
				"allowed" => true,
				"thumbnail" => "flash",
				"max_filesize" => (1024 * 1024 * 4)
			]
		],
		
		# thumbnail dimensions for different views
		# keep in mind that catalog thumbnails are the same as op thumbnails but are resized via css to the catalog size
		"thumbnail_dimensions" => [
			"op" => 250,
			"reply" => 125,
			"catalog" => 150
		],
		
		# use javascript to load thumbnails as they become visible instead of loading them all at once, reduces frequency of requests significantly
		"thumbnail_lazyload" => true,
		
		# always display a "no file" thumbnail for threads with no file
		# it will always be displayed in the catalog regardless of this setting
		"display_absent_file" => false,
		
		# board-specific permissions
		"permissions" => [
			"see_board" => $config["users"]["roles"]["default"],
			"access_board" => $config["users"]["roles"]["default"],
			"post_thread" => $config["users"]["roles"]["default"],
			"post_reply" => $config["users"]["roles"]["default"],
			"post_in_locked_thread" => $config["users"]["roles"]["mod"],
			"post_in_archived_thread" => $config["users"]["roles"]["mod"],
			"post_without_captcha" => $config["users"]["roles"]["user"],
			"post_with_name" => $config["users"]["roles"]["default"],
			"post_with_tripcode" => $config["users"]["roles"]["default"],
			"post_with_file" => $config["users"]["roles"]["default"],
			"post_with_html" => $config["users"]["roles"]["admin"],
			"post_with_sage" => $config["users"]["roles"]["default"],
			"bypass_post_requirements" => $config["users"]["roles"]["mod"],
			"bypass_file_restrictions" => $config["users"]["roles"]["admin"],
			"set_file_spoiler" => $config["users"]["roles"]["mod"],
			"set_thread_sticky" => $config["users"]["roles"]["mod"],
			"set_thread_lock" => $config["users"]["roles"]["mod"],
			"set_thread_bumplock" => $config["users"]["roles"]["mod"],
			"bump_thread_manually" => $config["users"]["roles"]["mod"],
			"delete_thread" => $config["users"]["roles"]["mod"],
			"delete_reply" => $config["users"]["roles"]["mod"],
			"delete_thread_file" => $config["users"]["roles"]["mod"], # todo (file deletion)
			"delete_reply_file" => $config["users"]["roles"]["mod"], # todo (file deletion)
			"edit_post" => $config["users"]["roles"]["admin"],
			"see_thread_bumplock" => $config["users"]["roles"]["default"],
			"see_post_sage" => $config["users"]["roles"]["default"],
			"see_poster_info" => $config["users"]["roles"]["mod"],
			"see_poster_ips" => $config["users"]["roles"]["mod"],
			"see_poster_users" => $config["users"]["roles"]["mod"],
			"see_deleted_posts" => $config["users"]["roles"]["mod"],
			"see_shadow_posts" => $config["users"]["roles"]["mod"],
		]
	];
	
	$config["boards"]["test"] = [
		# values defined here will overwrite the values above for the specified board
	];
}

function config_edit_user(&$config) {
	global $basepath;
	
	$path = "{$basepath}/config/config.php";
	
	if(is_file($path)) {
		$func = require_once($path);
		
		if(is_callable($func)) {
			$func($config);
		}
	}
}

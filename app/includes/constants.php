<?php

abstract class CONSTANTS {
	const USER_LEVEL_MIN = 0;
	const USER_LEVEL_MAX = 100;
	const USER_LEVEL_DEFAULT = 0;
	const USER_LEVEL_IMPOSSIBLE = (self::USER_LEVEL_MIN - 1);
	const USER_INITIAL_USERNAME = "admin";
	const USER_INITIAL_PASSWORD = "admin";
	const USER_INITIAL_LEVEL = self::USER_LEVEL_MAX;
	const SALT_GENERATED_LENGTH = 128;
	const MAXLEN_USER_USERNAME = 128;
	const MAXLEN_USER_PASSWORD = 64;
	const MAXLEN_BOARD_URI = 128;
	const MAXLEN_BOARD_TITLE = 128;
	const MAXLEN_BOARD_SUBTITLE = 1024;
	const MAXLEN_POST_NAME = 128;
	const MAXLEN_POST_SUBJECT = 128;
	const MAXLEN_POST_COMMENT = 32768;
	const MAXLEN_FILE_FILENAME = 256;
	const MAXLEN_FILE_FILENAME_SHOWN = 20;
	const MAXLEN_THREAD_SUMMARY = 32;
	const BOARD_INDEX_SHOWN_REPLIES_MAX = 10;
	const POSTER_ID_HASH_ALGORITHM = "sha256";
	const POSTER_ID_HASH_ITERATIONS = 10;
	const DATE_FORMAT_POST = "m/d/y(D)H:i:s";
	const DATE_FORMAT_BANNED = "m/d/y H:i";
	const FILE_HASH_USED_LENGTH = 10;
	const LINK_NULL_URL = "javascript:void(0);";
	const IMAGE_NULL_URL = "data:image/gif;base64,R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7";
	const REGEX_BOARD_URI = "([^\/\\\?\&\=]+)";
	const REGEX_FILE_REQUEST = "(?:.+\-)?([0-9a-fA-F]+)\-(src|thb-op|thb-rp)\.([a-zA-Z0-9]+)";
	
	const RESTRICTED_BOARD_URIS = [
		"*",
		"assets",
		"index.php",
		"index.html",
		"robots.txt",
		"banner",
		"post",
		"report",
		"banned",
		"warning",
		"cp",
		"files"
	];
	
	const MIMETYPES_OF_EXTENSIONS = [
		"jpg" => "image/jpeg",
		"png" => "image/png",
		"gif" => "image/gif",
		"mp3" => "audio/mpeg",
		"ogg" => "audio/ogg",
		"webm" => "video/webm",
		"mp4" => "video/mp4",
		"swf" => "application/x-shockwave-flash"
	];
	
	const EXTENSION_ALIASES = [
		"jpeg" => "jpg"
	];
	
	const MIMETYPE_ALIASES = [
		"application/ogg" => "audio/ogg"
	];
}

abstract class C extends CONSTANTS {
	# wew lad
}

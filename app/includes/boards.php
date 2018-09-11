<?php

$boards = null;

function get_all_boards() {
	global $db;
	global $config;
	global $boards;
	
	if($boards) {
		return;
	}
	
	$query = $db->prepare("SELECT * FROM boards ORDER BY uri");
	$query->execute();
	
	$_boards = $query->fetchAll(PDO::FETCH_ASSOC);
	
	foreach($_boards as &$board) {
		process_board($board);
	}
	
	$boards = $_boards;
}

function get_board_by_uri($uri) {
	global $boards;
	
	if(is_board_uri_valid($uri)) {
		get_all_boards();
		
		foreach($boards as $board) {
			if($board["uri"] === $uri) {
				return $board;
			}
		}
	}
	
	return null;
}

function is_board_uri_valid($uri) {
	if(strlen($uri) === 0) {
		return false;
	}
	
	if(mb_strlen($uri) > CONSTANTS::MAXLEN_BOARD_URI) {
		return false;
	}
	
	if(!preg_match(("/^" . CONSTANTS::REGEX_BOARD_URI . "$/"), $uri)) {
		return false;
	}
	
	if(in_array(mb_strtolower($uri), CONSTANTS::RESTRICTED_BOARD_URIS, true)) {
		return false;
	}
	
	return true;
}

<?php

$user = null;
$users = null;

function get_all_users() {
	global $db;
	global $config;
	global $users;
	
	if($users) {
		return;
	}
	
	$query = $db->prepare("SELECT * FROM users ORDER BY id");
	$query->execute();
	
	$_users = $query->fetchAll(PDO::FETCH_ASSOC);
	
	foreach($_users as &$_user) {
		process_user($_user);
	}
	
	$users = $_users;
}

function get_user_by_id($id) {
	global $users;
	
	get_all_users();
	
	foreach($users as $i_user) {
		if($i_user["id"] === (int)$id) {
			return $i_user;
		}
	}
	
	return null;
}

function get_user_by_username($username) {
	global $users;
	
	get_all_users();
	
	foreach($users as $i_user) {
		if($i_user["username"] === $username) {
			return $i_user;
		}
	}
	
	return null;
}

function set_current_user($_user = null) {
	global $user;
	
	$user = ($_user !== null ? $_user : []);
	
	$user["logged_in"] = !!$_user;
	
	# twig global needs to be updated
	twig_set_globals();
}

function user_has_permission($user = null, $board = null, $permission) {
	global $config;
	
	$permissions = [];
	$permissions = array_merge($permissions, $config["permissions"]);
	$permissions = ($board !== null ? array_merge($permissions, $board["config"]["permissions"]) : $permissions);
	
	if($board && !user_is_assigned_to_board($user, $board)) {
		return false;
	}
	
	foreach($permissions as $permission_name => $permission_level) {
		if($permission_name === $permission) {
			if(!level_is_impossible($permission_level) && (user_get_level($user) >= $permission_level)) {
				return true;
			}
		}
	}
	
	return false;
}

function user_can_use_capcode($user = null, $board = null, $capcode_key) {
	global $config;
	
	$capcodes = $config["users"]["capcodes"];
	
	if(!user_is_assigned_to_board($user, $board)) {
		return false;
	}
	
	if($capcode_key === null) {
		foreach($capcodes as $capcode) {
			if($capcode) {
				if(!level_is_impossible($capcode["level"]) && (user_get_level($user) >= $capcode["level"])) {
					return true;
				}
			}
		}
	} else {
		$capcode = $capcodes[$capcode_key];
		
		if($capcode) {
			if(!level_is_impossible($capcode["level"]) && (user_get_level($user) >= $capcode["level"])) {
				return true;
			}
		}
	}
	
	return false;
}

function user_is_assigned_to_board($user = null, $board) {
	global $boards;
	
	$user_boards = ($user["logged_in"] ? explode(",", $user["boards"]) : ["*"]);
	
	if(in_array("*", $user_boards, true)) {
		$user_boards = [];
		
		foreach($boards as $board_i) {
			$user_boards[] = $board_i["uri"];
		}
	}
	
	return ($board === null || in_array($board["uri"], $user_boards, true));
}

function user_get_level($user) {
	return ($user["logged_in"] ? $user["level"] : CONSTANTS::USER_LEVEL_DEFAULT);
}

function level_is_impossible($level) {
	return ($level === CONSTANTS::USER_LEVEL_IMPOSSIBLE);
}

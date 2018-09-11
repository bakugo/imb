<?php

function session_start_c() {
	global $basepath;
	global $config;
	
	if(session_status() === PHP_SESSION_ACTIVE) {
		return;
	}
	
	$session_name = $config["general"]["session"]["name"];
	$session_lifetime = $config["general"]["session"]["lifetime"];
	$session_timeout = $config["general"]["session"]["lifetime"];
	$session_secure = $config["general"]["session"]["secure"];
	$session_path = "{$basepath}/data/sessions";
	
	ini_set("session.name", $session_name);
	ini_set("session.save_path", $session_path);
	ini_set("session.cookie_lifetime", $session_lifetime);
	ini_set("session.cookie_domain", null);
	ini_set("session.cookie_secure", $session_secure);
	ini_set("session.cookie_httponly", true);
	ini_set("session.gc_maxlifetime", $session_timeout);
	ini_set("session.hash_function", "sha256");
	ini_set("session.hash_bits_per_character", 4);
	ini_set("session.use_strict_mode", true);
	ini_set("session.use_only_cookies", true);
	
	b4k::make_dir($session_path);
	
	session_start();
	
	$_SESSION["timestamp"] = b4k::get_time_sec();
}

function login_login($username, $password) {
	global $users;
	
	session_start_c();
	
	if(strlen($username) && strlen($password)) {
		$_user = get_user_by_username($username);
		
		if($_user !== null) {
			if(login_password_verify($password, $_user["password"])) {
				$_SESSION["login"] = [
					"user_id" => $_user["id"],
					"password_hash" => login_password_hash_session($_user["password"]),
					"remote_addr" => get_remote_addr(),
					"user_agent" => get_user_agent()
				];
				
				session_regenerate_id();
				
				set_current_user($_user);
				
				return $_user;
			}
		}
	}
	
	return false;
}

function login_logout() {
	session_start_c();
	
	if($_SESSION["login"] === null) {
		return false;
	}
	
	$_SESSION["login"] = null;
	
	set_current_user(null);
	
	return true;
}

function login_check() {
	global $user;
	
	session_start_c();
	
	if($user) {
		return $user;
	}
	
	$sessiondata = $_SESSION["login"];
	
	if($sessiondata) {
		$_user = get_user_by_id($sessiondata["user_id"]);
		
		if($_user) {
			if(hash_equals($sessiondata["password_hash"], login_password_hash_session($_user["password"]))) {
				set_current_user($_user);
				
				return $_user;
			}
		}
	}
	
	login_logout();
	
	set_current_user(null);
	
	return false;
}

function login_password_hash($password) {
	return password_hash($password, PASSWORD_DEFAULT);
}

function login_password_verify($password, $hash) {
	return password_verify($password, $hash);
}

function login_password_hash_session($password) {
	return hash("sha256", $password);
}

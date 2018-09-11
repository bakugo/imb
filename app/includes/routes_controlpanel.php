<?php

function require_login() {
	global $user;
	
	if(!$user["logged_in"]) {
		redirect(url("/cp/login"));
		exit;
	}
}

function route_controlpanel($path) {
	login_check();
	
	if($path === "/login") {
		return route_controlpanel_login();
	}
	
	require_login();
	
	if($path === "" || $path === "/") {
		redirect(url("/cp/dashboard"));
		return true;
	}
	
	if($path === "/dashboard") {
		return route_controlpanel_dashboard();
	}
	
	if(preg_match(("/^\/(user|admin|mod)(\/(?:.*)|)$/"), $path, $matches)) {
		switch($matches[1]) {
			case "user":
				if(route_controlpanel_user($matches[2])) return true;
				break;
			
			case "admin":
				if(route_controlpanel_admin($matches[2])) return true;
				break;
			
			case "mod":
				if(route_controlpanel_mod($matches[2])) return true;
				break;
		}
	}
	
	return false;
}

function route_controlpanel_dashboard() {
	global $twig;
	
	twig_init();
	
	$twig->display("controlpanel_dashboard.html");
	
	return true;
}

function route_controlpanel_login() {
	global $twig;
	global $user;
	global $httpvars;
	
	twig_init();
	
	$username = null;
	$message = null;
	
	if(isset($httpvars["get"]["out"]) && $user["logged_in"]) {
		login_logout();
		$message = "Logged out successfully.";
	} else {
		if($user["logged_in"]) {
			redirect(url("/cp/dashboard"));
		} else {
			if(http_param_bool($httpvars["post"]["submit"])) {
				$username = $httpvars["post"]["username"];
				$password = $httpvars["post"]["password"];
				
				if(login_login($username, $password)) {
					redirect(url("/cp/dashboard"));
				} else {
					$message = "Invalid login.";
				}
			}
		}
	}
	
	$twig->display("controlpanel_login.html", [
		"username" => $username,
		"message" => $message
	]);
	
	return true;
}

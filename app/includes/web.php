<?php

function web() {
	global $httpvars;
	
	$httpvars = [
		"get" => $_GET,
		"post" => $_POST,
		"get_n_post" => array_merge($_GET, $_POST),
		"cookie" => $_COOKIE,
		"files" => $_FILES
	];
	
	session_start_c();
	login_check();
	
	$path = get_path();
	
	$routed = route($path);
	
	if(!$routed) {
		error(404);
	}
	
	exit;
}

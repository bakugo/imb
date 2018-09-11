<?php

$ftp = null;

function _ftp_connect() {
	global $ftp;
	global $config;
	
	if($ftp !== null) {
		return;
	}
	
	$ftp_host = $config["general"]["external_files"]["ftp_info"]["host"];
	$ftp_user = $config["general"]["external_files"]["ftp_info"]["user"];
	$ftp_pass = $config["general"]["external_files"]["ftp_info"]["pass"];
	
	try {
		$ftp = ftp_connect($ftp_host, null, 10);
		
		if(!$ftp) {
			throw new Exception();
		}
		
		$ftp_login = ftp_login($ftp, $ftp_user, $ftp_pass);
		
		if(!$ftp_login) {
			throw new Exception();
		}
	} catch(Exception $e) {
		error(500, "FTP connection failed");
	}
}

function _ftp_close() {
	global $ftp;
	
	if($ftp === null) {
		return;
	}
	
	ftp_close($ftp);
	
	$ftp = null;
}

function _ftp_navdir($path) {
	global $ftp;
	
	if($ftp === null) {
		return;
	}
	
	if(@ftp_chdir($ftp, $path)) {
		return;
	}
	
	$parts = explode("/", $path);
	
	foreach($parts as $i => $part) {
		if(!strlen($part)) {
			if($i === 0) {
				$part = "/";
			} else {
				continue;
			}
		}
		
		if(!@ftp_chdir($ftp, $part)) {
			ftp_mkdir($ftp, $part);
			ftp_chdir($ftp, $part);
		}
	}
}

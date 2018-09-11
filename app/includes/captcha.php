<?php

function captcha_get_site_key() {
	global $config;
	
	return $config["general"]["captcha"]["site_key"];
}

function captcha_get_secret_key() {
	global $config;
	
	return $config["general"]["captcha"]["secret_key"];
}

function captcha_is_set_up() {
	return (strlen(captcha_get_site_key()) && strlen(captcha_get_secret_key()));
}

function captcha_verify($response) {
	global $config;
	
	if(captcha_is_set_up()) {
		$data = [
			"secret" => captcha_get_secret_key(),
			"response" => $response
		];
		
		$ch = curl_init();
		
		curl_setopt_array($ch, [
			CURLOPT_URL => "https://www.google.com/recaptcha/api/siteverify",
			CURLOPT_TIMEOUT => 10,
			CURLOPT_USERAGENT => get_app_useragent(),
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_POST => true,
			CURLOPT_POSTFIELDS => http_build_query($data)
		]);
		
		$verification = curl_exec($ch);
		
		curl_close($ch);
		
		if($verification !== false) {
			$verification = json_decode($verification, true);
			
			if($verification && isset($verification["success"])) {
				return $verification["success"];
			}
		}
	}
	
	return null;
}

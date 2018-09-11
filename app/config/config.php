<?php

# override any config options inside this function
# check the bottom of /includes/config.php to see the default settings and their descriptions

return function(&$config) {
	$config["database"] = [
		"host" => "localhost",
		"user" => "imb",
		"pass" => "",
		"dbname" => "imb"
	];
	
	$config["general"]["title"] = "imb Test";
	$config["general"]["file_prefix"] = "imb";
	
	$config["general"]["session"]["lifetime"] = (60*60*24*365);
	$config["general"]["session"]["secure"] = true;
	
	$config["general"]["captcha"]["site_key"] = null;
	$config["general"]["captcha"]["secret_key"] = null;
	
	$config["users"]["capcodes"]["admin"]["desc"] = "This user is an administrator.";
	$config["users"]["capcodes"]["mod"]["desc"] = "This user is a moderator.";
	
	
	$config["boards"]["*"]["max_length_comment"] = 10000;
	$config["boards"]["*"]["poster_public_ids"]["enabled"] = false;
	$config["boards"]["*"]["poster_country_flags"]["enabled"] = false;
	$config["boards"]["*"]["permissions"]["see_board"] = $config["users"]["roles"]["admin"];
	$config["boards"]["*"]["permissions"]["access_board"] = $config["users"]["roles"]["admin"];
	
	$config["boards"]["admin"]["permissions"]["see_board"] = $config["users"]["roles"]["default"];
	$config["boards"]["admin"]["permissions"]["access_board"] = $config["users"]["roles"]["admin"];
	
	$config["boards"]["test"]["permissions"]["see_board"] = $config["users"]["roles"]["default"];
	$config["boards"]["test"]["permissions"]["access_board"] = $config["users"]["roles"]["default"];
	$config["boards"]["test"]["permissions"]["post_thread"] = $config["users"]["roles"]["default"];
	$config["boards"]["test"]["permissions"]["post_reply"] = $config["users"]["roles"]["default"];
	$config["boards"]["test"]["permissions"]["post_without_captcha"] = $config["users"]["roles"]["default"];
};

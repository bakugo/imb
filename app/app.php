<?php

###########################
# imb imageboard software #
###########################

define("APP_NAME", "imb"); # imb/Yousuke
define("APP_VERSION", "1.0.0");
define("APP_MIN_PHP_VER", "5.6.0");
define("APP_MIN_PHP_EXTS", "pdo,pdo_mysql,mbstring,fileinfo,json,curl,gd");

#call_user_func(function () {
	if(version_compare(PHP_VERSION, APP_MIN_PHP_VER, "<")) {
		$error = (APP_NAME . " requires PHP version " . APP_MIN_PHP_VER . " or newer to run (your version: " . PHP_VERSION . ")");
		
		if(PHP_SAPI === "cli") {
			throw new Exception($error);
		} else {
			header("HTTP/1.1 500 Internal Server Error");
			header("Content-Type: text/plain");
			echo ("[Error] " . $error . "\n");
		}
		
		exit;
	}
#});

call_user_func(function () {
	$missingext = [];
	
	foreach(explode(",", APP_MIN_PHP_EXTS) as $ext) {
		if(!extension_loaded($ext)) {
			$missingext[] = $ext;
		}
	}
	
	if(count($missingext)) {
		$error = (APP_NAME . " requires the following missing PHP extensions: " . implode(", ", $missingext));
		
		if(PHP_SAPI === "cli") {
			throw new Exception($error);
		} else {
			header("HTTP/1.1 500 Internal Server Error");
			header("Content-Type: text/plain");
			echo ("[Error] " . $error . "\n");
		}
		
		exit;
	}
});

define("BASEFILE", __FILE__);
define("BASEPATH", __DIR__);
define("NEWLINE", "\n");

$basepath = BASEPATH;
chdir($basepath);

require_once("{$basepath}/b4k.php");
require_once("{$basepath}/vendor/autoload.php");
require_once("{$basepath}/includes/start.php");
require_once("{$basepath}/includes/constants.php");
require_once("{$basepath}/includes/exceptions.php");
require_once("{$basepath}/includes/cache.php");
require_once("{$basepath}/includes/config.php");
require_once("{$basepath}/includes/database.php");
require_once("{$basepath}/includes/twig.php");
require_once("{$basepath}/includes/ftp.php");
require_once("{$basepath}/includes/login.php");
require_once("{$basepath}/includes/users.php");
require_once("{$basepath}/includes/boards.php");
require_once("{$basepath}/includes/files.php");
require_once("{$basepath}/includes/bans.php");
require_once("{$basepath}/includes/bbcode.php");
require_once("{$basepath}/includes/geoip.php");
require_once("{$basepath}/includes/captcha.php");
require_once("{$basepath}/includes/loaders.php");
require_once("{$basepath}/includes/processors.php");
require_once("{$basepath}/includes/renderers.php");
require_once("{$basepath}/includes/templateutils.php");
require_once("{$basepath}/includes/json.php");
require_once("{$basepath}/includes/misc.php");
require_once("{$basepath}/includes/web.php");
require_once("{$basepath}/includes/routes.php");
require_once("{$basepath}/includes/routes_controlpanel.php");
require_once("{$basepath}/includes/routes_controlpanel_user.php");
require_once("{$basepath}/includes/routes_controlpanel_admin.php");
require_once("{$basepath}/includes/routes_controlpanel_mod.php");
require_once("{$basepath}/includes/routes_post.php");
require_once("{$basepath}/includes/routes_board.php");
require_once("{$basepath}/includes/end.php");

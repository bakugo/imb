<?php

$twig = null;

function twig_init() {
	global $basepath;
	global $twig;
	
	if($twig) {
		return;
	}
	
	$loader = new Twig_Loader_Chain([
		new Twig_Loader_Filesystem([
			"{$basepath}/templates",
			"{$basepath}/config"
		])
	]);
	
	$environment = new Twig_Environment($loader, [
		"debug" => false,
		"charset" => "utf-8",
		"base_template_class" => "Twig_Template",
		"cache" => "{$basepath}/data/cache/twig",
		"auto_reload" => true,
		"strict_variables" => false,
		"autoescape" => "html",
		"optimizations" => Twig_NodeVisitor_Optimizer::OPTIMIZE_ALL
	]);
	
	$twig = $environment;
	
	twig_set_globals();
	twig_add_functions();
}

function twig_set_globals() {
	global $twig;
	global $config;
	global $boards;
	global $user;
	
	if(!$twig) {
		return;
	}
	
	$globals = [
		"app" => [
			"name" => APP_NAME,
			"version" => APP_VERSION,
		],
		
		"config" => &$config,
		"boards" => &$boards,
		"user" => &$user,
		
		"script_settings" => [
			"enableTwemoji" => false,
			"enableThreadUpdater" => true,
			"enableDynamicPostForm" => false,
			"enableThreadStats" => true
		]
	];
	
	foreach($globals as $key => &$value) {
		$twig->addGlobal($key, $value);
	}
}

function twig_add_functions() {
	global $twig;
	
	if(!$twig) {
		return;
	}
	
	$functions = [
		new Twig_SimpleFunction("var", function($var) {
			return $GLOBALS[$var];
		}),
		
		new Twig_SimpleFunction("const", function($const) {
			return constant($const);
		}),
		
		new Twig_SimpleFunction("func", function($func, $args = []) {
			return call_user_func_array($func, $args);
		}),
		
		new Twig_SimpleFunction("url", function($path, $is_asset = false) {
			return url($path, $is_asset);
		}),
		
		new Twig_SimpleFunction("lang", function($string) {
			return lang($string);
		})
	];
	
	foreach($functions as &$function) {
		$twig->addFunction($function);
	}
}

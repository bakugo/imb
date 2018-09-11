<?php

# This isn't used anymore, static vars are better :^)

$cache = [];

function cache_set($key, $value = null) {
	global $cache;
	
	$key = cache_process_key($key);
	
	$cache[$key] = $value;
}

function cache_get($key) {
	global $cache;
	
	$key = cache_process_key($key);
	
	$value = $cache[$key];
	
	return (($value !== null) ? $value : null);
}

function cache_process_key($key) {
	return md5(serialize($key));
}

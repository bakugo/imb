<?php

$geoip_reader = null;

function geoip_init() {
	global $basepath;
	global $geoip_reader;
	
	if($geoip_reader === null) {
		$geoip_dbpath = "{$basepath}/resources/geoip2/GeoLite2-Country.mmdb";
		
		if(file_exists($geoip_dbpath)) {
			$geoip_reader = new GeoIp2\Database\Reader($geoip_dbpath);
		}
	}
	
	return ($geoip_reader !== null);
}

function geoip_country($ip) {
	global $geoip_reader;
	
	geoip_init();
	
	if($geoip_reader !== null) {
		try {
			$record = $geoip_reader->country($ip);
			
			return [
				"name" => $record->country->name,
				"code" => $record->country->isoCode
			];
		} catch(Exception $e) { }
	}
	
	return null;
}

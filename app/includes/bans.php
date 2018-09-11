<?php

function get_bans($time, $ip = null, $onlymustsee = false, $onlyactive = false) {
	global $db;
	
	if($ip !== null) {
		$query = $db->prepare("SELECT * FROM bans WHERE (ip = :ip) ORDER BY time DESC");
		$query->bindValue(":ip", $ip);
	} else {
		$query = $db->prepare("SELECT * FROM bans ORDER BY time DESC");
	}
	
	$query->execute();
	
	$bans = $query->fetchAll(PDO::FETCH_ASSOC);
	
	foreach($bans as &$ban) {
		process_ban($ban, $time);
	}
	
	if($onlymustsee || $onlyactive) {
		foreach($bans as $i => &$ban) {
			if(($onlymustsee && !$ban["mustsee"]) || ($onlyactive && !$ban["active"])) {
				unset($bans[$i]);
				continue;
			}
		}
		
		$bans = array_values($bans);
	}
	
	return $bans;
}

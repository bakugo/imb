<?php

function route_controlpanel_admin($path) {
	if($path === "/manage-users") {
		return true;
	}
	
	if($path === "/manage-boards") {
		return true;
	}
	
	return false;
}

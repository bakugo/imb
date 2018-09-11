<?php

function route_controlpanel_user($path) {
	if($path === "/messages") {
		return true;
	}
	
	if($path === "/my-posts") {
		return true;
	}
	
	if($path === "/settings") {
		return true;
	}
	
	if($path === "/change-pass") {
		return true;
	}
	
	return false;
}

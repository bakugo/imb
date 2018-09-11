<?php

$timestart = get_time_sec_float();

$httpvars = null;

config_load();
setup_shutdown_func();
db_connect();
get_all_boards();

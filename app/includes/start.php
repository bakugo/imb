<?php

ini_set("error_reporting", (E_ALL & ~E_NOTICE & ~E_STRICT & ~E_DEPRECATED));
ini_set("display_errors", false);
ini_set("memory_limit", "32M");
ini_set("max_execution_time", ((PHP_SAPI === "cli") ? 0 : 20));
ini_set("ignore_user_abort", true);
ini_set("date.timezone", "UTC");

ini_set("log_errors", true);
ini_set("error_log", "{$basepath}/logs/php-errors.log");

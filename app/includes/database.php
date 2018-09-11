<?php

$db = null;
$db_charset = null;
$db_collate = null;

function db_connect() {
	global $config;
	global $db;
	global $db_charset;
	global $db_collate;
	
	if($db) {
		return true;
	}
	
	$db_charset = "utf8mb4";
	$db_collate = "utf8mb4_unicode_ci";
	
	$host = $config["database"]["host"];
	$user = $config["database"]["user"];
	$pass = $config["database"]["pass"];
	$dbname = $config["database"]["dbname"];
	
	try {
		$db = new PDO("mysql:host={$host};dbname={$dbname}", $user, $pass);
	} catch(Exception $exception) {
		error(500, "Database connection failed");
	}
	
	$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
	$db->setAttribute(PDO::ATTR_STRINGIFY_FETCHES, false);
	
	$db->query("SET NAMES {$db_charset}");
	
	db_create_tables();
	
	return true;
}

function db_create_tables($force = false) {
	global $db;
	global $db_charset;
	global $db_collate;
	
	if($db->query("SHOW TABLES")->rowCount() > 0) {
		if(!$force) {
			return;
		}
	}
	
	$queries = [];
	
	$queries[] = "DROP TABLE IF EXISTS users";
	$queries[] = "DROP TABLE IF EXISTS boards";
	$queries[] = "DROP TABLE IF EXISTS threads";
	$queries[] = "DROP TABLE IF EXISTS posts";
	$queries[] = "DROP TABLE IF EXISTS files";
	$queries[] = "DROP TABLE IF EXISTS bans";
	
	$queries[] = "CREATE TABLE users (" .
		"id INT UNSIGNED AUTO_INCREMENT NOT NULL," .
		"username VARCHAR(128) NOT NULL," .
		"password VARCHAR(1024) NOT NULL," .
		"level INT UNSIGNED NOT NULL DEFAULT 0," .
		"boards TEXT NOT NULL," .
		"enabled TINYINT NOT NULL DEFAULT 0," .
		"PRIMARY KEY (id)," .
		"UNIQUE username (username)," .
		"INDEX level (level)," .
		"INDEX enabled (enabled)" .
		") ENGINE = InnoDB DEFAULT CHARSET = {$db_charset} COLLATE = {$db_collate};";
	
	$queries[] = "CREATE TABLE boards (" .
		"id INT UNSIGNED AUTO_INCREMENT NOT NULL," .
		"uri VARCHAR(128) NOT NULL," .
		"title VARCHAR(128) NOT NULL," .
		"subtitle VARCHAR(1024) DEFAULT NULL," .
		"locked TINYINT NOT NULL DEFAULT 0," .
		"post_number INT UNSIGNED NOT NULL DEFAULT 0," .
		"PRIMARY KEY (id)," .
		"UNIQUE uri (uri)" .
		") ENGINE = InnoDB DEFAULT CHARSET = {$db_charset} COLLATE = {$db_collate};";
	
	$queries[] = "CREATE TABLE threads (" .
		"id INT UNSIGNED AUTO_INCREMENT NOT NULL," .
		"board VARCHAR(128) NOT NULL," .
		"number INT UNSIGNED NOT NULL," .
		"time_bumped BIGINT UNSIGNED NOT NULL," .
		"time_modified BIGINT UNSIGNED NOT NULL," .
		"stickied  INT UNSIGNED NOT NULL DEFAULT 0," .
		"locked TINYINT NOT NULL DEFAULT 0," .
		"bumplocked TINYINT NOT NULL DEFAULT 0," .
		"archived TINYINT NOT NULL DEFAULT 0," .
		"PRIMARY KEY (id)," .
		"INDEX board (board)," .
		"INDEX number (number)," .
		"UNIQUE board__number (board, number)," .
		"INDEX time_bumped (time_bumped)," .
		"INDEX time_modified (time_modified)," .
		"INDEX stickied (stickied)," .
		"INDEX locked (locked)," .
		"INDEX bumplocked (bumplocked)," .
		"INDEX archived (archived)" .
		") ENGINE = InnoDB DEFAULT CHARSET = {$db_charset} COLLATE = {$db_collate};";
	
	$queries[] = "CREATE TABLE posts (" .
		"id INT UNSIGNED AUTO_INCREMENT NOT NULL," .
		"board VARCHAR(128) NOT NULL," .
		"number INT UNSIGNED NOT NULL," .
		"thread INT UNSIGNED NOT NULL," .
		"time_posted BIGINT UNSIGNED NOT NULL," .
		"time_modified BIGINT UNSIGNED NOT NULL," .
		"ip VARCHAR(128) DEFAULT NULL," .
		"user INT UNSIGNED DEFAULT NULL," .
		"name VARCHAR(128) DEFAULT NULL," .
		"tripcode VARCHAR(128) DEFAULT NULL," .
		"capcode VARCHAR(128) DEFAULT NULL," .
		"subject VARCHAR(128) DEFAULT NULL," .
		"comment TEXT DEFAULT NULL," .
		"sage TINYINT NOT NULL DEFAULT 0," .
		"enable_html TINYINT NOT NULL DEFAULT 0," .
		"public_ban TINYINT NOT NULL DEFAULT 0," .
		"shadow TINYINT NOT NULL DEFAULT 0," .
		"deleted TINYINT NOT NULL DEFAULT 0," .
		"PRIMARY KEY (id)," .
		"INDEX board (board)," .
		"INDEX number (number)," .
		"UNIQUE board__number (board, number)," .
		"INDEX thread (thread)," .
		"INDEX board__reply_to (board, thread)," .
		"UNIQUE board__number__thread (board, number, thread)," .
		"UNIQUE board__thread__number (board, thread, number)," .
		"INDEX time_posted (time_posted)," .
		"INDEX time_modified (time_modified)," .
		"INDEX ip (ip)," .
		"INDEX user (user)," .
		"INDEX name (name)," .
		"INDEX tripcode (tripcode)," .
		"INDEX name__tripcode (name, tripcode)," .
		"INDEX capcode (capcode)," .
		"INDEX subject (subject)," .
		"FULLTEXT comment (comment)," .
		"INDEX sage (sage)," .
		"INDEX enable_html (enable_html)," .
		"INDEX public_ban (public_ban)," .
		"INDEX shadow (shadow)," .
		"INDEX deleted (deleted)" .
		") ENGINE = InnoDB DEFAULT CHARSET = {$db_charset} COLLATE = {$db_collate};";
	
	$queries[] = "CREATE TABLE files (" .
		"id INT UNSIGNED AUTO_INCREMENT NOT NULL," .
		"board VARCHAR(128) NOT NULL," .
		"number INT UNSIGNED NOT NULL," .
		"md5 VARCHAR(128) NOT NULL," .
		"extension VARCHAR(128) NOT NULL," .
		"filesize INT UNSIGNED NOT NULL," .
		"dimensions VARCHAR(64) DEFAULT NULL," .
		"duration FLOAT UNSIGNED DEFAULT NULL," .
		"filename VARCHAR(256) NOT NULL," .
		"spoiler TINYINT NOT NULL DEFAULT 0," .
		"deleted TINYINT NOT NULL DEFAULT 0," .
		"PRIMARY KEY (id)," .
		"INDEX board (board)," .
		"INDEX number (number)," .
		"UNIQUE board__number (board, number)," .
		"INDEX md5 (md5)," .
		"INDEX extension (extension)," .
		"INDEX filesize (filesize)," .
		"FULLTEXT filename (filename)," .
		"INDEX spoiler (spoiler)," .
		"INDEX deleted (deleted)" .
		") ENGINE = InnoDB DEFAULT CHARSET = {$db_charset} COLLATE = {$db_collate};";
	
	$queries[] = "CREATE TABLE bans (" .
		"id INT UNSIGNED AUTO_INCREMENT NOT NULL," .
		"ip VARCHAR(128) DEFAULT NULL," .
		"time BIGINT UNSIGNED NOT NULL," .
		"creator INT UNSIGNED," .
		"post INT UNSIGNED," .
		"type VARCHAR(128) NOT NULL DEFAULT 'bantemp'," .
		"length BIGINT UNSIGNED DEFAULT 86400000," .
		"reason TEXT DEFAULT NULL," .
		"seen TINYINT NOT NULL DEFAULT 0," .
		"PRIMARY KEY (id)," .
		"INDEX ip (ip)," .
		"INDEX time (time)," .
		"INDEX creator (creator)," .
		"INDEX post (post)," .
		"INDEX type (type)," .
		"INDEX length (length)," .
		"FULLTEXT reason (reason)," .
		"INDEX seen (seen)" .
		") ENGINE = InnoDB DEFAULT CHARSET = {$db_charset} COLLATE = {$db_collate};";
	
	b4k::set_header("content-type", "text-plain");
	
	echo ("creating tables" . NEWLINE);
	echo ("queries: " . NEWLINE);
	
	$db->beginTransaction();
	
	foreach($queries as $query) {
		echo ("\t{$query}" . NEWLINE);
		
		try {
			$db->exec($query);
		} catch(Exception $e) {
			echo ($e->getMessage() . NEWLINE);
			
			exit;
		}
	}
	
	$db->commit();
	
	echo ("tables created". NEWLINE);
	
	db_seed();
	
	echo ("database seeded" . NEWLINE);
	
	exit;
}

function db_seed() {
	global $db;
	
	$db->beginTransaction();
	
	$query = $db->prepare("INSERT INTO users (username,password,level,boards,enabled) VALUES (:username,:password,:level,:boards,:enabled)");
	$query->bindValue(":username", CONSTANTS::USER_INITIAL_USERNAME);
	$query->bindValue(":password", login_password_hash(CONSTANTS::USER_INITIAL_PASSWORD));
	$query->bindValue(":level", CONSTANTS::USER_INITIAL_LEVEL);
	$query->bindValue(":boards", "*");
	$query->bindValue(":enabled", (int)true);
	$query->execute();
	
	$query = $db->prepare("INSERT INTO boards (uri,title,subtitle) VALUES (:uri,:title,:subtitle)");
	$query->bindValue(":uri", "admin");
	$query->bindValue(":title", "Administration");
	$query->bindValue(":subtitle", "A private board accessible only to moderators and administrators.");
	$query->execute();
	
	$query = $db->prepare("INSERT INTO boards (uri,title,subtitle) VALUES (:uri,:title,:subtitle)");
	$query->bindValue(":uri", "test");
	$query->bindValue(":title", "Testing");
	$query->bindValue(":subtitle", ("A public board for testing."));
	$query->execute();
	
	$db->commit();
}

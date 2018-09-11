<?php

function load_post_file(&$post) {
	load_posts_files($posts = [&$post]);
}

function load_posts_files(&$posts) {
	global $db;
	
	if(!count($posts)) {
		return;
	}
	
	$wheres = [];
	
	foreach($posts as $i => $post) {
		$wheres[] = ("board = " . $db->quote($post["board"]) . " AND number = " . (int)$post["number"]);
	}
	
	$query = $db->prepare("SELECT * FROM files FORCE INDEX (board__number) WHERE (" . implode(") OR (", $wheres) . ") ORDER BY id");
	$query->execute();
	
	$files = $query->fetchAll(PDO::FETCH_ASSOC);
	
	foreach($files as &$file) {
		process_post_file($file);
		
		foreach($posts as &$post) {
			if($post["board"] === $file["board"] && $post["number"] === $file["number"]) {
				$post["file"] = $file;
			}
		}
	}
}

function load_threads_posts(&$threads, $include_last_replies) {
	global $db;
	
	if(!count($threads)) {
		return;
	}
	
	$posts = null;
	$replies = null;
	
	$wheres = [];
	
	foreach($threads as $i => $thread) {
		$wheres[] = ("board = " . $db->quote($thread["board"]) . " AND number = " . (int)$thread["number"]);
	}
	
	$query = $db->prepare("SELECT * FROM posts FORCE INDEX (board__number) WHERE (" . implode(") OR (", $wheres) . ") ORDER BY number");
	$query->execute();
	
	$posts = $query->fetchAll(PDO::FETCH_ASSOC);
	
	foreach($posts as &$post) {
		process_post($post);
		
		foreach($threads as &$thread) {
			if($post["number"] === $thread["number"]) {
				$thread["post"] = $post;
			}
		}
	}
	
	if($include_last_replies) {
		foreach($threads as &$thread) {
			$query = $db->prepare("SELECT * FROM posts FORCE INDEX (board__thread) WHERE (board = :board AND thread = :thread AND number != thread) ORDER BY number DESC LIMIT " . (int)CONSTANTS::BOARD_INDEX_SHOWN_REPLIES_MAX);
			$query->bindValue(":board", $thread["board"]);
			$query->bindValue(":thread", $thread["number"]);
			$query->execute();
			
			$replies = $query->fetchAll(PDO::FETCH_ASSOC);
			
			foreach($replies as &$reply) {
				process_post($reply);
			}
			
			$thread["replies"] = array_reverse($replies);
		}
	}
}

function load_thread_stats(&$thread) {
	global $db;
	
	$query = $db->prepare("SELECT COUNT(posts.id) AS posts, COUNT(files.id) as files, COUNT(DISTINCT(posts.ip)) AS posters FROM posts FORCE INDEX(board__thread__number) LEFT JOIN files FORCE INDEX(board__number) ON (posts.board = files.board AND posts.number = files.number) WHERE (posts.board = :board AND posts.thread = :number)");
	$query->bindValue(":board", $thread["board"]);
	$query->bindValue(":number", $thread["number"]);
	$query->execute();
	
	$thread["stats"] = $query->fetch(PDO::FETCH_ASSOC);
	$thread["stats"] = process_thread_stats($thread["stats"]);
}

function load_threads_stats(&$threads) {
	if(!count($threads)) {
		return;
	}
	
	foreach($threads as &$thread) {
		load_thread_stats($thread);
	}
}

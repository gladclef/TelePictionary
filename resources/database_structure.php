<?php

$a_basic_tables_structure = array(
	"images" => array(
		"id" =>                    array("type" => "INT",           "indexed" => TRUE,  "isPrimaryKey" => TRUE,  "special" => "AUTO_INCREMENT"),
		"alias" =>                 array("type" => "INT",           "indexed" => TRUE,  "isPrimaryKey" => FALSE, "special" => ""),
		"extension" =>             array("type" => "VARCHAR(10)",   "indexed" => TRUE,  "isPrimaryKey" => FALSE, "special" => ""),
		"roomCode" =>              array("type" => "VARCHAR(255)",  "indexed" => TRUE,  "isPrimaryKey" => FALSE, "special" => ""),
		"storyId" =>               array("type" => "INT",           "indexed" => FALSE, "isPrimaryKey" => FALSE, "special" => "")
	),
	"cards" => array(
		"id" =>                    array("type" => "INT",           "indexed" => TRUE,  "isPrimaryKey" => TRUE,  "special" => "AUTO_INCREMENT"),
		"roomCode" =>              array("type" => "VARCHAR(255)",  "indexed" => TRUE,  "isPrimaryKey" => FALSE, "special" => ""),
		"storyId" =>               array("type" => "INT",           "indexed" => FALSE, "isPrimaryKey" => FALSE, "special" => ""),
		"authorId" =>              array("type" => "INT",           "indexed" => FALSE, "isPrimaryKey" => FALSE, "special" => ""), // player id that authored this card
		"type" =>                  array("type" => "TINYINT",       "indexed" => FALSE, "isPrimaryKey" => FALSE, "special" => ""), // 1 for sentence round, 0 for drawing round
		"text" =>                  array("type" => "VARCHAR(4096)", "indexed" => FALSE, "isPrimaryKey" => FALSE, "special" => ""),
		"imageId" =>               array("type" => "INT",           "indexed" => FALSE, "isPrimaryKey" => FALSE, "special" => ""),
		"isRevealed" =>            array("type" => "TINYINT",       "indexed" => FALSE, "isPrimaryKey" => FALSE, "special" => "")
	),
	"stories" => array(
		"id" =>                    array("type" => "INT",           "indexed" => TRUE,  "isPrimaryKey" => TRUE,  "special" => "AUTO_INCREMENT"),
		"roomCode" =>              array("type" => "VARCHAR(255)",  "indexed" => TRUE,  "isPrimaryKey" => FALSE, "special" => ""),
		"name" =>                  array("type" => "VARCHAR(1024)", "indexed" => FALSE, "isPrimaryKey" => FALSE, "special" => ""),
		"playerId" =>              array("type" => "INT",           "indexed" => FALSE, "isPrimaryKey" => FALSE, "special" => ""), // starting player
		"cardIds" =>               array("type" => "VARCHAR(1024)", "indexed" => FALSE, "isPrimaryKey" => FALSE, "special" => "")
	),
	"games" => array(
		"id" =>                    array("type" => "INT",           "indexed" => TRUE,  "isPrimaryKey" => TRUE,  "special" => "AUTO_INCREMENT"),
		"name" =>                  array("type" => "VARCHAR(255)",  "indexed" => TRUE,  "isPrimaryKey" => FALSE, "special" => ""),
		"roomCode" =>              array("type" => "VARCHAR(255)",  "indexed" => TRUE,  "isPrimaryKey" => FALSE, "special" => ""),
		"playerIds" =>             array("type" => "VARCHAR(1024)", "indexed" => FALSE, "isPrimaryKey" => FALSE, "special" => ""),
		"playerOrder" =>           array("type" => "VARCHAR(1024)", "indexed" => FALSE, "isPrimaryKey" => FALSE, "special" => ""),
		"startTime" =>             array("type" => "DATETIME",      "indexed" => FALSE, "isPrimaryKey" => FALSE, "special" => ""),
		"cardStartType" =>         array("type" => "TINYINT",       "indexed" => FALSE, "isPrimaryKey" => FALSE, "special" => ""), // what card type to start the game with
		"player1Id" =>             array("type" => "INT",           "indexed" => FALSE, "isPrimaryKey" => FALSE, "special" => ""), // which player owns this game
		"drawTimerLen" =>          array("type" => "INT",           "indexed" => FALSE, "isPrimaryKey" => FALSE, "special" => ""), // how many seconds a drawing turn is limited to
		"textTimerLen" =>          array("type" => "INT",           "indexed" => FALSE, "isPrimaryKey" => FALSE, "special" => ""), // how many seconds a sentence turn is limited to
		"turnStart" =>             array("type" => "DATETIME",      "indexed" => FALSE, "isPrimaryKey" => FALSE, "special" => ""), // when this turn started, used to determine how much time is left on the timer
		"currentTurn" =>           array("type" => "INT",           "indexed" => FALSE, "isPrimaryKey" => FALSE, "special" => "") // the current turn, 0 indexed, -1 when the game hasn't started
	),
	"players" => array(
		"id" =>                    array("type" => "INT",           "indexed" => TRUE,  "isPrimaryKey" => TRUE,  "special" => "AUTO_INCREMENT"),
		"name" =>                  array("type" => "VARCHAR(255)",  "indexed" => FALSE, "isPrimaryKey" => FALSE, "special" => ""),
		"roomCode" =>              array("type" => "VARCHAR(255)",  "indexed" => TRUE,  "isPrimaryKey" => FALSE, "special" => ""),
		"storyId" =>               array("type" => "INT",           "indexed" => FALSE, "isPrimaryKey" => FALSE, "special" => ""),
		"gameIds" =>               array("type" => "VARCHAR(1024)", "indexed" => FALSE, "isPrimaryKey" => FALSE, "special" => "") // past games that have been played and not yet timed out, and the current game
	)
);
$a_database_insert_values = array();
//require_once(DIRNAME(__FILE__)."/common_functions.php");
//error_log_array($a_basic_tables_structure);

?>
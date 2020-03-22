<?php
$a_basic_tables_structure = array(
	"images" => array(
		"id" =>                    array("type" => "INT",           "indexed" => TRUE,  "isPrimaryKey" => TRUE,  "special" => "AUTO_INCREMENT"),
		"alias" =>                 array("type" => "INT",           "indexed" => TRUE,  "isPrimaryKey" => FALSE, "special" => ""),
		"game" =>                  array("type" => "VARCHAR(255)",  "indexed" => TRUE,  "isPrimaryKey" => FALSE, "special" => ""),
		"story" =>                 array("type" => "VARCHAR(1024)", "indexed" => FALSE, "isPrimaryKey" => FALSE, "special" => "")
	)
);
$a_database_insert_values = array();
?>
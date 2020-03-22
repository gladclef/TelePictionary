<?php

require_once(dirname(__FILE__).'/../resources/db_query.php');
require_once(dirname(__FILE__).'/../resources/globals.php');
require_once(dirname(__FILE__).'/../resources/database_structure.php');

// check for image existance
$s_imgalias = get_get_var("alias", null);
$s_fileNewPath = dirname(__FILE__).'/../../userImages/'.$s_imgalias;
if ($s_imgalias == null || !file_exists($s_fileNewPath)) {
	http_response_code(404);
	return "";
}

// return the image
$s_file_extension = pathinfo($s_imgalias, PATHINFO_EXTENSION);
$s_file_extension = ($s_file_extension == "") ? "jpeg" : $s_file_extension;
$binary_image = file_get_contents($s_fileNewPath);
$i_imagesize = filesize($s_fileNewPath);
$s_file_extension = "png";
header('Content-type: image/'.$s_file_extension.';');
header("Content-Length: " . $i_imagesize);
echo $binary_image;

?>
<?php

require_once(dirname(__FILE__)."/globals.php");

function get_get_var($getname, $s_default = '') {
	return isset($_POST[$getname]) ? $_POST[$getname] : $s_default;
}

function get_post_var($postname, $s_default = '') {
	return isset($_POST[$postname]) ? $_POST[$postname] : $s_default;
}

function my_session_start() {
	global $session_started;

	// error_log("starting session");

	if ($session_started === FALSE) {
		$i_sessionTime = 60*60*24*7; // 7 days
		// server should keep session data for AT LEAST session time
		//error_log(ini_get('session.gc_maxlifetime')); // we set this in the global php.ini file
		// each client should remember their session id for EXACTLY session time
		session_set_cookie_params($i_sessionTime);

		// start the session
		session_start();

		// remember that the session was started
		$session_started = TRUE;
	}
}

// Get the full URL used to access this script
// from http://webcheatsheet.com/php/get_current_page_url.php
function curPageURL() {
	$pageURL = stripos($_SERVER['SERVER_PROTOCOL'],'https') === true ? 'https://' : 'http://';
	if ($_SERVER["SERVER_PORT"] != "80") {
		$pageURL .= $_SERVER["SERVER_NAME"].":".$_SERVER["SERVER_PORT"].$_SERVER["REQUEST_URI"];
	} else {
		$pageURL .= $_SERVER["SERVER_NAME"].$_SERVER["REQUEST_URI"];
	}
	return $pageURL;
}

// adds the last modified datetime of the javascript file to the src as a get var
// example usage:
// $s_includes = append_js_timestamps('<script type="text/javascript" src="js/jquery.qrcode.min.js"></script>')
// returns string '<script type="text/javascript" src="js/jquery.qrcode.min.js?2020-05-01+11%3A35%3A28"></script>'
function append_js_timestamps($s_output) {
	global $filesystem_root;
	
	// insert the latest datetime stamp into each javascript link
	$parts_explode = "<script type=";
	$a_parts = explode($parts_explode, $s_output);
	for ($i = 0; $i < count($a_parts); $i++) {
			$mid_explode = "</script";
			$a_mid = explode($mid_explode, $a_parts[$i]);
			$mid_index = 0;
			$s_mid = $a_mid[$mid_index];
			$js_pos = stripos($s_mid, ".js");
			$moddatetime = "";
			if ($js_pos !== FALSE) {
					$js_string = substr($s_mid, 0, $js_pos+3);
					$js_rest = substr($s_mid, $js_pos+3);
					$single_pos = (int)strrpos($js_string, "'");
					$double_pos = (int)strrpos($js_string, '"');
					$js_substr = substr($js_string, max($single_pos, $double_pos)+1);

					$s_fileLoc = dirname(__FILE__)."/../{$js_substr}";
					if (substr($js_substr, 0, 1) == "/") {
						$s_fileLoc = "{$filesystem_root}{$js_substr}";
					}

					$modtime = filemtime($s_fileLoc);
					$moddatetime = urlencode(date("Y-m-d H:i:s", $modtime));
					$a_mid[$mid_index] = "{$js_string}?{$moddatetime}{$js_rest}";
			}
			$a_parts[$i] = implode($mid_explode, $a_mid);
	}
	$s_output = implode($parts_explode, $a_parts);

	return $s_output;
}

// for pretty printing arrays to the error log for quick debugging
function error_log_array($a_output, $i_tab_level = 0) {
	$s_tab = str_repeat("  ", $i_tab_level);
	foreach ($a_output as $k=>$v) {
		if (is_object($v)) {
			$v = (array)$v;
		}
		if (is_array($v)) {
			error_log("{$s_tab}{$k}: array:   ****");
			error_log_array($v, $i_tab_level+1);
		} else {
			error_log("{$s_tab}{$k}: {$v}   ****");
		}
	}
}

// check if the string haystack ends with the string needle
function endsWith($haystack, $needle) {
    $length = strlen($needle);

    return $length === 0 || 
    (substr($haystack, -$length) === $needle);
}

// example usage:
// $a_ids = explodeIds('|1||2||5|', 'intval');
// returns array of integers: array(1, 2, 5)
function explodeIds($s_ids, $f_applyFunc = null) {
	if (!is_string($s_ids))
		return $s_ids;
	if ($s_ids == "")
		return array();
	$a_ids = explode("||", $s_ids);
	$a_ids = str_replace("|", "", $a_ids);
	if ($f_applyFunc !== null)
	{
		for ($i = 0; $i < count($a_ids); $i++)
		{
			$a_ids[$i] = $f_applyFunc($a_ids[$i]);
		}
	}
	return $a_ids;
}

// example usage:
// $s_ids = implodeIds( array(1, 2, 5) );
// return string '|1||2||5|'
function implodeIds($a_ids) {
	if (!is_array($a_ids) || count($a_ids) == 0) {
		return "";
	}
	return "|" . join("||", $a_ids) . "|";
}

function escapeTextVals($a_vals, $a_keys) {
	$a_vals_obj = new ArrayObject($a_vals);
	$a_vals2 = $a_vals_obj->getArrayCopy();
	foreach ($a_keys as $s_key) {
		$a_vals2[$s_key] = htmlspecialchars($a_vals2[$s_key]);
	}
	return $a_vals2;
}

// Iterates through a_array, extracting the value si_key from the child arrays.
// Returns the values as a new, collapsed array.
// Example usage:
// $a_weeklyLunches = getValuesOfInnerArraysByKey(array(
//     'SUN' => array(1200 => 'Stephen', 1700=>'Parents'),
//     'MON' => array(1200 => 'Marisa'),
//     'WED' => array(1200 => 'Wilson'),
//     'THR' => array(0900 => 'Thomas'),
//     'SAT' => array(0800 => 'Sam', 1200 => 'Sally')
// ), 1200);
// Returns array('Stephen', 'Marisa', 'Wilson', 'Sally')
function getValuesOfInnerArraysByKey($a_array, $si_key) {
	if (count($a_array) == 0)
		return array();

	$a_retval = array();
	foreach ($a_array as $a_inner_array) {
		if (!array_key_exists($si_key, $a_inner_array))
			continue;
		$a_retval[] = $a_inner_array[$si_key];
	}

	return $a_retval;
}

?>
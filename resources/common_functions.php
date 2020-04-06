<?php

require_once(dirname(__FILE__)."/globals.php");

function get_post_var($postname, $s_default = '') {
	return isset($_POST[$postname]) ? $_POST[$postname] : $s_default;
}

function my_session_start() {
	global $session_started;

	if ($session_started === FALSE) {
		$i_sessionTime = 60*60*24*7; // 7 days
		// server should keep session data for AT LEAST session time
		ini_set('session.gc_maxlifetime', $i_sessionTime);
		// each client should remember their session id for EXACTLY session time
		session_set_cookie_params($i_sessionTime);

		// start the session
		session_start();

		// remember that the session was started
		$session_started = TRUE;
	}
}

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

function endsWith($haystack, $needle) {
    $length = strlen($needle);

    return $length === 0 || 
    (substr($haystack, -$length) === $needle);
}

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
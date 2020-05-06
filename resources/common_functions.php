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

// from http://detectmobilebrowsers.com/
// Tested to obey the "request desktop site" from at least iPhone 7 plus running iOS 13.4.1.
function isMobileDevice() {
	$useragent=$_SERVER['HTTP_USER_AGENT'];

	$b_isMobile = (preg_match('/(android|bb\d+|meego).+mobile|avantgo|bada\/|blackberry|blazer|compal|elaine|fennec|hiptop|iemobile|ip(hone|od)|iris|kindle|lge |maemo|midp|mmp|netfront|opera m(ob|in)i|palm( os)?|phone|p(ixi|re)\/|plucker|pocket|psp|series(4|6)0|symbian|treo|up\.(browser|link)|vodafone|wap|windows (ce|phone)|xda|xiino/i',$useragent)||preg_match('/1207|6310|6590|3gso|4thp|50[1-6]i|770s|802s|a wa|abac|ac(er|oo|s\-)|ai(ko|rn)|al(av|ca|co)|amoi|an(ex|ny|yw)|aptu|ar(ch|go)|as(te|us)|attw|au(di|\-m|r |s )|avan|be(ck|ll|nq)|bi(lb|rd)|bl(ac|az)|br(e|v)w|bumb|bw\-(n|u)|c55\/|capi|ccwa|cdm\-|cell|chtm|cldc|cmd\-|co(mp|nd)|craw|da(it|ll|ng)|dbte|dc\-s|devi|dica|dmob|do(c|p)o|ds(12|\-d)|el(49|ai)|em(l2|ul)|er(ic|k0)|esl8|ez([4-7]0|os|wa|ze)|fetc|fly(\-|_)|g1 u|g560|gene|gf\-5|g\-mo|go(\.w|od)|gr(ad|un)|haie|hcit|hd\-(m|p|t)|hei\-|hi(pt|ta)|hp( i|ip)|hs\-c|ht(c(\-| |_|a|g|p|s|t)|tp)|hu(aw|tc)|i\-(20|go|ma)|i230|iac( |\-|\/)|ibro|idea|ig01|ikom|im1k|inno|ipaq|iris|ja(t|v)a|jbro|jemu|jigs|kddi|keji|kgt( |\/)|klon|kpt |kwc\-|kyo(c|k)|le(no|xi)|lg( g|\/(k|l|u)|50|54|\-[a-w])|libw|lynx|m1\-w|m3ga|m50\/|ma(te|ui|xo)|mc(01|21|ca)|m\-cr|me(rc|ri)|mi(o8|oa|ts)|mmef|mo(01|02|bi|de|do|t(\-| |o|v)|zz)|mt(50|p1|v )|mwbp|mywa|n10[0-2]|n20[2-3]|n30(0|2)|n50(0|2|5)|n7(0(0|1)|10)|ne((c|m)\-|on|tf|wf|wg|wt)|nok(6|i)|nzph|o2im|op(ti|wv)|oran|owg1|p800|pan(a|d|t)|pdxg|pg(13|\-([1-8]|c))|phil|pire|pl(ay|uc)|pn\-2|po(ck|rt|se)|prox|psio|pt\-g|qa\-a|qc(07|12|21|32|60|\-[2-7]|i\-)|qtek|r380|r600|raks|rim9|ro(ve|zo)|s55\/|sa(ge|ma|mm|ms|ny|va)|sc(01|h\-|oo|p\-)|sdk\/|se(c(\-|0|1)|47|mc|nd|ri)|sgh\-|shar|sie(\-|m)|sk\-0|sl(45|id)|sm(al|ar|b3|it|t5)|so(ft|ny)|sp(01|h\-|v\-|v )|sy(01|mb)|t2(18|50)|t6(00|10|18)|ta(gt|lk)|tcl\-|tdg\-|tel(i|m)|tim\-|t\-mo|to(pl|sh)|ts(70|m\-|m3|m5)|tx\-9|up(\.b|g1|si)|utst|v400|v750|veri|vi(rg|te)|vk(40|5[0-3]|\-v)|vm40|voda|vulc|vx(52|53|60|61|70|80|81|83|85|98)|w3c(\-| )|webc|whit|wi(g |nc|nw)|wmlb|wonu|x700|yas\-|your|zeto|zte\-/i',substr($useragent,0,4)));

	return $b_isMobile;
}

?>
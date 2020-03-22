<?php

global $o_access_object;
require_once(dirname(__FILE__).'/../../resources/db_query.php');
require_once(dirname(__FILE__).'/../../resources/globals.php');
require_once(dirname(__FILE__)."/../../resources/check_logged_in.php");
require_once(dirname(__FILE__).'/../../resources/database_structure.php');
require_once(dirname(__FILE__).'/campaign_funcs.php');
require_once(dirname(__FILE__)."/../login/access_object.php");
require_once(dirname(__FILE__)."/../../objects/command.php");
require_once(dirname(__FILE__).'/character_funcs.php');
require_once(dirname(__FILE__).'/welcome_funcs.php');

class user_ajax {
	public static function upload_file() {
		global $maindb;

		$s_game = get_post_var("game");
		$s_story = get_post_var("story");
		$s_round = get_post_var("round");
		$s_user = get_post_var("user");
		$s_fileOrigName = $_FILES['file']['name'];
		$s_fileTmpName = $_FILES['file']['tmp_name'];

		// verify the file extension and size
		$a_acceptableExtensions = array("jpg", "jpeg", "png", "gif", "bmp", "tiff");
		$s_file_extension = strtolower(pathinfo($s_fileOrigName, PATHINFO_EXTENSION));
		if (!in_array($s_file_extension, $a_acceptableExtensions)) {
			return json_encode(array(
				new command("print failure", "File type must be one of .".join(", .", $a_acceptableExtensions))));
		}
		$i_imagesize = filesize($s_fileTmpName);
		if ($i_imagesize > 3145728) {
			return json_encode(array(
				new command("print failure", "Image is too big.")));
		}

		// find a new name to use to save the file
		// move the file to that new name
		$a_imgvals = array(
			"database"=>$maindb,
			"table"=>"images",
			"alias"=>"",
			"game"=>$s_game,
			"story"=>$s_story,
			"round"=>$s_round,
			"user"=>$s_user
		);
		$i_maxcnt = 1000;
		$s_pathPrefix = dirname(__FILE__).'/../../../telePictionaryUserImages/';
		while ($i_maxcnt > 0)
		{
			$s_fileNewPath = $s_pathPrefix.rand(1000,1000000000).'.'.$s_file_extension;
			if (!file_exists($s_fileNewPath)) {
				$a_imgvals['alias'] = basename($s_fileNewPath);
				$ab_result = db_query("SELECT `alias` FROM `[database]`.`[table]` WHERE `alias`='[alias]'", $a_imgvals);
				if (is_array($ab_result) && sizeof($ab_result) === 0) {
					break;
				}
			}
			$i_maxcnt--;
		};
		$s_fileNewName = basename($s_fileNewPath);
		if ($i_maxcnt == 0) {
			return json_encode(array(
				new command("print failure", "Error finding good alias. Try again.")));
		}
		if (!move_uploaded_file($s_fileTmpName, $s_fileNewPath)) {
			return json_encode(array(
				new command("print failure", "Filesystem error")));
		}
		$s_value = $s_fileNewName;

		// get the current value
		$sb_retval = create_row_if_not_existing($a_imgvals);
		if ($sb_retval === TRUE) {
			// get the previous file name
			$a_requestvars = array(
				"database"=>$maindb,
				"table"=>$s_table,
				"column"=>$s_column,
				"id"=>$i_rowid
			);
			$a_fileOldName = db_query("SELECT `[column]` FROM `[database]`.`[table]` WHERE `id`='[id]'", $a_requestvars);
			$s_fileOldName = (sizeof($a_fileOldName) == 1 && isset($a_fileOldName[0]['portrait'])) ? $a_fileOldName[0]['portrait'] : "";

			// update to the new file name
			if ($s_table != "" && $s_table != "characters") {
				$sb_retval = campaign_funcs::update_table($s_table, $i_rowid, $s_column, $s_value);
			} else {
				$sb_retval = campaign_funcs::update_character($charid, $s_column, $s_value);
			}
		}
		if (is_string($sb_retval)) {
			return json_encode(array(
				new command("print failure", $sb_retval)));
		} else if (!$sb_retval) {
			return json_encode(array(
				new command("print failure", "Failed to update")));
		} else {
			// remove the old file
			if (isset($s_fileOldName) && $s_fileOldName !== "") {
				$a_imgvals['alias'] = $s_fileOldName;
				$s_fileOldPath = $s_pathPrefix.$s_fileOldName;
				db_query("DELETE FROM `[database]`.`[table]` WHERE `alias`='[alias]'", $a_imgvals);
				if (file_exists($s_fileOldPath)) { unlink($s_fileOldPath); }
			}

			// return success
			return json_encode(array(
				new command("print success", "Changes synched"),
				new command("run script", 'var tmp = $("#'.$s_imgtagid.'"); tmp.attr("actsrc", "'.$s_fileNewName.'"); updateImg(null, tmp);')));
		}
	}
}

if (!isset($_POST['command']))
	$_POST['command'] = 'nope';
if (isset($_POST['command'])) {
		$o_ajax = new user_ajax();
		$s_command = $_POST['command'];
		if (method_exists($o_ajax, $s_command)) {
				$s_response = user_ajax::$s_command();
				echo $s_response;
		} else {
				echo json_encode(array(
					'bad command'));
		}
}

?>
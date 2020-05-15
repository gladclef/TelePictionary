<?php

require_once(dirname(__FILE__) . "/resources/include.php");

if (isset($_GET['refresh']))
{
	// clear the database
	global $maindb;
	db_query("DELETE FROM `{$maindb}`.`games`");
	db_query("DELETE FROM `{$maindb}`.`players`");
	db_query("DELETE FROM `{$maindb}`.`stories`");
	db_query("DELETE FROM `{$maindb}`.`cards`");
	db_query("DELETE FROM `{$maindb}`.`images`");

	// delete all image files
	$files = glob(dirname(__FILE__) . '/../../telePictionaryUserImages/*');
	foreach($files as $file){ // iterate files
		if(is_file($file))
			unlink($file); // delete file
	}

	// clear the global player
	unset($o_globalPlayer);
	$o_globalPlayer = player::getGlobalPlayer();
}

?><!DOCTYPE html>
<html>
	<head>
		<meta content="text/html;charset=utf-8" http-equiv="Content-Type">
		<meta content="utf-8" http-equiv="encoding">

		<?php
		echo $s_includeScripts;
		echo $s_includeStylesheets;
		?>
		<script>
			<?php
			includeServerStats();
			?>

			a_toExec[a_toExec.length] = {
				"name": "controlCustomVals",
				"dependencies": ["outgoingMessenger"],
				"function": function() {
					// add special application-level data to every push-pull ajax request
					outgoingMessenger.customData = {
						playerId: serverStats['localPlayerId']
					};
				}
			};


			a_toExec[a_toExec.length] = {
				"name": "index.php",
				"dependencies": ["jQuery", "jqueryExtension.js", "commands.js", "playerFuncs", "game", "control.js", "reveal_overrides"],
				"function": function() {
					f_commonStartupJs();
				}
			};
		</script>
	</head>
	<body>
		<?php
		includeContents();
		includeGeneralError();
		?>
		<div class="firefoxPlaceholder">placeholder</div>
	</body>
</html>
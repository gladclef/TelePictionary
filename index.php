<?php

require_once(dirname(__FILE__) . "/resources/include.php");

if (isset($_GET['refresh']))
{
	global $maindb;
	db_query("DELETE FROM `{$maindb}`.`games`");
	db_query("DELETE FROM `{$maindb}`.`players`");
	db_query("DELETE FROM `{$maindb}`.`stories`");
	db_query("DELETE FROM `{$maindb}`.`cards`");
	db_query("DELETE FROM `{$maindb}`.`images`");
	unset($o_globalPlayer);
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
						playerId: serverStats['localPlayer']
					};
				}
			};


			a_toExec[a_toExec.length] = {
				"name": "index.php",
				"dependencies": ["jQuery", "jqueryExtension.js", "commands.js", "playerFuncs", "control.js"],
				"function": function() {
					// set some things
					playerFuncs.setLocalPlayer(serverStats['localPlayer']);
					game.setLocalPlayer(serverStats['localPlayer']);

					// show the content
					var s_content = "about";
					if (serverStats['isInGame']) {
						s_content = "game";
					}
					commands.showContent(s_content);
				}
			};
		</script>
	</head>
	<body>
		<div class="centered generalError"></div>
		<?php
		includeContents();
		?>
		<div class="firefoxPlaceholder">placeholder</div>
	</body>
</html>
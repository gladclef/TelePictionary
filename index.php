<?php

require_once(dirname(__FILE__) . "/resources/globals.php");
require_once(dirname(__FILE__) . "/resources/common_functions.php");
require_once(dirname(__FILE__) . "/objects/player.php");
require_once(dirname(__FILE__) . "/objects/game.php");

if (isset($_GET['refresh']))
{
	require_once(dirname(__FILE__) . "/resources/db_query.php");
	global $maindb;
	db_query("DELETE FROM `{$maindb}`.`games`");
	db_query("DELETE FROM `{$maindb}`.`players`");
	unset($o_globalPlayer);
}

global $o_globalPlayer;
$o_globalPlayer = player::getGlobalPlayer();

?><!DOCTYPE html>
<html>
	<head>
		<meta content="text/html;charset=utf-8" http-equiv="Content-Type">
		<meta content="utf-8" http-equiv="encoding">

		<script type="text/javascript" src="<?php echo $global_path_to_jquery; ?>"></script>
		<script type="text/javascript" src="<?php echo $global_path_to_d3; ?>"></script>
		<script type="text/javascript" src="communication/longPoll/pushPull.js"></script>
		<script type="text/javascript" src="js/common.js"></script>
		<script type="text/javascript" src="js/index.js"></script>
		<script type="text/javascript" src="js/javascriptExtension.js"></script>
		<script type="text/javascript" src="js/jqueryExtension.js"></script>
		<script type="text/javascript" src="js/toExec.js"></script>
		<script type="text/javascript" src="js/control.js"></script>
		<script type="text/javascript" src="js/commands.js"></script>
		<script type="text/javascript" src="js/player.js"></script>
		<script>
			if (window.a_toExec === undefined) window.a_toExec = [];
			if (window.serverStats === undefined) window.serverStats = {};

			<?php
			$b_hasUsername = $o_globalPlayer->getGameState()[0] > 0;
			$b_isInGame = ($b_hasUsername) ? ($o_globalPlayer->getGameState()[0] > 1) : false;
			$s_hasUsername = ($b_hasUsername) ? "true" : "false";
			$s_isInGame = ($b_isInGame) ? "true" : "false";
			echo "serverStats['latestEvents'] = []; // TODO get the latest 100 message events\r\n";
			echo "serverStats['hasUsername'] = {$s_hasUsername};\r\n";
			echo "serverStats['isInGame'] = {$s_isInGame};\r\n";
			?>

			a_toExec[a_toExec.length] = {
				"name": "index.php",
				"dependencies": ["jQuery", "jqueryExtension.js", "commands.js"],
				"function": function() {
					var s_content = "about";
					if (serverStats['isInGame']) {
						s_content = "game";
					}
					commands.showContent(s_content);
				}
			};
		</script>

		<link rel="stylesheet" type="text/css" href="css/common.css" />
	</head>
	<body>
		<div class="centered generalError"></div>
		<?php
		require_once(dirname(__FILE__) . "/pages/about.php");
		require_once(dirname(__FILE__) . "/pages/createUser.php");
		require_once(dirname(__FILE__) . "/pages/gameSetup.php");
		require_once(dirname(__FILE__) . "/pages/game.php");
		require_once(dirname(__FILE__) . "/pages/reveal.php");
		?>
		<div class="firefoxPlaceholder">placeholder</div>
	</body>
</html>
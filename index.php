<?php

require_once(dirname(__FILE__) . "/resources/globals.php");
require_once(dirname(__FILE__) . "/resources/common_functions.php");
require_once(dirname(__FILE__) . "/objects/player.php");

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
		<script>
			if (window.a_toExec === undefined) window.a_toExec = [];
			if (window.serverStats === undefined) window.serverStats = {};

			<?php
			$s_hasUsername = ($o_globalPlayer->getGameState()[0] > 0) ? "true" : "false";
			$s_isInGame = ($o_globalPlayer->getGameState()[0] > 1) ? "true" : "false";
			echo "serverStats['latestIndexes'] = []; // TODO get the latest 100 message indexes";
			echo "serverStats['hasUsername'] = {$s_hasUsername};";
			echo "serverStats['isInGame'] = {$s_isInGame};";
			?>

			a_toExec[a_toExec.length] = {
				"name": "index.php",
				"dependencies": ["jQuery", "jqueryExtension.js", "commands.js"],
				"function": function() {
					var a_content = location.href.match(/#.*/);
					var s_content = (a_content != null && a_content.length > 0) ? a_content[0] : "about";
					commands.setContent(s_content);
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
	</body>
</html>
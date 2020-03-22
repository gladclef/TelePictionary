<?php

require_once(dirname(__FILE__) . "/resources/globals.php");

?><!DOCTYPE html>
<html>
	<head>
		<script type="text/javascript" src="<?php echo $global_path_to_jquery; ?>"></script>
		<script type="text/javascript" src="<?php echo $global_path_to_d3; ?>"></script>
		<script type="text/javascript" src="control.js"></script>
		<script type="text/javascript" src="toExec.js"></script>
		<script type="text/javascript" src="communication/longPoll/pushPull.js"></script>
		<script>
			if (window.a_toExec === undefined) window.a_toExec = [];

			<?php
			echo "serverStats['latest_indexes'] = []; // TODO get the latest 100 message indexes";
			echo "serverStats['in_game'] = false; // TODO determine if the client is in the game"
			?>

			a_toExec[a_toExec.length] = {
				"name": "control.php",
				"dependencies": ["jQuery"],
				"function": function() {
					// TODO show login screen or game board
				}
			};
		</script>
	</head>
	<body>
		<svg id="canvas_container"></svg>
	</body>
</html>
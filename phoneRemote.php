<?php

require_once(dirname(__FILE__) . "/resources/include.php");

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
				"name": "index.php", // emulate the index.php value in order to get the game code to execute
				"dependencies": ["jQuery", "jqueryExtension.js", "commands.js", "playerFuncs", "control.js"],
				"function": function() {
					// set some things
					playerFuncs.setLocalPlayer(serverStats['localPlayer']);
					game.setLocalPlayer(serverStats['localPlayer']);

					// never show the desktop content
					commands.showContent = function() {
						// don't do anything
					};

					// show the remote control content
					$("#gameCard").remove();
					var jGameCard = $("#removeControlGameCard");
					jGameCard.attr("id", "gameCard");
					jGameCard.addClass("phoneRemote");
					jGameCard.show();
				}
			};

			a_toExec[a_toExec.length] = {
				"name": "phoneRemote.php",
				"dependencies": ["game.php"], // to execute after the game has been drawn
				"function": function() {
					// update the size of the game card
					var jGameCard = $("#gameCard");
					var jWindow = $(window);
					var winWidth = jWindow.width();
					var winHeight = jWindow.height();
					var gcWidth = jGameCard.fullWidth(true, false);
					var gcHeight = jGameCard.fullHeight(true, false);
					var ratio = 1;
					// get the new ratio
					if (gcWidth * ratio < winWidth)
					{
						ratio = winWidth / gcWidth;
					}
					if (gcHeight * ratio < winHeight)
					{
						ratio = winHeight / gcHeight;
					}
					if (gcWidth * ratio > winWidth)
					{
						ratio = Math.min(winWidth / gcWidth, ratio);
					}
					if (gcHeight * ratio > winHeight)
					{
						ratio = Math.min(winHeight / gcHeight);
					}
					// use the ratio to set the size
					jGameCard.css({
						'width': (jGameCard.width() * ratio) + 'px',
						'height': (jGameCard.height() * ratio) + 'px',
					})

					// update the size of everything else to match
					var jCurrentImage = jGameCard.find(".currentImage");
					game.limitImageSize(jCurrentImage);
				}
			};
		</script>
	</head>
	<body>
		<div class="centered generalError"></div>
		<?php
		includeContents();
		?>
		<div id="removeControlGameCard" class="centered" style="display: none;">
			<?php
			global $s_gameCardContents;
			echo $s_gameCardContents;
			?>
		</div>
		<div class="firefoxPlaceholder">placeholder</div>
	</body>
</html>
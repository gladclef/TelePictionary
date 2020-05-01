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

			phoneRemote = {
				updatePlayer: function(o_player) {
					if (playerFuncs.isLocalPlayer(o_player))
					{
						var jGameCard = $("#gameCard");
						var jStoryDescription = jGameCard.find(".storyDescription");

						if (o_player.gameState[0] < 2) { // GAME_PSTATE::WAITING
							jStoryDescription.text("Waiting to join a game");
						}
					}
				},

				setCurrentTurn: function(i_currentTurn) {
					var jGameCard = $("#gameCard");
					var jStoryDescription = jGameCard.find(".storyDescription");
					var o_localPlayer = playerFuncs.getPlayer();

					if (o_localPlayer === null || o_localPlayer.gameState[0] >= 2) // GAME_PSTATE::WAITING=2, local player is in a game
					{
						if (i_currentTurn < 0) {
							jStoryDescription.text("Waiting for host to start the game");
						}
					}

					jGameCard.show(); // the game code will hide the game card when the game hasn't started yet
				}
			};

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
				"name": "index.php", // emulate the index.php value in order to get the game code to execute
				"dependencies": ["jQuery", "jqueryExtension.js", "commands.js", "playerFuncs", "game", "control.js"],
				"function": function() {
					// set some things
					playerFuncs.setLocalPlayer(serverStats['localPlayerId']);
					game.setLocalPlayer(serverStats['localPlayerId']);

					// never show the desktop content
					commands.showContent = function() {
						// don't do anything
					};

					// do phoneRemote specific things when the local player changes
					var oldAddPlayer = playerFuncs.updatePlayer;
					playerFuncs.updatePlayer = function(o_player) {
						oldAddPlayer(o_player);
						phoneRemote.updatePlayer(o_player);
					}

					// show the remote control content
					$("#gameCard").remove();
					var jGameCard = $("#remoteControlGameCard");
					jGameCard.attr("id", "gameCard");
					jGameCard.addClass("phoneRemote");
					jGameCard.show();
				}
			};

			a_toExec[a_toExec.length] = {
				"name": "phoneRemote.php",
				"dependencies": ["game.php"], // to execute after the game has been drawn
				"function": function() {
					// do phoneRemote specific things when the current turn changes
					var oldSetCurrentTurn = game.setCurrentTurn;
					game.setCurrentTurn = function(i_currentTurn) {
						oldSetCurrentTurn(i_currentTurn);
						phoneRemote.setCurrentTurn(i_currentTurn);
					}

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
					fitImageSize(jCurrentImage, jGameCard.width() - 200, jGameCard.height() - 300);

					// re-update the current turn, since this should have already happened
					if (game.o_cachedGame !== null && game.o_cachedGame !== undefined) {
						phoneRemote.setCurrentTurn(game.o_cachedGame.currentTurn);
					}
				}
			};
		</script>
	</head>
	<body>
		<div class="centered generalError"></div>
		<?php
		includeContents();
		?>
		<div id="remoteControlGameCard" class="centered" style="display: none;">
			<?php
			global $s_gameCardContents;
			echo $s_gameCardContents;
			?>
		</div>
		<div class="firefoxPlaceholder">placeholder</div>
	</body>
</html>
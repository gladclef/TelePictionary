<div class="content" id="game" style="display: none;">
	<script type="text/javascript">
		game = {
			o_cachedGame: null,

			resetGuiState: function() {
				$("#gamePlayersCircle").find(".playerToken").remove();
				$("#gamePlayer1Control").hide();
			},

			setGameName: function(s_newGameName) {
				outgoingMessenger.pushData({
					command: 'setGameName',
					gameName: s_newGameName
				});
			},

			updateGame: function(o_game) {
				var jRoomCode = $("#gameRoomCode");
				var jGameName = $("#gameGameName");
				var jGameNameEdit = $("#gameGameNameEdit");
				var jGameStatus = $("#gameGameStatus");
				game.o_cachedGame = o_game;

				// get the game status
				var s_gameStatus = "Waiting for " + playerFuncs.getPlayerName(o_game.player1Id, 'host') + " to start game.";
				if (o_game.currentTurn >= 0)
				{
					s_gameStatus = "Turn " + (o_game.currentTurn+1);
				}
				if (o_game.currentTurn >= o_game.playerIds.length)
				{
					var i_playerId = o_game.playerIds[o_game.currentTurn - o_game.playerIds.length];
					var s_playerName = playerFuncs.getPlayerName(i_playerId);
					s_gameStatus = s_playerName + "'s story";
				}

				// update drawn values
				jRoomCode.text("Room code " + o_game.roomCode);
				jGameName.text(o_game.name);
				jGameNameEdit.find("input[type=text]").val(o_game.name);
				jGameName.show();
				jGameNameEdit.hide();
				jGameStatus.text(s_gameStatus);
				for (var i = 0; i < o_game.playerOrder.length; i++)
				{
					game.setPlayerTokenPosition(o_game.playerOrder[i], i);
				}
				game.setCurrentTurn(o_game.currentTurn);

				// call other update functions
				commands.setPlayer1(o_game.player1Id);
			},

			addPlayer: function(o_player) {
				var jPlayersCircle = $("#gamePlayersCircle");

				// get or create the player token
				var jPlayerToken = jPlayersCircle.find(".playerToken[playerId=" + o_player.id + "]");
				if (jPlayerToken.length === 0)
				{
					jPlayerToken = $("<div class='playerToken' playerId='" + o_player.id + "'><div class='playerImagePlaceholder'></div><img class='playerImage'></img><div class='playerName'></div></div>");
					jPlayersCircle.append(jPlayerToken);
					game.setPlayerTokenPosition(o_player.id, jPlayersCircle.find(".playerToken").length-1);
				}
				var jPlayerImgPlaceholder = jPlayerToken.find(".playerImagePlaceholder");
				var jPlayerImg = jPlayerToken.find(".playerImage");
				var jPlayerName = jPlayerToken.find(".playerName");

				// update the player's name and image
				var s_name = (o_player.name.trim() != "") ? o_player.name : "unknown";
				s_name = s_name.trim();
				var s_initials = s_name[0] + s_name[Math.min(s_name.length-1, 1)];
				if (s_name.indexOf(" ") >= 0)
					s_initials = s_name[0] + s_name[Math.min(s_name.lastIndexOf(" ")+1, s_name.length-1)];
				s_initials = s_initials.toUpperCase();
				jPlayerImgPlaceholder.text(s_initials);
				jPlayerName.text(s_name);

				// show the player name on hover
				jPlayerToken.off("click").on("click", function() {
					if (jPlayerName.css("display") == "none")
						jPlayerName.show();
					else
						jPlayerName.hide();
				});

				// update the player token editability if the local player
				if (o_player.id === playerFuncs.localPlayer)
				{
					commands.setLocalPlayer(playerFuncs.localPlayer);
				}
			},

			setPlayerTokenPosition: function(i_playerId, i_position, b_updatePositions) {
				if (arguments.length < 3) b_updatePositions = true;
				var jPlayersCircle = $("#gamePlayersCircle");
				var jPlayerToken = jPlayersCircle.find(".playerToken[playerId=" + i_playerId + "]");

				// bump the position of other player tokens with this position
				var jOtherPlayerToken = jPlayersCircle.find(".playerToken[position=" + i_position + "]");
				if (jOtherPlayerToken.length > 0)
				{
					var i_otherPlayerId = parseInt(jOtherPlayerToken.attr("playerId"));
					game.setPlayerTokenPosition(i_otherPlayerId, i_position + 1, false);
				}

				// set this player token position and update token layouts
				jPlayerToken.attr("position", i_position);
				if (b_updatePositions)
				{
					game.updatePlayerTokensLayout();
				}
			},

			updatePlayerTokensLayout: function() {
				var jPlayersCircle = $("#gamePlayersCircle");
				var jaPlayerTokens = jPlayersCircle.find(".playerToken");
				var tokenWidth = $(jaPlayerTokens[0]).find(".playerImagePlaceholder").width();
				var tokenHeight = $(jaPlayerTokens[0]).find(".playerImagePlaceholder").height();
				var padding = 20;
				var canvasWidth = jPlayersCircle.width() - padding*2;
				var canvasHeight = jPlayersCircle.height() - padding*2;

				$.each(jaPlayerTokens, function(k, playerToken) {
					var jPlayerToken = $(playerToken);
					var i_position = parseInt(jPlayerToken.attr("position"));
					
					var radians = 2*Math.PI*(i_position / jaPlayerTokens.length);
					var x0 = ( Math.sin(radians) + 1 ) / 2;
					var y0 = ( Math.cos(radians + Math.PI) + 1 ) / 2;
					var x = x0 * ( canvasWidth - tokenWidth ) + padding;
					var y = y0 * ( canvasHeight - tokenHeight ) + padding;

					jPlayerToken.css({
						left: x + "px",
						top: y + "px"
					});
				});
			},

			setPlayer1: function(i_id) {
				if (playerFuncs.isPlayer1())
				{
					// make game name editable
					var jGameName = $("#gameGameName");
					var jGameNameEdit = $("#gameGameNameEdit");
					jGameName.off("click");
					jGameName.on("click", function() {
						jGameName.hide();
						jGameNameEdit.show();
					});
					jGameName.css({
						cursor: 'pointer'
					});

					// make player1 controls visible
					var jPlayer1Control = $("#gamePlayer1Control");
					var jControlStart = jPlayer1Control.find("[control=start]");
					jPlayer1Control.show();
				}
			},

			controlLeaveClick: function() {
				outgoingMessenger.setNoPoll(10000);
				outgoingMessenger.pushData({
					'command': 'leaveGame'
				});
			},

			controlStartClick: function() {
				outgoingMessenger.pushData({
					'command': 'startGame'
				});
			},

			startGame: function() {
				if (playerFuncs.isPlayer1())
				{
					var jPlayer1Control = $("#gamePlayer1Control");
					var jControlStart = jPlayer1Control.find("[control=start]");
					jControlStart.hide();
				}
			},

			setCurrentTurn: function(i_currentTurn) {

			},

			endGame: function() {
				game.resetGuiState();
			},

			removePlayer: function(o_player) {
				var jPlayersCircle = $("#gamePlayersCircle");
				var jPlayerToken = jPlayersCircle.find(".playerToken[playerId=" + o_player.id + "]");
				jPlayerToken.remove();
			}
		};

		<?php

		require_once(dirname(__FILE__) . "/../resources/globals.php");
		require_once(dirname(__FILE__) . "/../resources/common_functions.php");
		require_once(dirname(__FILE__) . "/../objects/player.php");
		require_once(dirname(__FILE__) . "/../objects/game.php");

		function drawGame() {
			global $o_globalPlayer;

			// get the game instance
			$o_game = $o_globalPlayer->getGame();
			if ($o_game === null)
			{
				return;
			}
			$a_players = $o_game->getPlayers();
			$a_encodedPlayers = [];
			foreach ($a_players as $idx => $a_player) {
				array_push($a_encodedPlayers, $a_player->toJsonObj());
			}

			// draw the game
			$s_game = json_encode(json_encode($o_game->toJsonObj()));
			$s_players = json_encode(json_encode($a_encodedPlayers));
			echo "serverStats['game'] = {$s_game};\r\n";
			echo "serverStats['players'] = {$s_players}\r\n";
		}
		drawGame();

		?>

		a_toExec[a_toExec.length] = {
			"name": "game.php",
			"dependencies": ["jQuery", "jqueryExtension.js", "commands.js", "index.php"],
			"function": function() {
				game.resetGuiState();

				// register event handlers
				var jGameNameEdit = $("#gameGameNameEdit");
				var jGameNameEditText = jGameNameEdit.find("input[type=text]");
				var jGameNameEditDone = jGameNameEdit.find("input[type=button]");
				var jLeaveGame = $("#gameLeaveGame");
				jGameNameEditDone.on("click", function() {
					game.setGameName(jGameNameEditText.val());
				});
				jLeaveGame.off("mouseenter").on("mouseenter", function() {
					jLeaveGame.stop(true, true).animate({ 'width': '136px' }, 300, 'linear', function() {
						jLeaveGame.find("div").text("Exit Game").css({ 'top': '-3px', 'left': '0px' });
						jLeaveGame.css({ 'font-size': '20px' });
					});
				});
				jLeaveGame.off("mouseleave").on("mouseleave", function() {
					jLeaveGame.stop(true, true).css({ 'font-size': '23px' }).find("div").text('\u2B05').css({ 'top': '-7px', 'left': '-2px' });
					jLeaveGame.animate({ 'width': '20px' }, 300, 'linear');
				});
				jLeaveGame.off("click").on("click", game.controlLeaveClick);

				// canvas.mousemove(function(e) {
				// 	if (windowFocus && mouseDown) {
				// 		localUpdate(e);
				// 	}
				// });
				// canvas[0].addEventListener("touchmove", (function(e) {
				// 	if (windowFocus) {
				// 		e = e.originalEvent || e;
				// 		e = e.targetTouches || e.changedTouches || e.touches || e;
				// 		e = e[0] || e["0"] || e;
				// 		localUpdate(e);
				// 	}
				// }), false);

				// add the players
				if (serverStats['players'] !== undefined)
				{
					var a_players = JSON.parse(serverStats['players']);
					for (var i = 0; i < a_players.length; i++)
					{
						commands.addPlayer(a_players[i]);
					}
				}

				// update the game
				if (serverStats['game'] !== undefined)
				{
					var o_game = JSON.parse(serverStats['game']);
					commands.updateGame(o_game);
				}
			}
		};
	</script>

	<div id="gameLeaveGame"><div>&#x2B05;</div></div>
	<h3 id="gameGameName" class="centered"></h3>
	<div id="gameGameNameEdit" class="centered" style="width: 300px; display: none;"><input type="text" /><input type="button" value="Done" /></div>
	<h4 id="gameRoomCode" class="centered"></h4>
	<br />
	<div id="gamePlayersCircle" class="centered bordered" style="width: 700px; height: 700px;">

	</div>
	<div id="gamePlayer1Control" class="centered" style="width: 700px; display: none;">
		<div class="centered" gameControl="start" style="width: 80px;">
			<input type="button" value="Start Game" onclick="game.controlStartClick();" />
		</div>
	</div>
	<div id="gameGameStatus" class="centered" style="width: 500px;">loading...</div>
</div>
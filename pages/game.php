<div class="content" id="game" style="display: none;">
	<script type="text/javascript">
		game = {
			o_cachedGame: null,

			/** Called by commands.showContent() */
			init: function() {
				game.resetGuiState();
			},

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
				game.o_cachedGame = o_game;

				// update drawn values
				jRoomCode.text("Room code " + o_game.roomCode);
				jGameName.text(o_game.name);
				jGameNameEdit.find("input[type=text]").val(o_game.name);
				jGameName.show();
				jGameNameEdit.hide();
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
					sPlayerToken = $("#gamePlayerTokenTemplate").html().replaceAll("__playerId__", o_player.id);
					jPlayerToken = $(sPlayerToken);
					jPlayersCircle.append(jPlayerToken);
					game.setPlayerTokenPosition(o_player.id, jPlayersCircle.find(".playerToken").length-1);
				}
				var jPlayerImgPlaceholder = jPlayerToken.find(".playerImagePlaceholder");
				var jPlayerImage = jPlayerToken.find(".playerImage");
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
				jPlayerName.attr("s_name", encodeURI(s_name));
				jPlayerName.find(".playerNameName").text(s_name);
				if (playerFuncs.isLocalPlayer(o_player)) {
					jPlayerName.addClass("playerControls");
				}
				if (o_player.imageURL == "") {
					jPlayerImgPlaceholder.show();
					jPlayerImage.hide();
				} else {
					jPlayerImgPlaceholder.hide();
					jPlayerImage.show();
					jPlayerImg.css("background-image", "url(" + o_player.imageURL + ")");
				}

				// set the player readiness
				if (o_player.ready) {
					jPlayerToken.addClass("ready");
				} else {
					jPlayerToken.removeClass("ready");
				}

				// show the player name on click
				jPlayerToken.off("click").on("click", function() {
					if (jPlayerName.css("display") == "none")
						jPlayerName.show();
					else
						jPlayerName.hide();
				});
				var jUploadButton = jPlayerName.find("input[type=file]");
				jUploadButton.off('change');
				jUploadButton.on('change', function(e) {
					e.preventDefault();
					e.stopPropagation();
					game.uploadImage(jPlayerImage, jUploadButton[0].files);
					return false;
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
				if (i_position > playerFuncs.playerCount() / 2) {
					jPlayerToken.addClass("secondHalf");
				} else {
					jPlayerToken.removeClass("secondHalf");
				}
				if (b_updatePositions)
				{
					game.updatePlayerTokensLayout();
				}
			},

			updatePlayerTokensLayout: function() {
				var jPlayersCircle = $("#gamePlayersCircle");
				var jaPlayerTokens = jPlayersCircle.find(".playerToken");
				var jImagePlaceholderSample = $(jaPlayerTokens[0]).find(".playerImagePlaceholder");
				var tokenWidth = jImagePlaceholderSample.fullWidth(true, false);
				var tokenHeight = jImagePlaceholderSample.fullHeight(true, false);
				var padding = 20;
				var canvasWidth = jPlayersCircle.fullWidth(true, false) - padding*2;
				var canvasHeight = jPlayersCircle.fullHeight(true, false) - padding*2;

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
				var jPlayersCircle = $("#gamePlayersCircle");
				var jPlayerTokens = jPlayersCircle.find(".playerToken");

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
					jPlayer1Control.show();

					// update the status text
					game.updateStatusText();

					// update player tokens
					$.each(jPlayerTokens, function(k, v) {
						var jPlayerToken = $(v);
						var jPlayerName = jPlayerToken.find(".playerName");
						var i_id = parseInt(jPlayerToken.attr('playerId'));
						var o_player = playerFuncs.getPlayer(i_id);
						var s_name = decodeURI(jPlayerName.attr("s_name"));

						if (!playerFuncs.isLocalPlayer(i_id))
							jPlayerName.addClass("player1Controls");
						else
							jPlayerName.removeClass("player1Controls");
					});
				}
				else
				{
					// make game name not editable
					var jGameName = $("#gameGameName");
					var jGameNameEdit = $("#gameGameNameEdit");
					jGameName.off("click");
					jGameNameEdit.hide();
					jGameName.css({
						cursor: 'auto'
					});

					// make player1 controls invisible
					var jPlayer1Control = $("#gamePlayer1Control");
					var jControlStart = jPlayer1Control.find("[control=start]");
					jPlayer1Control.hide();

					// update the status text
					game.updateStatusText();

					// update player tokens
					$.each(jPlayerTokens, function(k, v) {
						var jPlayerToken = $(v);
						var jPlayerName = jPlayerToken.find(".playerName");
						var s_name = decodeURI(jPlayerName.attr("s_name"));

						jPlayerName.removeClass("player1Controls");
					});
				}

				// give player1 a crown!
				$.each(jPlayerTokens, function(k, v) {
					var jPlayerToken = $(v);
					jPlayerToken.find(".player1Crown").hide();
				});
				var jPlayer1Token = jPlayersCircle.find(".playerToken[playerId=" + players.player1 + "]");
				jPlayer1Token.find(".player1Crown").show();
			},

			setLocalPlayer: function(i_id) {
				if (i_id < 0)
					return;
				var jPlayersCircle = $("#gamePlayersCircle");
				var jPlayerToken = jPlayersCircle.find(".playerToken[playerId=" + i_id + "]");
				var jPlayerName = jPlayerToken.find(".playerName");

				jPlayerName.removeClass("player1Controls");
				jPlayerName.addClass("playerControls");
			},

			controlLeaveClick: function() {
				outgoingMessenger.setNoPoll(10000);
				outgoingMessenger.pushData({
					'command': 'leaveGame'
				});
			},

			uploadImage: function(jImg, a_files) {
				if (a_files.length !== 1) { alert("Incorrect number of image files (must be 1)."); return; }
				var f_file = a_files[0];
				if (f_file.size > 12 * 1048576) { alert("Image is too big! (must be less than 12MB)"); return; }

				var posts = new FormData();
				posts.append('command', jImg.attr("command"));
				posts.append('file', f_file);
				$.each(outgoingMessenger.customData, function(k, v) {
					posts.append(k, v);
				});
				var options = {
					"contentType": false,
					"processData": false
				};
				outgoingMessenger.pushData(posts, undefined, options);
			},

			controlUploadSentence: function() {
				var jGameCard = $("#gameCard");
				var jNewText = jGameCard.find(".newText");
				outgoingMessenger.pushData({
					'command': 'setCardText',
					'text': jNewText.val()
				});
			},

			controlPromotePlayer: function(i_id) {
				outgoingMessenger.pushData({
					'command': 'promotePlayer',
					'otherPlayerId': i_id
				});
			},

			controlKickPlayer: function(i_id) {
				outgoingMessenger.pushData({
					'command': 'leaveGame',
					'otherPlayerId': i_id
				});
			},

			controlStartClick: function() {
				outgoingMessenger.pushData({
					'command': 'setGameTurn',
					'turn': 0
				});
			},

			controlNextTurnClick: function() {
				outgoingMessenger.pushData({
					'command': 'setGameTurn',
					'turn': game.o_cachedGame.currentTurn + 1
				});
			},

			getCurrentCard: function() {
				outgoingMessenger.pushData({
					'command': 'getCurrentCard'
				});
			},

			limitImageSize: function(jImage) {
				var jGameCard = $("#gameCard");

				var limitSize = function(img) {
					var width = parseInt(img.width);
					var height = parseInt(img.height);
					var maxWidth = jGameCard.width() - 150;
					var maxHeight = jGameCard.height() - 150;
					var ratio = 1;

					if (width * ratio < maxWidth)
					{
						ratio = maxWidth / width;
					}
					if (height * ratio < maxHeight)
					{
						ratio = maxHeight / height;
					}
					if (width * ratio > maxWidth)
					{
						ratio = Math.min(maxWidth / width, ratio);
					}
					if (height * ratio > maxHeight)
					{
						ratio = Math.min(maxHeight / height);
					}

					jImage.css({
						'width': (width * ratio) + 'px',
						'height': (height * ratio) + 'px',
						'margin-top': ((maxHeight - (height * ratio)) / 2) + 'px'
					});
				}

				jImage.off('load');
				jImage.on('load', function() {
					var img = new Image();
					img.onload = function() {
						limitSize(img);
					};
					img.src = jImage.attr('src');
				});
				limitSize(jImage[0]);
			},

			updateCard: function(o_card) {
				var i_currentTurn = game.o_cachedGame.currentTurn;

				// draw the current card
				var jGameCard = $("#gameCard");
				var jHideMeFirsts = jGameCard.find(".hideMeFirst");
				if (i_currentTurn < 0) {
					// game not started yet
				} else if (i_currentTurn < game.o_cachedGame.playerIds.length) {
					// 1st+ turn of active play
					// draw the current card
					if (playerFuncs.isLocalPlayer(o_card.authorId)) {
						jGameCard.show();
						jHideMeFirsts.hide();
						if (o_card.type == 0) { // image card
							var jCurrentImage = jGameCard.find(".currentImage");
							game.limitImageSize(jCurrentImage);
							jCurrentImage.attr('src', o_card.imageURL);
							jCurrentImage.show();
						} else { // sentence card
							var jNewText = jGameCard.find(".newText");
							var jCurrentText = jGameCard.find(".currentText");
							jCurrentText.css('width', jGameCard.width() * 0.8 + 'px');
							jCurrentText.text(o_card.text);
							if (jNewText.val() == "")
								jNewText.val(o_card.text);
							if (jCurrentText.text() == "")
								jCurrentText.hide();
							else
								jCurrentText.show();
						}
					}
				} else {
					// reveal step TODO
					jGameCard.hide();
				}
			},

			updateStory: function(o_story) {
				var jGameCard = $("#gameCard");
				var jStoryDescription =	jGameCard.find(".storyDescription");

				if (o_story.startingPlayerName === "" || game.o_cachedGame.currentTurn == 0) {
					jStoryDescription.text(playerFuncs.getPlayer().name + "'s Story:");
				} else {
					jStoryDescription.text(o_story.startingPlayerName + "'s Story:");
				}
			},

			prevTurn: -1,
			setCurrentTurn: function(i_currentTurn) {
				if (game.prevTurn == i_currentTurn && i_currentTurn >= 0)
				{
					// don't draw the same turn over and over again
					return;
				}

				// un-minimize the game card
				var jGameCard = $("#gameCard");
				if (jGameCard.hasClass('minimized')) {
					var jopaqueEye = jGameCard.find(".opaqueEye");
					game.minimizeGameCard(jopaqueEye[0]);
				}

				// draw the current card
				var jHideMeFirsts = jGameCard.find(".hideMeFirst");
				if (i_currentTurn < 0) {
					// game not started yet
					jGameCard.hide();
				} else if (i_currentTurn < game.o_cachedGame.playerIds.length) {
					// 1st+ turn of active play
					// show and get the current card
					jGameCard.show();
					jHideMeFirsts.hide();

					var cardType = (game.o_cachedGame.cardStartType + i_currentTurn) % 2;
					var otherType = (cardType + 1) % 2;
					var jCurrentCard = jGameCard.find(".card" + cardType);
					var jOtherCard = jGameCard.find(".card" + otherType);
					var jCurrentImage = jGameCard.find(".currentImage");
					var jNewText = jGameCard.find(".newText");
					var jCurrentText = jGameCard.find(".currentText");
					jCurrentImage.attr('src', '');
					jNewText.val('');
					jCurrentText.val('');
					jCurrentCard.show();
					jOtherCard.hide();

					if (i_currentTurn == 0) {
						// first turn
						jGameCard.show();
						jHideMeFirsts.hide();
						var jStartingCard = jGameCard.find(".card" + game.o_cachedGame.cardStartType);
						var jStoryDescription =	jGameCard.find(".storyDescription");
						jStoryDescription.text(playerFuncs.getPlayer().name + "'s Story:");
						jStartingCard.show();
					}

					game.getCurrentCard();
				} else {
					// reveal step TODO
					jGameCard.hide();
				}

				// update the available game controls
				var jPlayer1Control = $("#gamePlayer1Control");
				var jControlStart = jPlayer1Control.find("input.startGame");
				var jControlNextTurn = jPlayer1Control.find("input.nextTurn");
				var jUploadButton = jGameCard.find("input[type=file]");
				var jCardImage = jGameCard.find(".currentImage");
				jControlStart[(i_currentTurn == -1 && playerFuncs.isPlayer1()) ? 'show' : 'hide']();
				jControlStart[(i_currentTurn >= 0 && playerFuncs.isPlayer1() && playerFuncs.allPlayersReady()) ? 'show' : 'hide']();
				jUploadButton.off('change');
				jUploadButton.on('change', function(e) {
					e.preventDefault();
					e.stopPropagation();
					game.uploadImage(jCardImage, jUploadButton[0].files);
					return false;
				});

				// update the status text
				game.updateStatusText();
			},

			endGame: function() {
				game.resetGuiState();
			},

			updateStatusText: function() {
				var jGameStatus = $("#gameGameStatus");
				var o_game = game.o_cachedGame;
				var s_gameStatus = "";

				// determine the status text
				if (playerFuncs.isPlayer1(players.localPlayer))
				{
					s_gameStatus = "Press \"Start Game\" when all players have joined.";
				}
				else
				{
					s_gameStatus = "Waiting for host to start game";
				}
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

				jGameStatus.text(s_gameStatus);
			},

			removePlayer: function(o_player) {
				var jPlayersCircle = $("#gamePlayersCircle");
				var jPlayerToken = jPlayersCircle.find(".playerToken[playerId=" + o_player.id + "]");
				jPlayerToken.remove();
			},

			makeTransparent: function(h_child) {
				var jchild = $(h_child);
				var jparent = jchild.parent();
				if (jchild.attr('old-opacity') === undefined)
					jchild.attr('old-opacity', jchild.css('opacity'));
				jchild.finish().animate({ 'opacity': 1 }, 200);
				jparent.finish().animate({ 'opacity': 0.3 }, 200);
			},

			makeOpaque: function(h_child) {
				var jchild = $(h_child);
				var jparent = jchild.parent();
				var opacity = parseFloat(jchild.attr('old-opacity'));
				jchild.finish().animate({ 'opacity': opacity }, 200);
				jparent.finish().animate({ 'opacity': 1 }, 200);
			},

			minimizeGameCard: function(h_child) {
				var jchild = $(h_child);
				var jparent = jchild.parent();
				var currentCard = (game.o_cachedGame.currentTurn + game.o_cachedGame.cardStartType) % 2;
				var otherCard = (currentCard + 1) % 2;
				var jotherCard = jparent.find(".card" + otherCard);
				if (jparent.hasClass('minimized')) {
					var width = parseFloat(jparent.attr('old-width'));
					jparent.finish().animate({ 'width': width + 'px' }, 200);
					jparent.removeClass('minimized');
					$.each(jparent.children(), function(k, v) {
						if (v != h_child && v != jotherCard[0]) {
							$(v).finish().show(200);
						}
					});
				} else {
					if (jparent.attr('old-width') === undefined)
						jparent.attr('old-width', jparent.width());
					jparent.finish().animate({ 'width': '70px' }, 200);
					jparent.addClass('minimized');
					$.each(jparent.children(), function(k, v) {
						if (v != h_child && v != jotherCard[0]) {
							$(v).finish().hide(200);
						}
					});
				}
			}
		};

		<?php

		require_once(dirname(__FILE__) . "/../resources/globals.php");
		require_once(dirname(__FILE__) . "/../resources/common_functions.php");
		require_once(dirname(__FILE__) . "/../objects/player.php");
		require_once(dirname(__FILE__) . "/../objects/game.php");

		function drawGame() {
			global $o_globalPlayer;
			global $fqdn;

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
			echo "serverStats['fqdn'] = '{$fqdn}'\r\n";
			echo "serverStats['localPlayer'] = {$o_globalPlayer->getId()}\r\n";

			// draw the current card
			$a_gameState = $o_game->getGameState();
			if ($a_gameState[0] == 2) { // game started but not revealing cards yet
				$o_card = $o_globalPlayer->getCurrentCard();
				error_log("game card: " . $o_card->getId());
				if ($o_card !== null) {
					$s_card = json_encode(json_encode($o_card->toJsonObj()));
					echo "serverStats['currentCard'] = {$s_card}\r\n";
				}
			}
			else if ($a_gameState[0] == 3) { // revealing cards
				// TODO
			}
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

				// draw the current card
				if (serverStats['currentCard'] !== undefined)
				{
					var o_card = JSON.parse(serverStats['currentCard']);
					commands.updateCard(o_card);
				}

				// draw the qr code
				if (serverStats['players'] !== undefined)
				{
					var jjoin = $("#gameJoinOnPhone");
					var jqrCode = jjoin.find(".code").find(".qrcode");
					var jlink = jjoin.find(".code").find(".link");
					var linkText = "https://" + serverStats['fqdn'] + "/phoneRemote.php?playerId=" + serverStats['localPlayer'];
					jqrCode.qrcode(linkText);
					jlink.attr('href', linkText).text(linkText);
				}
			}
		};
	</script>

	<h3 id="gameGameName" class="centered"></h3>
	<div id="gameGameNameEdit" class="centered" style="width: 300px; display: none;"><input type="text" /><input type="button" value="Done" /></div>
	<h4 id="gameRoomCode" class="centered"></h4>
	<br />
	<div id="gamePlayersCircle" class="centered bordered" style="width: 700px; height: 700px;">
	</div>
	<div id="gameCard" class="centered" style="display: none;">
		<?php
		ob_start();
		?>
		<div class="opaqueEye" onmouseenter="game.makeTransparent(this);" onmouseleave="game.makeOpaque(this);" onclick="game.minimizeGameCard(this);"></div>
		<div class="storyDescription centered">Player's Story:</div>
		<div class="card1" style="display: none;">
			<div class="previousImage hideMeFirst centered" style="background-image: __imageUrl__"></div>
			<span class="hideMeFirst">Write a short description of this image:</span>
			<textarea class="newText" placeholder="short sentence" cols="40" rows="3"></textarea><br />
			<input type="button" value="Submit" onclick="game.controlUploadSentence();" />
			<br /><br /><br />
			<div class="currentText centered"></div>
		</div>
		<div class="card0" style="display: none;">
			<div class="previousSentence hideMeFirst centered">__sentenceValue__</div>
			<div>
				<span>Draw an image</span>
				<span class="hideMeFirst">about this sentence</span>
				<span>and then upload it:</span>
				<input type="button" value="Upload Image" onclick="$(this).parent().find('input[type=file]').val('').click();" />
				<input type="file" accept="image/jpg,image/jpeg,image/png,image/gif,image/bmp,image/tiff" style="display: none;" /><!-- calls uploadImage on click, as set in setCurrentTurn -->
				<br />
				<img src="__imageUrl__" class="currentImage centered" command="setCardImage" style="display: none;" />
			</div>
		</div>
		<?php
		global $s_gameCardContents;
		$s_gameCardContents = ob_get_contents();
		ob_end_clean();
		echo $s_gameCardContents;
		?>
	</div>
	<div id="gamePlayer1Control" class="centered" style="width: 700px; display: none;">
		<div class="centered" gameControl="start" style="width: 80px;">
			<input type="button" class="startGame" value="Start Game" onclick="game.controlStartClick();" />
			<input type="button" class="nextTurn" value="Next Turn" onclick="game.controlNextTurnClick();" />
		</div>
	</div>
	<div id="gameGameStatus" class="centered" style="width: 500px;">loading...</div>
	<div id="gamePlayerTokenTemplate" style="display: none;">
		<div class="playerToken" playerid="__playerId__">
			<div class="player1Crown" style="display: none;"></div>
			<div class="playerImagePlaceholder" style="display: none;"></div>
			<div class="playerImage" command="setPlayerImage" style="display: none;"></div>
			<div class="playerName" style="display: none;">
				<div class="playerNameName"></div>
				<input class="playerControl" type="button" value="Change Picture" onclick="$(this).parent().find('input[type=file]').click();" />
				<input type="file" accept="image/jpg,image/jpeg,image/png,image/gif,image/bmp,image/tiff" style="display: none;" /><!-- calls uploadImage on click, as set in addPlayer -->
				<input class="player1Control" type="button" value="Promote" onclick="game.controlPromotePlayer(__playerId__);" />
				<input class="player1Control" type="button" value="Kick" onclick="game.controlKickPlayer(__playerId__);" />
			</div>
			<img class="readyCheck" src="imagesStatic/checkmark.png" />
		</div>
	</div>
	<div id="gameLeaveGame"><div>&#x2B05;</div></div>
	<div id="gameJoinOnPhone">
		<div class="button" onclick="$(this).parent().find('.code').toggle();"></div>
		<div class="code">
			<div class="qrcode"></div>
			<a class="link"></a>
		</div>
	</div>
</div>
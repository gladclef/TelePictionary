<?php

global $o_globalPlayer;
global $o_globalGame;
$o_globalGame = $o_globalPlayer->getGame();

?><div class="content" id="game" style="display: none;">
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

				var jGameCard = $("#gameCard"); if (jGameCard.find === undefined) { throw ("jGameCard is " + JSON.stringify(jGameCard) + " in <?php echo (basename(__FILE__) . __LINE__); ?>"); }
				var jHideMeFirsts = jGameCard.find(".hideMeFirst");
				var jImgs = jGameCard.find("img");
				var jPreviousText = jGameCard.find(".previousText");
				var jNewText = jGameCard.find(".newText");
				var jCurrentText = jGameCard.find(".currentText");
				jHideMeFirsts.hide();
				jPreviousText.text("");
				jPreviousText.hide();
				jImgs.attr('src', '');
				jImgs.hide();
				jImgs.css({ 'width': 0, 'height': 0 });
				jNewText.val('');
				jCurrentText.text('');
				jCurrentText.hide();
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
					game.setPlayerTokenPosition(o_game.playerOrder[i], i, false);
				}
				game.updatePlayerTokensLayout();
				game.setCurrentTurn(o_game.currentTurn);

				// call other update functions
				commands.setPlayer1(o_game.player1Id);
			},

			updatePlayer: function(o_player) {
				var jPlayersCircle = $("#gamePlayersCircle");

				// get or create the player token
				var jPlayerToken = jPlayersCircle.find(".playerToken[playerId=" + o_player.id + "]");
				if (jPlayerToken.length === 0)
				{
					sPlayerToken = $("#gamePlayerTokenTemplate").html().replaceAll("__playerId__", o_player.id);
					jPlayerToken = $(sPlayerToken);
					jPlayersCircle.append(jPlayerToken);
				}
				game.setPlayerTokenPosition(o_player.id, game.getPlayerOrderIndex(o_player));
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
				if (o_player.isReady) {
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
				if (playerFuncs.isLocalPlayer(o_player.id)) {
					commands.setLocalPlayer(o_player.id);
				}

				// update the player1 controls (dependent on player readiness levels)
				game.updatePlayer1Controls();

				// update the game card visibility based on player readiness
				var jGame = $("#game");
				if (playerFuncs.isLocalPlayer(o_player.id)) {
					game.showGameCard(jGame, o_player);
				}
			},

			showGameCard: function(jContainer, o_localPlayer) {
				jContainer.removeClass("gameStarted");
				jContainer.removeClass("showGameCard");
				if (o_localPlayer == null) {
					return;
				}

				// GAME_PSTATE::IN_PROGRESS=3
				if (o_localPlayer.gameState[0] < 3) {
					// game hasn't started yet
				} else if (o_localPlayer.gameState[0] == 3) {
					// game has started
					jContainer.addClass("gameStarted");
					if (!o_localPlayer.isReady) {
						// prompt the user to set their text/upload an image for this turn
						jContainer.addClass("showGameCard");
					}
				} else if (o_localPlayer.gameState[0] > 3) {
					// currently revealing
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
					if (i_otherPlayerId != i_playerId) {
						game.setPlayerTokenPosition(i_otherPlayerId, i_position + 1, false);
					}
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
					
					var radians = 2*Math.PI*(i_position / playerFuncs.playerCount());
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

			getPlayerOrderIndex: function(o_player) {
				if (game.o_cachedGame === null)
					return 0;
				return game.o_cachedGame.playerOrder.indexOf(o_player.id);
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

				// update the player1 controls at the bottom of the screen
				game.updatePlayer1Controls(); 
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
				var f_leaveGame = function() {
					outgoingMessenger.setNoPoll(10000);
					outgoingMessenger.pushData({
						'command': 'leaveGame'
					});
				};

				if (game.o_cachedGame === undefined || game.o_cachedGame === null || game.o_cachedGame.currentTurn <= 0) {
					f_leaveGame();
				} else {
					$( "#dialog-confirm-exit" ).dialog({
						resizable: false,
						height: "auto",
						width: 400,
						modal: true,
						draggable: false,
						buttons: {
							"Leave Game": function() {
								$( this ).dialog( "close" );
								f_leaveGame();
							},
							"Cancel": function() {
								$( this ).dialog( "close" );
							}
						}
					});
				}
			},

			uploadProgress: function(f_progress) {
				var jProgress = $("#uploadProgress");

				if (f_progress >= 1.0) {
					jProgress.hide();
				} else {
					$("body").append(jProgress);
					var percentText = (f_progress > 0) ? (Math.floor(f_progress * 100) + "%") : "";
					jProgress.text("Uploading... " + percentText);
					jProgress.show();
				}
			},

			uploadImage: function(jImg, a_files) {
				if (a_files.length == 0) { return; } // no image chosen
				if (a_files.length !== 1) { alert("Incorrect number of image files (must be 1)."); return; }
				var f_file = a_files[0];
				if (f_file.size > 12 * 1048576) { alert("Image is too big! (must be less than 12MB)"); return; }

				var posts = new FormData();
				posts.append('command', jImg.attr("command"));
				posts.append('file', f_file);
				var options = {
					"contentType": false,
					"processData": false,
					"timeout": 5*60*1000, // don't let the upload time out during the upload
				};
				outgoingMessenger.pushData(posts, undefined, options, game.uploadProgress);
			},

			controlUploadSentence: function() {
				var jGameCard = $("#gameCard"); if (jGameCard.find === undefined) { throw ("jGameCard is " + JSON.stringify(jGameCard) + " in <?php echo (basename(__FILE__) . __LINE__); ?>"); }
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
				var jPlayer1Control = $("#gamePlayer1Control");
				var jControlStartCard = jPlayer1Control.find("select.startCard");
				outgoingMessenger.pushData({
					'command': 'composite',
					'action': [
						{ 'command': 'setStartCard',
						  'startCard': parseInt(jControlStartCard.val()) },
						{ 'command': 'setGameTurn',
						  'turn': 0 },
					]
				});
			},

			controlNextTurnClick: function() {
				outgoingMessenger.pushData({
					'command': 'setGameTurn',
					'turn': game.o_cachedGame.currentTurn + 1
				});
			},

			controlStartSharingClick: function() {
				outgoingMessenger.pushData({
					'command': 'startSharing'
				});
			},

			getCurrentCard: function() {
				outgoingMessenger.pushData({
					'command': 'getCurrentCard'
				});
			},

			updateCard: function(o_card) {
				var o_game = game.o_cachedGame;
				var i_currentTurn = o_game.currentTurn;

				// find the game card
				var jGameCard = $("#gameCard"); if (jGameCard.find === undefined) { throw ("jGameCard is " + JSON.stringify(jGameCard) + " in <?php echo (basename(__FILE__) . __LINE__); ?>"); }
				var jGameCardSmall = $("#gameCardSmall");

				// draw the current card
				var jHideMeFirsts = jGameCard.find(".hideMeFirst");
				if (i_currentTurn < 0) {
					// game not started yet
				} else if (i_currentTurn < o_game.playerIds.length) {
					// 1st+ turn of active play
					// draw the current card
					if (playerFuncs.isLocalPlayer(o_card.authorId)) {
						if (i_currentTurn == 0) {
							jHideMeFirsts.hide();
						} else {
							jHideMeFirsts.show();
						}

						var jCurrentImages = $.merge(jGameCard.find(".currentImage"), jGameCardSmall.find(".currentImage"));
						var jPreviousTexts = $.merge(jGameCard.find(".previousText"), jGameCardSmall.find(".previousText"));
						var jNewTexts = $.merge(jGameCard.find(".newText"), jGameCardSmall.find(".newText"));
						var jCurrentTexts = $.merge(jGameCard.find(".currentText"), jGameCardSmall.find(".currentText"));
						var jPreviousImages = $.merge(jGameCard.find(".previousImage"), jGameCardSmall.find(".previousImage"));
						var jOverlay = jGameCardSmall.find(".overlay");
						var jImageCardContents = $.merge($('<div>'), jCurrentImages, jPreviousTexts);
						var jTextCardContents = $.merge($('<div>'), jNewTexts, jCurrentTexts, jPreviousImages);
						jImageCardContents.hide();
						jTextCardContents.hide();
						
						if (o_card.type == 0) { // image card
							jImageCardContents.show();
							jOverlay.text("change image");

							var previousText = (o_card.text.trim() != "") ? '"'+o_card.text.trim()+'"' : "";
							jPreviousTexts.text(previousText);
							$(jPreviousTexts[0]).css('width', jGameCard.width() * 0.75 + 'px');
							jPreviousTexts[(previousText.trim() == "" ? "hide" : "show")]();
							jCurrentImages.attr('src', o_card.imageURL);
						} else { // text/sentence card
							jTextCardContents.show();
							jOverlay.text("change text");

							$(jCurrentTexts[0]).css('width', jGameCard.width() * 0.75 + 'px');
							$(jCurrentTexts[1]).css('width', jGameCardSmall.width() + 'px');
							jCurrentTexts.text(o_card.text);
							if (jNewTexts.val() == "")
								jNewTexts.val(o_card.text);
							if (jCurrentTexts.text() == "")
								jCurrentTexts.hide();
							else
								jCurrentTexts.show();
							jNewTexts.show();
							jPreviousImages.attr('src', o_card.imageURL);
						}

						// fit the image size to the available card size
						game.fitCardImages(o_card.type);
					}
				} else {
					// reveal step
				}

				// draw/hide the game card
				var jGame = $("#game");
				if (playerFuncs.isLocalPlayer(o_card.authorId)) {
					game.showGameCard(jGame, playerFuncs.getPlayer());
				}
			},

			fitCardImages: function(i_cardType) {
				// find the game card
				var jGameCard = $("#gameCard"); if (jGameCard.find === undefined) { throw ("jGameCard is " + JSON.stringify(jGameCard) + " in <?php echo (basename(__FILE__) . __LINE__); ?>"); }
				var jGameCardSmall = $("#gameCardSmall");

				// find the images
				var jCurrentImages = $.merge(jGameCard.find(".currentImage"), jGameCardSmall.find(".currentImage"));
				var jPreviousImages = $.merge(jGameCard.find(".previousImage"), jGameCardSmall.find(".previousImage"));
				var jActiveImgs = null;
				if (i_cardType == 0) { // image card
					jActiveImgs = jCurrentImages;
				} else { // text/sentence card
					jActiveImgs = jPreviousImages;
				}

				// fit the image size to the available card size
				var fitActiveImgSize = function(k, h_img) {
					// get the image and the parent game card
					var jImg = $(h_img);
					var jParent = jImg;
					var i = 0;
					while (jParent.attr("id") != "gameCard" && jParent.attr("id") != "gameCardSmall") {
						jParent = jParent.parent();
						i++;
						if (i >= 100) {
							console.log("Programmer error: too much recursion in <?php echo __FILE__ . ':' . __LINE__; ?>");
							return;
						}
					}

					// get the contents for determining size
					var jAllImgs = jParent.find("img");
					var jChildren = jParent.children();
					var maxWidth = jParent.width() - parseInt(jParent.attr("subWidth"));
					var maxHeight = jParent.height() - parseInt(jParent.attr("subHeight"));

					// find the max size
					jAllImgs.hide();
					$.each(jChildren, function(k, h) {
						var jChild = $(h);
						if (jChild.css('display') === 'none' || jChild.hasClass('dontCountHeight'))
							return;
						maxHeight -= jChild.fullHeight(true, true, true);
					});

					// fit the image to the max size
					if (jImg.attr('src') != '')
						jImg.show();
					var fitType = (jParent.attr('id') == 'gameCard') ? 'fit' : 'fill';
					fitImageSize(jImg, maxWidth, maxHeight, null, fitType);
				}
				$.each(jActiveImgs, fitActiveImgSize);
			},

			updateStory: function(o_story) {
				if (o_story === null || game.o_cachedGame === null)
					return;

				var jGameCard = $("#gameCard"); if (jGameCard.find === undefined) { throw ("jGameCard is " + JSON.stringify(jGameCard) + " in <?php echo (basename(__FILE__) . __LINE__); ?>"); }
				var jStoryDescription =	jGameCard.find(".storyDescription");

				if (o_story.startingPlayerName === "" || game.o_cachedGame.currentTurn == 0) {
					jStoryDescription.text(playerFuncs.getPlayer().name + "'s Story:");
				} else {
					jStoryDescription.text(o_story.startingPlayerName + "'s Story:");
				}
			},

			updatePlayer1Controls: function() {
				if (game.o_cachedGame === undefined || game.o_cachedGame === null || playerFuncs === undefined) {
					return;
				}

				// This function updates the player1 controls at the bottom of the screen.
				// It is not just a part of the setPlayer1 function because it is also dependent
				// on the current turn and the players readiness.
				var i_currentTurn = game.o_cachedGame.currentTurn;
				var jPlayer1Control = $("#gamePlayer1Control");
				var jControlStartCard = jPlayer1Control.find("select.startCard");
				var jStartBreak = jPlayer1Control.find(".startGameBreak");
				var jControlStart = jPlayer1Control.find("input.startGame");
				var jControlNextTurn = jPlayer1Control.find("input.nextTurn");
				var jControlStartReviewing = jPlayer1Control.find(".finishGame");
				jPlayer1Control.show();
				jControlStartCard.hide();
				jStartBreak.hide();
				jControlStart.hide();
				jControlNextTurn.hide();
				jControlStartReviewing.hide();

				// hide all controls if not player1
				if (!playerFuncs.isPlayer1()) {
					jPlayer1Control.hide();
				}

				// show certain controls to player1
				else if (i_currentTurn == -1) {
					jControlStartCard.show();
					jStartBreak.show();
					jControlStart.show();
				} else if (!playerFuncs.allPlayersReady()) {
					jPlayer1Control.hide();
				} else if (i_currentTurn >= 0 && i_currentTurn < playerFuncs.playerCount()-1) {
					jControlNextTurn.show();
				} else if (i_currentTurn == playerFuncs.playerCount()-1) {
					jControlStartReviewing.show();
				}

				// update some the controls based on the game object
				if (game.o_cachedGame.cardStartType != parseInt(jControlStartCard.val())) {
					jControlStartCard.val(game.o_cachedGame.cardStartType + '');
				}
			},

			prevTurn: -1,
			setCurrentTurn: function(i_currentTurn) {
				if (game.prevTurn == i_currentTurn && i_currentTurn >= 0)
				{
					// don't draw the same turn over and over again
					return;
				}

				// find the game card and small game card
				// show and get the current card
				var jGame = $("#game");
				var jGameCard = $("#gameCard"); if (jGameCard.find === undefined) { throw ("jGameCard is " + JSON.stringify(jGameCard) + " in <?php echo (basename(__FILE__) . __LINE__); ?>"); }
				var jGameCardSmall = $("#gameCardSmall");
				game.showGameCard(jGame, playerFuncs.getPlayer());

				// draw the current card
				var jHideMeFirsts = jGameCard.find(".hideMeFirst");
				if (i_currentTurn < 0) {
					// game not started yet
				} else if (i_currentTurn < game.o_cachedGame.playerIds.length) {
					// 1st+ turn of active play
					jHideMeFirsts.hide();

					var cardType = (game.o_cachedGame.cardStartType + i_currentTurn) % 2;
					var otherType = (cardType + 1) % 2;
					var jCurrentCard = jGameCard.find(".card" + cardType);
					var jOtherCard = jGameCard.find(".card" + otherType);
					var jCurrentImages = $.merge(jGameCard.find(".currentImage"), jGameCardSmall.find(".currentImage"));
					var jNewTexts = $.merge(jGameCard.find(".newText"), jGameCardSmall.find(".newText"));
					var jCurrentTexts = $.merge(jGameCard.find(".currentText"), jGameCardSmall.find(".currentText"));
					jCurrentImages.attr('src', '');
					jNewTexts.val('');
					jCurrentTexts.val('');
					jCurrentCard.show();
					jOtherCard.hide();

					if (i_currentTurn == 0) {
						// first turn
						jHideMeFirsts.hide();
						var jStartingCard = jGameCard.find(".card" + game.o_cachedGame.cardStartType);
						var jStoryDescription =	jGameCard.find(".storyDescription");
						jStoryDescription.text(playerFuncs.getPlayer().name + "'s Story:");
						jStartingCard.show();
					}

					game.getCurrentCard();
				} else {
					// reveal step
				}

				// update the available generic controls
				var jUploadButton = jGameCard.find("input[type=file]");
				var jCardImage = jGameCard.find(".currentImage");
				jUploadButton.off('change');
				jUploadButton.on('change', function(e) {
					e.preventDefault();
					e.stopPropagation();
					game.uploadImage(jCardImage, jUploadButton[0].files);
					return false;
				});

				// update the player 1 controls
				game.updatePlayer1Controls();

				// update the status text
				game.updateStatusText();
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

			minimizeGameCard: function(jGameCard, i_cardType, o_animationOptions) {
				if (arguments.length < 2 || i_cardType === undefined || i_cardType === null)
					i_cardType = (game.o_cachedGame.currentTurn + game.o_cachedGame.cardStartType) % 2;
				if (arguments.length < 3 || o_animationOptions === undefined || o_animationOptions === null)
					o_animationOptions = { 'duration': 200 };

				var i_otherCard = (i_cardType + 1) % 2;
				var jotherCard = jGameCard.find(".card" + i_otherCard);
				if (jGameCard.hasClass('minimized')) {
					
					// show this card
					var width = parseFloat(jGameCard.attr('old-width'));
					jGameCard.finish().animate({ 'width': width + 'px' }, o_animationOptions);
					jGameCard.removeClass('minimized');
					$.each(jGameCard.children(), function(k, v) {
						var jObj = $(v);
						if (!jObj.hasClass('dontMinimize') && v != jotherCard[0]) {
							jObj.finish().show(o_animationOptions);
						}
					});
				} else {

					// minimize this card
					if (jGameCard.attr('old-width') === undefined)
						jGameCard.attr('old-width', jGameCard.width());
					jGameCard.finish().animate({ 'width': '70px' }, o_animationOptions);
					jGameCard.addClass('minimized');
					$.each(jGameCard.children(), function(k, v) {
						var jObj = $(v);
						if (!jObj.hasClass('dontMinimize') && v != jotherCard[0]) {
							jObj.finish().hide(o_animationOptions);
						}
					});
				}
			},

			showQrCode: function() {
				var jJoin = $("#gameJoinOnPhone");
				var jCodeContainer = jJoin.find('.code');
				var jqrCode = jCodeContainer.find(".qrcode");
				var jlink = jCodeContainer.find(".link");
				var linkText = "https://" + serverStats['fqdn'] + "/phoneRemote.php?playerId=" + playerFuncs.getPlayer().id;
				jqrCode.children().remove();
				jqrCode.qrcode(linkText);
				jlink.attr('href', linkText).text(linkText);

				jCodeContainer.toggle();
			},

			updateTurnTimer: function() {
				var o_game = game.o_cachedGame;
				var i_turnType = (o_game.cardStartType + o_game.currentTurn) % 2;
				var f_turnElapsed = getServerTime() - o_game.turnStart;
				var f_turnTotal = (i_turnType == 0) ? o_game.drawTimerLen : o_game.textTimerLen;
				var f_turnRemaining = f_turnTotal - f_turnElapsed;
				var f_percentElapsed = f_turnElapsed / f_turnTotal;

				var a_color = [0,255,0];
				var o_localPlayer = playerFuncs.getPlayer();
				if (o_localPlayer !== null && !o_localPlayer.isReady) {
					a_color = colorFade(f_percentElapsed, [0,255,0], [255,255,0], [255,0,0]);
				}
				var s_color = 'rgb(' + a_color[0] + ',' + a_color[1] + ',' + a_color[2] + ')';

				var drawArc = function(jCanvas, f_ratio, color) {
					var context = jCanvas[0].getContext("2d");
					var xPos = jCanvas.width() / 2;
					var yPos = xPos;
					var radius = xPos;

					var startAngle = -90;
					var endAngle = Math.max(Math.min(f_ratio, 1.0), 0) * 360 - 90;
					var startAngle = startAngle * (Math.PI/180);
					var endAngle   = endAngle   * (Math.PI/180);
					var anticlockwise = false;
					var radius = radius;

					context.strokeStyle = color;
					context.fillStyle   = color;
					context.lineWidth   = 1;

					context.clearRect(0, 0, parseInt(jCanvas.attr('width')), parseInt(jCanvas.attr('height')));
					context.beginPath();
					context.arc(xPos, yPos, radius, startAngle, endAngle, anticlockwise);
					context.lineTo(xPos, yPos);
					context.lineTo(xPos, 0);
					context.fill();
					context.stroke();
				}
				drawArc($("#turnTimerSmall"), f_percentElapsed, s_color);
				drawArc($("#turnTimerBig"), f_percentElapsed, s_color);
			}
		};
		// Here to indicate this script has executed.
		// We do this because the "game" div gets registered as being accessible by javascript.
		gameJsObj = true;

		<?php

		require_once(dirname(__FILE__) . "/../resources/globals.php");
		require_once(dirname(__FILE__) . "/../resources/common_functions.php");
		require_once(dirname(__FILE__) . "/../objects/player.php");
		require_once(dirname(__FILE__) . "/../objects/game.php");

		function drawGame() {
			global $o_globalPlayer;
			global $o_globalGame;
			global $fqdn;

			// always set the fqdn server stat
			echo "serverStats['fqdn'] = '{$fqdn}'\r\n";

			// get the game instance
			if ($o_globalGame === null)
			{
				return;
			}
			$a_players = $o_globalGame->getPlayers();
			$a_encodedPlayers = [];
			foreach ($a_players as $idx => $a_player) {
				array_push($a_encodedPlayers, $a_player->toJsonObj());
			}

			// draw the game
			$s_game = json_encode(json_encode($o_globalGame->toJsonObj()));
			$s_players = json_encode(json_encode($a_encodedPlayers));
			echo "serverStats['game'] = {$s_game};\r\n";
			echo "serverStats['players'] = {$s_players}\r\n";

			// draw the current card
			$a_gameState = $o_globalGame->getGameState();
			if ($a_gameState[0] == GAME_GSTATE::IN_PROGRESS) { // game started but not revealing cards yet
				$o_card = $o_globalPlayer->getCurrentCard();
				if ($o_card !== null) {
					$s_card = json_encode(json_encode($o_card->toJsonObj()));
					echo "serverStats['currentCard'] = {$s_card}\r\n";
				}
			}
		}
		drawGame();

		?>

		a_toExec[a_toExec.length] = {
			"name": "game.php",
			"dependencies": ["jQuery", "jqueryExtension.js", "commands.js", "index.php", "jquery.qrcode.min.js", "reveal_overrides"],
			"function": function() {
				game.resetGuiState();

				// register event handlers
				var jGame = $("#game");
				var jGameNameEdit = $("#gameGameNameEdit");
				var jGameNameEditText = jGameNameEdit.find("input[type=text]");
				var jGameNameEditDone = jGameNameEdit.find("input[type=button]");
				var jLeaveGame = $(".leaveGame");
				var jGameCard = $("#gameCard");
				var jGameCardSmall = $("#gameCardSmall");
				var jGameCardOverlay = $("#gameCardOverlay");
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
				jGameCardSmall.off("click").on("click", function() {
					jGame.addClass("showGameCard");
					game.fitCardImages((game.o_cachedGame.cardStartType + game.o_cachedGame.currentTurn) % 2);
				});
				jGameCardOverlay.off("click").on("click", function() { jGame.removeClass("showGameCard"); });

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
						commands.updatePlayer(a_players[i]);
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

				// set up drawing the timer
				setInterval(game.updateTurnTimer, 1000);
			}
		};
	</script>

	<h3 id="gameGameName" class="centered"></h3>
	<div id="gameGameNameEdit" class="centered" style="width: 300px; display: none;"><input type="text" /><input type="button" value="Done" /></div>
	<h4 id="gameRoomCode" class="centered"></h4>
	<br />
	<div id="gamePlayersCircle" class="centered bordered" style="width: 700px; height: 700px;">
	</div>
	<div id="uploadProgress" class="dontMinimize dontCountHeight"><!-- will get moved to the end of the "body" element when used -->
		Uploading...
	</div>
	<div id="gamePlayer1Control" class="centered" style="width: 700px; display: none;">
		<div class="centered" gameControl="start">
			<select class="startCard">
				<option value="0" <?php echo ($o_globalGame != null && $o_globalGame->getCardStartType() == 0) ? 'selected' : '' ?>>Start with Drawing</option>
				<option value="1" <?php echo ($o_globalGame != null && $o_globalGame->getCardStartType() == 1) ? 'selected' : '' ?>>Start with Sentence</option>
			</select>
			<br class="startGameBreak" />
			<input type="button" class="startGame" value="Start Game" onclick="game.controlStartClick();" style="display:none;" />
			<input type="button" class="nextTurn" value="Next Turn" onclick="game.controlNextTurnClick();" style="display:none;" />
			<div class="finishGame" style="display:none;">End this round and <input type="button" value="Start Sharing" onclick="game.controlStartSharingClick();" /></div>
		</div>
	</div>
	<div id="gameGameStatus" class="centered" style="width: 500px;">loading...</div>
	<div class="leaveGame"><div>&#x2B05;</div></div>
	<div id="gameCardOverlay"></div>
	<canvas id="turnTimerSmall" width="341" height="341"></canvas><!-- will get moved to the be in the parent element of gameCardSmall when used -->
	<canvas id="turnTimerBig" width="676" height="676"></canvas><!-- will get moved to the be in the parent element of gameCardSmall when used -->
	<div id="gameCardSmall" class="centered" subWidth="0" subHeight="0">
		<img src="" class="currentImage" command="setCardImage" style="display: none; position: absolute; left: 0;" />
		<div class="dontCountHeight" style="position: absolute; width: 100%; height: 100%;">
			<img class="previousImage hideMeFirst centered" />
			<div class="currentText centered" style="position: absolute; bottom: 0;"></div>
			<div class="previousText centered"></div>
			<div class="overlay dontCountHeight">change image</div>
		</div>
	</div>
	<div id="gameCard" class="centered" subWidth="150" subHeight="50"><!-- will get moved to phoneRemoteContent when used in the phoneRemote page -->
		<br />
		<div class="storyDescription spaceBottom centered">Player's Story:</div>
		<div class="card1" style="display: none;">
			<span class="hideMeFirst spaceBottom">Write a short description of this image:</span><br />
			<textarea class="newText" placeholder="short sentence" cols="40" rows="3"></textarea><br />
			<input class="" type="button" value="Submit" onclick="game.controlUploadSentence();" /><br /><br />
			<img class="previousImage hideMeFirst centered" />
			<div class="currentText centered"></div>
		</div>
		<div class="card0" style="display: none;">
			<div>
				<div class="spaceBottom" style="max-width: 58%; margin: 0 auto 10px;">
					<span class="">Draw an image</span>
					<span class="hideMeFirst">for this description</span>
					<span class="">and then upload it:</span>
					<input class="" type="button" value="Upload Image" onclick="$(this).parent().parent().find('input[type=file]').val('').click();" />
				</div>
				<div class="previousText centered spaceBottom"></div><br />
				<div style="height:1px; width:1px; overflow:hidden; display:inline-block;"><input class="" type="file" accept="image/jpg,image/jpeg,image/png,image/gif,image/bmp,image/tiff" /><!-- calls uploadImage on click, as set in setCurrentTurn --></div>
				<br />
				<img src="" class="currentImage centered" command="setCardImage" style="display: none;" />
			</div>
		</div>
	</div>
	<div id="gameJoinOnPhone" <?php echo (isMobileDevice()) ? 'style="display: none;"' : ''; ?>>
		<div class="button" onclick="game.showQrCode();"></div>
		<div class="code">
			<div class="qrcode"></div>
			<a class="link"></a><br /><br />
			Point your phone's camera at this QR code to
			use your phone as a remote for taking pictures.
		</div>
	</div>
	<div id="gamePlayerTokenTemplate" style="display: none;">
		<div class="playerToken" playerid="__playerId__">
			<div class="player1Crown" style="display: none;"></div>
			<div class="playerImagePlaceholder" style="display: none;"></div>
			<div class="playerImage" command="setPlayerImage" style="display: none;"></div>
			<div class="playerName" style="display: none;">
				<div class="playerNameName"></div>
				<input class="playerControl" type="button" value="Change Picture" onclick="$(this).parent().find('input[type=file]').click();" />
				<input type="file" accept="image/jpg,image/jpeg,image/png,image/gif,image/bmp,image/tiff" style="display: none;" /><!-- calls uploadImage on click, as set in updatePlayer -->
				<input class="player1Control" type="button" value="Promote" onclick="game.controlPromotePlayer(__playerId__);" />
				<input class="player1Control" type="button" value="Kick" onclick="game.controlKickPlayer(__playerId__);" />
			</div>
			<img class="readyCheck" src="imagesStatic/checkmark.png" />
		</div>
	</div>
</div>
<div id="dialog-confirm-exit" title="Leave the game?" style="display: none;">
	<p><span class="ui-icon ui-icon-alert" style="float:left; margin:12px 12px 20px 0;"></span>Leaving the game effectively ends the game for everyone. Do you still want to leave?</p>
</div>
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
		<link rel="stylesheet" type="text/css" href="css/phoneRemote.css" />

		<script>
			<?php
			includeServerStats();
			?>

			phoneRemote = {
				updatePlayer: function(o_player) {
					if (playerFuncs.isLocalPlayer(o_player))
					{
						var jGameCard = $("#gameCard"); if (jGameCard.find === undefined) { throw ("jGameCard is " + JSON.stringify(jGameCard) + " in <?php echo (basename(__FILE__) . ':' . __LINE__); ?>"); }
						var jStoryDescription = jGameCard.find(".storyDescription");

						if (o_player.gameState[0] < 2) { // GAME_PSTATE::WAITING
							jStoryDescription.text("Waiting to join a game");
						}
					}
				},

				setCurrentTurn: function(i_currentTurn) {
					var jGameCard = $("#gameCard"); if (jGameCard.find === undefined) { throw ("jGameCard is " + JSON.stringify(jGameCard) + " in <?php echo (basename(__FILE__) . __LINE__); ?>"); }
					var jStoryDescription = jGameCard.find(".storyDescription");
					var o_localPlayer = playerFuncs.getPlayer();

					if (o_localPlayer === null || o_localPlayer.gameState[0] >= 2) // GAME_PSTATE::WAITING=2, local player is in a game
					{
						if (i_currentTurn < 0) {
							game.resetGuiState();
							$.each(jGameCard.children(), function(k, h) {
								var jChild = $(h);
								if (jChild.css('display') !== 'none' && !jChild.hasClass('hidePreGame')) {
									jChild.hide();
									jChild.addClass('hidePreGame');
								}
							});
							jStoryDescription.show();
							jStoryDescription.text("Waiting for host to start the game");
						} else {
							jGameCard.find('.hidePreGame').removeClass('hidePreGame').show();
						}
					}

					// show the game card
					var jPhoneRemoteGame = $("#phoneRemoteGame");
					game.showGameCard(jPhoneRemoteGame, o_localPlayer);
				},

				updateGameCard: function(o_card) {
					phoneRemote.updateGameCardSize();
				},

				updateGameCardSize: function() {
					var jGameCard = $("#gameCard");
					var jImgs = jGameCard.find("img");
					var jChildren = jGameCard.children();
					var maxWidth = jGameCard.width() - 150;
					var maxHeight = jGameCard.height() - 150;

					jImgs.hide();
					$.each(jChildren, function(k, h) {
						var jChild = $(h);
						if (jChild.css('display') === 'none')
							return;
						maxHeight -= jChild.fullHeight(true, true, true);
					});
					jImgs.show();

					$.each(jImgs, function(k, h_img) {
						var jImg = $(h_img);
						fitImageSize(jImg, maxWidth, maxHeight);
					});
				},

				updateRevealCard: function(jCard, o_card) {
					if (jCard == null)
						return;
					if (!playerFuncs.isLocalPlayer(o_card.authorId))
						return;

					// add the card
					var jCardContainer = $("#phoneRemoteReveal");
					jCardContainer.children().remove();
					jCardContainer.append(jCard);

					// size and position the card
					jCard.css({
						'left': 0,
						'top': 0,
						'width': parseInt(jCardContainer.width()) + 'px',
						'height': parseInt(jCardContainer.height()) + 'px'
					});
					$.each(jCard.find("img"), function(k, h_img) {
						var jImg = $(h_img);
						fitImageSize(jImg, jCard.width() - 150, jCard.height() - 200);
					});

					// get the player
					var o_player = playerFuncs.getPlayer();

					// add event handlers
					phoneRemote.registerCardEvents(jCard, o_card, o_player);
				},

				registerCardEvents: function(jCard, o_card, o_player) {
					var f_revealCard = function() { reveal.revealCard(jCard, o_card, o_player); };

					if (jCard.hasClass('notRevealed') && jCard.hasClass('localPlayer')) {
						jCard.children().off("click");
						jCard.off("click").on("click", f_revealCard);
					}
				},
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
				"dependencies": ["jQuery", "jqueryExtension.js", "commands.js", "playerFuncs", "gameJsObj", "control.js"],
				"function": function() {
					// show our own content, not the full content
					var oldShowContent = commands.showContent;
					commands.showContent = function(s_content) {
						s_content = 'phoneRemote' + s_content.capitalize();
						if ($("#" + s_content).length > 0) {
							oldShowContent(s_content);
						} else {
							oldShowContent('phoneRemoteAbout');
						}
					};

					// do phoneRemote specific things when the local player changes
					var oldAddPlayer = playerFuncs.updatePlayer;
					playerFuncs.updatePlayer = function(o_player) {
						oldAddPlayer(o_player);
						phoneRemote.updatePlayer(o_player);
					}

					// do phoneRemote specific things when the game card gets updated
					var oldUpdateCard = game.updateCard;
					game.updateCard = function(o_card) {
						oldUpdateCard(o_card);
						phoneRemote.updateGameCardSize();
					}

					// do phoneRemote specific things when the reveal card gets updated
					var oldRevealUpdateCard = reveal.updateCard;
					reveal.updateCard = function(o_card) {
						var jCard = oldRevealUpdateCard(o_card);
						phoneRemote.updateRevealCard(jCard, o_card);
					}

					// basic setup
					f_commonStartupJs();

					// show the remote control content
					var jGameCard = $("#gameCard"); if (jGameCard.find === undefined) { throw ("jGameCard is " + JSON.stringify(jGameCard) + " in <?php echo (basename(__FILE__) . __LINE__); ?>"); }
					var jGameCardOverlay = $("#gameCardOverlay");
					var jPhoneRemoteGame = $("#phoneRemoteGame");
					jPhoneRemoteGame.append(jGameCard);
					jGameCard.addClass("phoneRemote"); // for specialized css styling
					jGameCard.show(); // always keep the game card visible for the phoneRemote
					jGameCardOverlay.hide(); // always keep the overlay hidden for the phoneRemote
				}
			};

			a_toExec[a_toExec.length] = {
				"name": "phoneRemote.php",
				"dependencies": ["game.php", "revealJsObj"], // to execute after the game has been drawn
				"function": function() {
					// do phoneRemote specific things when the current turn changes
					var oldSetCurrentTurn = game.setCurrentTurn;
					game.setCurrentTurn = function(i_currentTurn) {
						oldSetCurrentTurn(i_currentTurn);
						phoneRemote.setCurrentTurn(i_currentTurn);
					}

					// update the size of the game card
					var jGameCard = $("#gameCard"); if (jGameCard.find === undefined) { throw ("jGameCard is " + JSON.stringify(jGameCard) + " in <?php echo (basename(__FILE__) . __LINE__); ?>"); } // the renamed #remoteControlGameCard
					var jRevealCard = $("#phoneRemoteReveal"); // the local reveal content container
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
					var a_size = {
						'width': (jGameCard.width() * ratio) + 'px',
						'height': (jGameCard.height() * ratio) + 'px',
					};
					jGameCard.css(a_size);
					jRevealCard.css(a_size);

					// update the size of everything else to match
					phoneRemote.updateGameCardSize();

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
		<div class="phoneRemoteContent" id="phoneRemoteAbout">
			Waiting to join a game.
		</div>
		<div class="phoneRemoteContent" id="phoneRemoteGame">
			<div id="remoteControlGameCard" class="gameCard centered" style="display: none;">
				<?php
				global $s_gameCardContents;
				echo $s_gameCardContents;
				?>
			</div>
		</div>
		<div class="phoneRemoteContent revealCardBar" id="phoneRemoteReveal">
		</div>
		<?php
		includeGeneralError();
		?>
		<div class="firefoxPlaceholder">placeholder</div>
	</body>
</html>
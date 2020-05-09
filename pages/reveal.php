<?php

global $o_globalPlayer;
global $o_globalGame;
$o_globalGame = $o_globalPlayer->getGame();

?><div class="content" id="reveal" style="display: none;">
	<script type="text/javascript">
		reveal = {
			i_cardWidth: 700,
			f_cardRatio: (625.0/500.0),
			i_cardHeight: 0,
			f_paddingRatio: (50.0/875.0),
			i_cardPadding: 0,

			o_cachedStory: null,
			a_cachedPlayerIdToCardId: {},
			a_cachedCardIdToPlayerId: {},
			a_cards: {},

			updateStory: function(o_story) {
				reveal.a_cachedCardIdToPlayerId = {};
				reveal.a_cachedPlayerIdToCardId = {};
				reveal.o_cachedStory = o_story;
				reveal.a_cards = {};
				if (o_story === null) {
					// hide the current story elements
					// TODO
					return;
				}

				// update maps
				for (var i = 0; i < reveal.o_cachedStory.playerOrder.length; i++) {
					var i_playerInOrderId = reveal.o_cachedStory.playerOrder[i];
					var i_cardId = reveal.o_cachedStory.cardIds[i];
					reveal.a_cachedPlayerIdToCardId[i_playerInOrderId] = i_cardId;
					reveal.a_cachedCardIdToPlayerId[i_cardId] = i_playerInOrderId;
				}

				// remove the old story cards
				var jRevealCardBar = $("#reveal").find(".revealCardBar");
				$.each(jRevealCardBar.children(), function(k, h_card) {
					var jCard = $(h_card);
					var i_cardId = parseInt(jCard.attr('cardId'));
					if (!o_story.cardIds.includes(i_cardId)) {
						jCard.remove();
					}
				});

				// upate the player tokens
				reveal.updatePlayerTokenOrder(o_story.playerOrder);

				// update player1 controls
				var jPlayer1Controls = $("#revealPlayerBar").children(".player1Controls");
				var jNextStory = jPlayer1Controls.find("input.nextStory");
				jNextStory.val("Start " + o_story.nextStory);
				reveal.updatePlayer1Controls();

				// update the rate game controls
				reveal.updateRateGameControls();
			},

			updateGame: function(o_game) {
				var o_player = playerFuncs.getPlayer();
				var s_ratingLinksGetVars = 'playerId=' + o_player.id + '&roomCode=' + o_game.roomCode;
				var jRateGame = $("#revealRateGame");
				var jRatingLinks = jRateGame.find("a");

				$.each(jRatingLinks, function(k, h_link) {
					var jLink = $(h_link);
					if (jLink.attr('href_original') === undefined)
						jLink.attr('href_original', jLink.attr('href'));
					jLink.attr('href', jLink.attr('href_original') + s_ratingLinksGetVars);
				});
			},

			getCardId: function(i_playerId) {
				if (reveal.o_cachedStory === null || reveal.a_cachedPlayerIdToCardId === null)
					return -1;
				if (reveal.a_cachedPlayerIdToCardId[i_playerId] === null)
					return -1;
				return a_cachedPlayerIdToCardId[i_playerId];
			},

			getCard: function(si_cardId) {
				if (typeof(si_cardId) == "string")
					si_cardId = parseInt(si_cardId);
				if (reveal.a_cards === undefined || reveal.a_cards[si_cardId] === undefined)
					return null;
				return reveal.a_cards[si_cardId];
			},

			updatePlayerTokenOrder: function(a_playerIdsInOrder) {
				// updates player token order and card order

				var jPlayerBar = $("#revealPlayerBar");
				var jScrollPanel = jPlayerBar.find(".scrollPanel");
				var i_tokenWidth = -1;

				for (var i = 0; i < a_playerIdsInOrder.length; i++) {
					// reorder the player token containers
					var i_playerId = a_playerIdsInOrder[i];
					var jRevealPlayerContainer = jScrollPanel.find(".playerTokenContainer[playerId=" + i_playerId + "]");
					if (jRevealPlayerContainer.length > 0) {
						if (i_tokenWidth < 0) {
							i_tokenWidth = jRevealPlayerContainer.fullWidth(true, true, true);
						}
						jRevealPlayerContainer.css({
							'left': (i_tokenWidth * i) + 'px'
						});
					}

					// reorder the cards
					if (i < a_playerIdsInOrder.length-1) {
						// find the card that follows this card
						var i_nextId = a_playerIdsInOrder[i+1];
						var jRevealCardBar = $("#reveal").find(".revealCardBar");
						var jCard = jRevealCardBar.find(".gameCard[playerId=" + i_playerId + "]");
						var jNextCard = jRevealCardBar.find(".gameCard[playerId=" + i_nextId + "]");

						// reorder things so that this card comes before the next card
						if (jCard.length > 0 && jNextCard.length > 0) {
							if (jCard.next()[0] != jNextCard[0]) {
								jCard.remove();
								jCard.insertBefore(jNextCard);
								
								var o_player = playerFuncs.getPlayer(i_playerId);
								var i_cardId = jCard.attr('cardId');
								var o_card = reveal.a_cards[i_cardId];
								reveal.registerCardEvents(jCard, o_card, o_player);
							}
						}
					}
				};

				// other things
				reveal.indicateActivePlayerCard();
			},

			updatePlayer: function(o_player) {
				// we call this immediately after game.updatePlayer so that we can copy the player token created by the game code
				var jPlayersCircle = $("#gamePlayersCircle");
				var jPlayerBar = $("#revealPlayerBar");
				var jScrollPanel = jPlayerBar.find(".scrollPanel");
				var jGamePlayerToken = jPlayersCircle.find(".playerToken[playerId=" + o_player.id + "]");

				// get the container
				var jRevealPlayerContainer = jScrollPanel.find(".playerTokenContainer[playerId=" + o_player.id + "]");
				if (jRevealPlayerContainer.length == 0)
				{
					// create and add the player token
					var jRevealPlayerTemplate = $("#revealPlayerTemplate");
					jRevealPlayerContainer = $(jRevealPlayerTemplate.html());
					jRevealPlayerContainer.attr('playerId', o_player.id);
					jScrollPanel.append(jRevealPlayerContainer);

					// set the position of the player token
					var i_tokenWidth = jRevealPlayerContainer.fullWidth(true, true, true);
					var i_numChildren = jScrollPanel.children().length;
					jRevealPlayerContainer.css({
						'left': (i_tokenWidth * (i_numChildren-1)) + 'px'
					});

					// update the scroll panel
					reveal.updatePlayerScrollPanel();
				}

				// copy over the new player token
				var jRevealPlayerToken = jRevealPlayerContainer.find(".revealPlayerToken");
				jRevealPlayerToken.children().remove();
				jRevealPlayerToken.html(jGamePlayerToken.html());

				// update the player name
				var jPlayerName = jRevealPlayerContainer.find(".revealPlayerName");
				jPlayerName.text(o_player.name);

				// update player1 controls
				var jReveal = $("#reveal");
				if (playerFuncs.allPlayersReady()) {
					jReveal.addClass("allPlayersReady");
				} else {
					jReveal.removeClass("allPlayersReady");
				}
				reveal.updatePlayer1Controls();

				// register events
				reveal.registerPlayerTokenEvents(jRevealPlayerToken, o_player);
			},

			removePlayer: function(o_player) {
				var jPlayerBar = $("#revealPlayerBar");
				var jScrollPanel = jPlayerBar.find(".scrollPanel");
				var jRevealPlayerContainer = jScrollPanel.find(".playerTokenContainer[playerId=" + o_player.id + "]");
				jRevealPlayerContainer.remove();
				reveal.updatePlayerScrollPanel();
			},

			updatePlayerScrollPanel: function() {
				var jPlayerBar = $("#revealPlayerBar");
				var jScrollPanel = jPlayerBar.find(".scrollPanel");
				var jRevealPlayerContainer = jScrollPanel.find(".playerTokenContainer");

				// get the new desired with
				if (jRevealPlayerContainer.length == 0)
					return;
				var containerWidths = jScrollPanel.children().length * jRevealPlayerContainer.fullWidth(true, true);
				containerWidths += 4; // this is for the cardActive green border around the player token

				// update css for the scroll panel
				jScrollPanel.css({ 'width': containerWidths + 'px' });
				var f_winWidth = $(window).width();
				var f_scrollPanelWidth = jScrollPanel.fullWidth(true, false, true);
				if (f_scrollPanelWidth < f_winWidth - 100) {
					var i_width = f_scrollPanelWidth;
					jPlayerBar.css({
						'width': i_width + 'px',
						'left': ((f_winWidth - i_width) / 2) + 'px',
						'border-left-width': jPlayerBar.css('border-bottom-width'),
						'border-left-color': jPlayerBar.css('border-bottom-color'),
						'border-left-style': jPlayerBar.css('border-bottom-style'),
						'border-right-width': jPlayerBar.css('border-bottom-width'),
						'border-right-color': jPlayerBar.css('border-bottom-color'),
						'border-right-style': jPlayerBar.css('border-bottom-style'),
						'border-bottom-left-radius': '10px',
						'border-bottom-right-radius': '10px',
					});
				} else {
					jPlayerBar.css({
						'width': '100%',
						'left': 0,
						'border-left': 'none',
						'border-right': 'none',
						'border-bottom-left-radius': 0,
						'border-bottom-right-radius': 0,
					});
				}
			},

			registerPlayerTokenEvents: function(jRevealPlayerToken, o_player) {
				var f_playerClick = function() { reveal.playerClick(jRevealPlayerToken, o_player); };
				jRevealPlayerToken.off("click").on("click", f_playerClick);
			},

			getCardMaximizedPosition: function() {
				var jPlayerBar = $("#revealPlayerBar");
				return {
					'left': ($(document).width() - reveal.i_cardWidth)/2 + 'px',
					'top': jPlayerBar.fullHeight(true, true) + 50 + 'px'
				};
			},

			i_cardFullHeight: -1,
			updateCard: function(o_card) {
				// verify this card is part of the current story
				if (reveal.o_cachedStory === null || reveal.a_cachedCardIdToPlayerId === undefined) {
					return null;
				}
				reveal.a_cards[o_card.id] = o_card;

				// get the player for this card
				var i_playerId = reveal.a_cachedCardIdToPlayerId[o_card.id];
				if (i_playerId === undefined || i_playerId === null) {
					return null;
				}
				var o_player = playerFuncs.getPlayer(i_playerId);
				if (o_player === undefined) {
					return null;
				}

				// create a new card for the player token
				var jPlayerBar = $("#revealPlayerBar");
				var jRevealPlayerContainer = jPlayerBar.find(".playerTokenContainer[playerId=" + o_player.id + "]");
				var jRevealCardBar = $("#reveal").find(".revealCardBar");
				var jCard = jRevealCardBar.find(".gameCard[playerId=" + o_player.id + "]");
				var jCardTemplate = $("#revealCard");
				var b_isMaximized = false;
				var jNext = null, jPrev = null;
				if (jCard.length > 0)
				{
					b_isMaximized = !jCard.hasClass('minimized');
					jNext = jCard.next();
					jPrev = jCard.prev();
				}
				jCard.remove();
				jCard = $(jCardTemplate.html());
				jCard.attr('cardId', o_card.id);
				jCard.attr('playerId', o_player.id);
				jCard.css({
					'width': reveal.i_cardWidth + 'px',
					'height': reveal.i_cardHeight + 'px',
					'padding-top': reveal.i_cardPadding + 'px'
				});

				// indicate the card reveal state
				if (o_card.isRevealed) {
					jCard.addClass("revealed");
				} else {
					jCard.addClass("notRevealed");
				}
				if (o_card.authorId == playerFuncs.getPlayer().id) {
					jCard.addClass("localPlayer");
				}

				// set the card contents
				var jImage = jCard.find(".currentImage");
				var jText = jCard.find(".currentText");
				var jCurrCard = jCard.find(".card" + o_card.type);
				var i_maxWidth = reveal.i_cardWidth - 2*reveal.i_cardPadding;
				var i_maxHeight = reveal.i_cardHeight - 2*reveal.i_cardPadding;
				jImage.attr('src', o_card.imageURL);
				jText.text(o_card.text);
				if (o_card.isRevealed || true) {
					jCurrCard.show();
				}
				if (jCard.hasClass('notRevealed') && jCard.hasClass('localPlayer')) {
					i_maxHeight -= 100;//jCard.find('.revealHint').height();
				}
				fitImageSize(jImage, i_maxWidth, i_maxHeight);
				jText.css({ 'max-width': i_maxWidth + 'px' });

				// add the card to the card bar
				if (jNext !== null && jNext.length > 0) {
					jCard.insertBefore(jNext);
				} else if (jPrev !== null && jPrev.length > 0) {
					jCard.insertAfter(jPrev);
				} else {
					jRevealCardBar.append(jCard);
				}
				reveal.i_cardFullHeight = jCard.fullHeight(true, true, true);
				var jReveal = $("#reveal");
				var i_cardBarHeight = jRevealCardBar.fullHeight(true, true, true);
				var i_playerBarHeight = jPlayerBar.fullHeight(true, false, true);
				var i_winHeight = $(window).height();
				var i_marginSpacer = (i_winHeight - i_playerBarHeight) % reveal.i_cardFullHeight - 35;
				$("#reveal").css({ 'margin-bottom': i_marginSpacer + 'px' });

				// update the card order
				reveal.updatePlayerTokenOrder(reveal.o_cachedStory.playerOrder);

				// indicate the active player according the the card scroll position
				reveal.indicateActivePlayerCard();

				// register events
				reveal.registerCardEvents(jCard, o_card, o_player);

				return jCard;
			},

			registerCardEvents: function(jCard, o_card, o_player) {
				var f_revealCard = function() { reveal.revealCard(jCard, o_card, o_player); };
				var f_maximizeCardContents = function() { reveal.maximizeCardContents(jCard, o_card, o_player); };

				if (jCard.hasClass('revealed')) {
					jCard.children().off("click").on("click", f_maximizeCardContents);
				} else if (jCard.hasClass('notRevealed') && jCard.hasClass('localPlayer')) {
					jCard.children().off("click");
					jCard.off("click").on("click", f_revealCard);
				}
			},

			t_winScroll: null,
			playerClick: function(jPlayerToken, o_player) {
				// figure out the player order index
				var i_activeIdx = reveal.o_cachedStory.playerOrder.indexOf(o_player.id);

				// scroll to the active card
				var jWindow = $(window);
				var jRevealCardBar = $("#reveal").find(".revealCardBar");
				var i_scrollPos = 0;
				if (i_activeIdx > 0) {
					i_scrollPos = reveal.i_cardFullHeight * i_activeIdx;
					i_scrollPos += parseInt(jRevealCardBar.css('padding-top'));
				}
				jWindow.smoothScroll(i_scrollPos, 300);
			},

			revealCard: function(jCard, o_card, o_player) {
				outgoingMessenger.pushData({
					command: 'revealCard',
					cardId: o_card.id
				});
			},

			maximizeCardContents: function(jCard, o_card, o_player) {
				var jRevealOverlay = $("#revealOverlay");
				var jImg = jRevealOverlay.find(".currentImage");
				var jTxt = jRevealOverlay.find(".currentText");
				var i_winWidth = $(window).width();
				var i_winHeight = $(window).height();
				var i_padding = 100;
				var i_maxWidth = i_winWidth - 2*i_padding;
				var i_maxHeight = i_winHeight - 2*i_padding;
				var jType = jRevealOverlay.find(".card" + o_card.type);

				jImg.attr('src', o_card.imageURL);
				jTxt.text(o_card.text);
				jImg.hide();
				jTxt.hide();
				jType.show();
				jRevealOverlay.show();

				var f_updateWidthHeight = function(jElement) {
					var i_width = parseInt(jElement.width());
					var i_height = parseInt(jElement.height());
					jElement.css({
						'margin-top': ((i_winHeight - i_height) / 2) + 'px',
						'margin-left': ((i_winWidth - i_width) / 2) + 'px'
					});
				}
				fitImageSize(jImg, i_maxWidth, i_maxHeight, f_updateWidthHeight);
				jTxt.css({ 'max-width': i_maxWidth + 'px' });
				f_updateWidthHeight(jTxt);
			},

			onWindowGestureChange: function(e_gesture) {
				if (e_gesture.rotation > 0)
					return;
				if (e_gesture.scale > 1)
					return;
				reveal.onWindowScroll(null, window.pageYOffset);
			},

			onWindowScroll: function(e_scrollEvt, i_scrollAmount, b_isGesture) {
				if (arguments.length < 2 || i_scrollAmount === undefined || i_scrollAmount === null)
					i_scrollAmount = $(window).scrollTop();
				if (arguments.length < 3 || b_isGesture === undefined || b_isGesture === null)
					b_isGesture = false;
				var jHidyBar = $("#revealHidyBar");
				var jLeaveGame = $("#reveal").find(".leaveGame");

				// some aesthetic stuff
				if (i_scrollAmount > 12) {
					jHidyBar.css({ 'border-bottom-color': '#5555FF' });
				} else {
					jHidyBar.css({ 'border-bottom-color': '#8888FF' });
				}
				if (jLeaveGame.attr('finalPos') === undefined)
					jLeaveGame.attr('finalPos', parseInt(jLeaveGame.css('left')));
				if (i_scrollAmount < 100) {
					if (jLeaveGame.hasClass('hiding')) {
						jLeaveGame.removeClass('hiding');
						jLeaveGame.show();
						jLeaveGame.css({ 'left': -(jLeaveGame.fullWidth(true, false, true)) + 'px' });
						jLeaveGame.finish().animate({
							'left': parseInt(jLeaveGame.attr('finalPos')) + 'px'
						}, 200, 'spring');
					}
				} else {
					if (!jLeaveGame.hasClass('hiding')) {
						jLeaveGame.addClass('hiding');
						jLeaveGame.css({ 'left': parseInt(jLeaveGame.attr('finalPos')) + 'px' });
						jLeaveGame.finish().animate({
							'left': -(jLeaveGame.fullWidth(true, false, true)) + 'px'
						}, 200, 'swing', function() {
							jLeaveGame.hide();
						});
					}
				}

				// indicate who is active
				var i_duration = (b_isGesture) ? 0 : undefined;
				reveal.indicateActivePlayerCard(i_scrollAmount, i_duration);
			},

			indicateActivePlayerCard: function(i_scrollAmount, i_duration) {
				if (arguments.length < 1 || i_scrollAmount === undefined || i_scrollAmount === null)
					i_scrollAmount = $(window).scrollTop();
				if (arguments.length < 2 || i_duration === undefined || i_duration === null)
					i_duration = 150;

				// calculate who is active
				var i_activePlayerId = -1;
				var i_activeIdx = -1;
				if (reveal.i_cardFullHeight > 0) {
					var i_partCardHeight = reveal.i_cardFullHeight / 4;
					i_activeIdx = Math.floor((i_scrollAmount + i_partCardHeight) / reveal.i_cardFullHeight);
					i_activeIdx = Math.max(i_activeIdx, 0);
					var i_activePlayerId = reveal.o_cachedStory.playerOrder[i_activeIdx];
				}

				// update the class for the active player
				if (i_activePlayerId > -1) {
					var jPlayerBar = $("#revealPlayerBar");
					var jScrollPanel = jPlayerBar.find(".scrollPanel");
					var jRevealPlayerContainer = jScrollPanel.children(".playerTokenContainer[playerId=" + i_activePlayerId + "]");
					jRevealPlayerContainer.addClass('cardActive');
					jRevealPlayerContainer.siblings().removeClass('cardActive');

					// scroll to the active player if they are not visible
					var i_tokenWidth = jRevealPlayerContainer.fullWidth(true, true, true);
					var i_playerBarWidth= jPlayerBar.width();
					var i_tokenLeft = i_activeIdx * i_tokenWidth;
					var i_tokenRight = i_tokenLeft + i_tokenWidth;
					var i_visLeft = jPlayerBar.scrollLeft();
					var i_visRight = i_visLeft + i_playerBarWidth;
					if (i_visLeft > i_tokenLeft) {
						// scrolled too far to the right to be able to see the full token
						jPlayerBar.smoothScroll(i_tokenLeft, i_duration, 'swing', true);
					} else if (i_visRight < i_tokenRight) {
						// scrolled too far to the left to be able to see the full token
						jPlayerBar.smoothScroll(i_tokenRight - i_playerBarWidth, i_duration, 'swing', true);
					}
				}
			},

			setPlayer1: function(i_oldPlayer1Id, i_newPlayer1Id, o_newPlayer1) {
				// we do this after the game code updates player tokens to capture the player1 crown
				reveal.updatePlayer(o_newPlayer1);
				if (i_oldPlayer1Id != i_newPlayer1Id) {
					// remove the crown from the old player1
					reveal.updatePlayer(players.getPlayer(i_oldPlayer1Id));
				}

				// update player1 controls
				reveal.updatePlayer1Controls(); 
			},

			updatePlayer1Controls: function() {
				if (reveal.o_cachedStory === null)
					return;
				var jReveal = $("#reveal");
				var jPlayerBar = $("#revealPlayerBar");
				var jPlayer1Controls = jPlayerBar.children(".player1Controls");

				// generally show or hide the player1 functions
				if (playerFuncs.isPlayer1()) {
					jReveal.addClass("player1");
				} else {
					jReveal.removeClass("player1");
				}

				// show the next story/new game button if all players are ready
				var jNextStory = jPlayer1Controls.find("input.nextStory");
				var jNewGame = jPlayer1Controls.find("input.newGameSamePlayers");
				if (playerFuncs.allPlayersReady()) {
					var b_lastStory = (reveal.o_cachedStory.nextStory == "");
					if (b_lastStory) {
						jNextStory.hide();
						jNewGame.show();
					} else {
						jNewGame.hide();
						jNextStory.show();
					}
				}

				// center the controls
				var i_playerBarWidth = jPlayerBar.fullWidth(true, false, false);
				var i_controlsWidth = jPlayer1Controls.fullWidth(true, false, true);
				jPlayer1Controls.css({
					'left': ((i_playerBarWidth - i_controlsWidth) / 2) + 'px'
				});
			},

			updateRateGameControls: function() {
				var jRateGame = $("#revealRateGame");
				jRateGame.hide();

				if (playerFuncs.allPlayersReady()) {
					var b_lastStory = (reveal.o_cachedStory.nextStory == "");
					if (b_lastStory) {
						// animate the showing of the rate game
						if (!jRateGame.hasClass('showing')) {
							jRateGame.addClass('showing');
							jRateGame.hide();

							var f_showRateGame = function() {
								var f_actuallyShow = function() {
									jRateGame.show();
									jRateGame.css({
										'right': -(jRateGame.width() + jRateGame.paddingLeft()) + 'px'
									});
									jRateGame.finish().animate({
										'right': parseInt(jRateGame.attr('finalPos'))
									}, 400, 'spring');
								};

								var d_timeSinceLoad = Date.timeSinceLoad();
								if (d_timeSinceLoad < 2000) {
									// If we've loaded reloaded the page after the end of the game, then just it.
									f_actuallyShow();
								} else {
									// Wait for some time (10s) after the last card is revealed before showing
									// the rating window.
									setTimeout(f_actuallyShow, 10000);
								}
							};

							if (jRateGame.attr('finalPos') === undefined) {
								// We're having trouble showing the rating window. Maybe need to wait for the
								// css 'right' property to be set.
								setTimeout(function() {
									jRateGame.attr('finalPos', parseInt(jRateGame.css('right')));	
									f_showRateGame();
								}, 2000);
							} else {
								f_showRateGame();
							}
						}
					}
				} else {
					jRateGame.removeClass('showing');
				}
			},

			controlNextStoryClick: function() {
				// Causes the server to push an event with the updated game, story, players, and cards.
				// We pass the new turn value, rather than just "setNextSharingTurn", so that we don't
				// accidentally progress multiple turns in the case that the next story button is
				// clicked multiple times.
				outgoingMessenger.pushData({
					command: 'setSharingTurn',
					turn: (game.o_cachedGame.currentTurn - playerFuncs.playerCount() + 1)
				});
			},

			controlNewGameSamePlayersClick: function() {
				outgoingMessenger.pushData({
					command: 'createGame'
				});
			},

			controlRateGameClick: function(s_rating) {
				var jRateGame = $("#revealRateGame");
				var jAfterRating = jRateGame.find(".afterRating");

				outgoingMessenger.pushData({
					command: 'rateGame',
					roomCode: game.o_cachedGame.roomCode,
					rating: s_rating
				});

				jAfterRating.show();
			},
		};
		a_toExec[a_toExec.length] = {
			"name": "reveal_overrides",
			"dependencies": ["jQuery", "jqueryExtension.js", "game"],
			"function": function() {
				var jWindow = $(window);

				// choose a card size
				var i_winHeight = jWindow.height();
				var i_cardHeight = reveal.i_cardWidth * reveal.f_cardRatio;
				i_cardHeight = Math.min(i_cardHeight, (i_winHeight - 150) / 1.25);
				reveal.i_cardHeight = i_cardHeight;
				reveal.i_cardWidth = i_cardHeight / reveal.f_cardRatio;
				reveal.i_cardPadding = reveal.f_paddingRatio * reveal.i_cardHeight;

				// override some of the game functions
				// we call reveal.updatePlayer immediately after game.updatePlayer so that we can copy the player token created by the game code
				var oldAddPlayer = game.updatePlayer;
				var oldSetPlayer1 = game.setPlayer1;
				game.updatePlayer = function(o_player) {
					oldAddPlayer(o_player);
					reveal.updatePlayer(o_player);
				}
				game.setPlayer1 = function(i_id) {
					var i_oldId = playerFuncs.getPlayer1Id();
					oldSetPlayer1(i_id);
					var o_player = playerFuncs.getPlayer(i_id);
					if (o_player !== null && o_player !== undefined) {
						reveal.setPlayer1(i_oldId, i_id, o_player);
					}
				}

				// add some event handlers
				jWindow.scroll(reveal.onWindowScroll);
				jWindow.on('gestureChange', reveal.onWindowGestureChange);
			}
		}
		a_toExec[a_toExec.length] = {
			"name": "reveal.php",
			"dependencies": ["index.php", "game.php", "playerFuncs"],
			"function": function() {
				// set some values
				if (serverStats !== undefined &&
					serverStats['currentStory'] !== undefined &&
					JSON.parse(serverStats['currentStory']) != "")
				{
					commands.updateStory(JSON.parse(serverStats['currentStory']));
					var a_cards = JSON.parse(serverStats['currentCards']);
					$.each(a_cards, function(k, o_card) {
						commands.updateCard(o_card);
					});
				} else {
					commands.updateStory(null);
				}
			}
		}
	</script>

	<div class="revealCardBar"></div>
	<div id="revealHidyBar"></div>
	<div id="revealPlayerBar">
		<div class="playerBar">
			<div class="scrollPanel"></div>
		</div>
		<div class="player1Controls">
			<input type="button" class="nextStory" value="Next Story" onclick="reveal.controlNextStoryClick();" />
			<input type="button" class="newGameSamePlayers" value="Start New Game" onclick="reveal.controlNewGameSamePlayersClick();" />
		</div>
	</div>
	<div class="leaveGame"><div>&#x2B05;</div></div>
	<div id="revealRateGame">
		<span>Did you enjoy this game?</span>
		<div class="thumbsContainer"
			 ><input type="button" value="&#x1f44d;" onclick="reveal.controlRateGameClick('good');" style="padding-top:3px; left:0;"
			/><input type="button" value="&#x1f44e;" onclick="reveal.controlRateGameClick('bad');" style="padding-bottom:3px; left:41px;"
		/></div>
		<div class="afterRating">
			Thanks!
		</div>
		<span>Feel free to <a href="downloadGame.php?" target="_blank">download this game</a> and <a href="feedback.php?" target="_blank">provide feedback</a>!</span>
	</div>
	<div id="revealOverlay" style="display: none;" onclick="$(this).hide();">
		<img class="card0 currentImage centered" />
		<div class="card1 currentText centered"></div>
	</div>

	<!-- templates -->
	<div id="revealCard" style="display:none;">
		<div class="revealCard gameCard centered" cardId="__cardId__">
			<div class="card0" style="display: none;">
				<img class="userContents currentImage centered" />
			</div>
			<div class="card1" style="display: none;">
				<div class="userContents currentText centered"></div>
			</div>
			<div class="revealHint centered">Reveal your card so that other people can see it.</div>
		</div>
	</div>
	<div id="revealPlayerTemplate" style="display:none;">
		<div class="playerTokenContainer" playerId="__playerId__">
			<div class="revealPlayerToken"></div>
			<div class="revealPlayerName"></div>
		</div>
	</div>
</div>
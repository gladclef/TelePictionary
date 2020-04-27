<div class="content" id="reveal" style="display: none;">
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

				// upate the player tokens
				reveal.updatePlayerTokenOrder(o_story.playerOrder);
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
				var jPlayerBar = $("#revealPlayerBar");
				var jScrollPanel = jPlayerBar.children(".scrollPanel");
				var i_tokenWidth = -1;

				for (var i = 0; i < a_playerIdsInOrder.length-1; i++) {
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
					var i_nextId = a_playerIdsInOrder[i+1];
					var jRevealCardBar = $("#revealCardBar");
					var jCard = jRevealCardBar.find(".gameCard[playerId=" + i_playerId + "]");
					var jNextCard = jRevealCardBar.find(".gameCard[playerId=" + i_nextId + "]");
					if (jCard.length > 0 && jNextCard.length > 0) {
						jCard.remove();
						jCard.insertBefore(jNextCard);
						
						var o_player = playerFuncs.getPlayer(i_playerId);
						var i_cardId = jCard.attr('cardId');
						var o_card = reveal.a_cards[i_cardId];
						reveal.registerCardEvents(jCard, o_card, o_player);
					}
				};

				// other things
				reveal.indicateActivePlayerCard();
			},

			addPlayer: function(o_player) {
				// we call this immediately after game.addPlayer so that we can copy the player token created by the game code
				var jPlayersCircle = $("#gamePlayersCircle");
				var jPlayerBar = $("#revealPlayerBar");
				var jScrollPanel = jPlayerBar.children(".scrollPanel");
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
					var containerWidths = jScrollPanel.children().length * jRevealPlayerContainer.fullWidth(true, true);
					containerWidths += 4; // this is for the cardActive green border around the player token

					// set the position of the player token
					var i_tokenWidth = jRevealPlayerContainer.fullWidth(true, true, true);
					var i_numChildren = jScrollPanel.children().length;
					jRevealPlayerContainer.css({
						'left': (i_tokenWidth * (i_numChildren-1)) + 'px'
					});

					// update the scroll panel
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
				}

				// copy over the new player token
				var jRevealPlayerToken = jRevealPlayerContainer.find(".revealPlayerToken");
				jRevealPlayerToken.children().remove();
				jRevealPlayerToken.html(jGamePlayerToken.html());
				reveal.registerPlayerTokenEvents(jRevealPlayerToken, o_player);
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
					return;
				}
				if (reveal.a_cachedCardIdToPlayerId[o_card.id] === null) {
					return;
				}
				reveal.a_cards[o_card.id] = o_card;

				// get the player for this card
				var o_player = playerFuncs.getPlayer(reveal.a_cachedCardIdToPlayerId[o_card.id]);

				// create a new card for the player token
				var jPlayerBar = $("#revealPlayerBar");
				var jRevealPlayerContainer = jPlayerBar.find(".playerTokenContainer[playerId=" + o_player.id + "]");
				var jRevealCardBar = $("#revealCardBar");
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

				// indicate the active player according the the card scroll position
				reveal.indicateActivePlayerCard();

				// register events
				reveal.registerCardEvents(jCard, o_card, o_player);
			},

			registerCardEvents: function(jCard, o_card, o_player) {
				var f_cardClick = function() { reveal.cardClick(jCard, o_card, o_player); };
				jCard.off("click").on("click", f_cardClick);
			},

			t_winScroll: null,
			playerClick: function(jPlayerToken, o_player) {
				// figure out the player order index
				var i_activeIdx = reveal.o_cachedStory.playerOrder.indexOf(o_player.id);

				// scroll to the active card
				var jWindow = $(window);
				var jRevealCardBar = $("#revealCardBar");
				var i_scrollPos = 0;
				if (i_activeIdx > 0) {
					i_scrollPos = reveal.i_cardFullHeight * i_activeIdx;
					i_scrollPos += parseInt(jRevealCardBar.css('padding-top'));
				}
				jWindow.smoothScroll(i_scrollPos, 300);
			},

			cardClick: function(jCard, o_card, o_player) {
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

				// some aesthetic stuff
				if (i_scrollAmount > 12) {
					jHidyBar.css({ 'border-bottom-color': '#5555FF' });
				} else {
					jHidyBar.css({ 'border-bottom-color': '#8888FF' });
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
					var jScrollPanel = jPlayerBar.children(".scrollPanel");
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
			}
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
				// we call reveal.addPlayer immediately after game.addPlayer so that we can copy the player token created by the game code
				var oldAddPlayer = game.addPlayer;
				var oldSetPlayer1 = game.setPlayer1;
				game.addPlayer = function(o_player) {
					oldAddPlayer(o_player);
					reveal.addPlayer(o_player);
				}
				game.setPlayer1 = function(i_id) {
					oldSetPlayer1(i_id);
					var o_player = playerFuncs.getPlayer(i_id);
					if (o_player !== null && o_player !== undefined)
						reveal.addPlayer(o_player); // we do this so that we also capture the player1 crown
				}

				// add some event handlers
				jWindow.scroll(reveal.onWindowScroll);
				jWindow.on('gestureChange', reveal.onWindowGestureChange);
			}
		}
		a_toExec[a_toExec.length] = {
			"name": "reveal.php",
			"dependencies": ["index.php", "game.php"],
			"function": function() {
				// set some values
				if (serverStats !== undefined && serverStats['currentStory'] !== undefined && serverStats['currentStory'] != "") {
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

	<div id="revealCardBar"></div>
	<div id="revealHidyBar"></div>
	<div id="revealPlayerBar">
		<div class="scrollPanel"></div>
	</div>
	<div id="revealOverlay" style="display: none;" onclick="$(this).hide();">
		<img class="card0 currentImage centered" />
		<div class="card1 currentText centered"></div>
	</div>

	<!-- templates -->
	<div id="revealCard" style="display:none;">
		<div class="gameCard centered" cardId="__cardId__">
			<div class="card0" style="display: none;">
				<img class="currentImage centered" />
			</div>
			<div class="card1" style="display: none;">
				<div class="currentText centered"></div>
			</div>
		</div>
	</div>
	<div id="revealPlayerTemplate" style="display:none;">
		<div class="playerTokenContainer" playerId="__playerId__">
			<div class="revealPlayerToken"></div>
		</div>
	</div>
</div>
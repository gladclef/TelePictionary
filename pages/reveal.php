<div class="content" id="reveal" style="display: none;">
	<script type="text/javascript">
		reveal = {
			i_cardWidth: 700,
			i_cardHeight: (625.0/500.0)*700,

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
					var jRevealPlayerTemplate = $("#revealPlayerTemplate");
					jRevealPlayerContainer = $(jRevealPlayerTemplate.html());
					jRevealPlayerContainer.attr('playerId', o_player.id);
					jScrollPanel.append(jRevealPlayerContainer);
					var containerWidths = jScrollPanel.children().length * jRevealPlayerContainer.fullWidth(true, true);
					jScrollPanel.css({ 'width': containerWidths + 'px' })
				}

				// copy over the new player token
				var jRevealPlayerToken = jRevealPlayerContainer.find(".revealPlayerToken");
				jRevealPlayerToken.children().remove();
				jRevealPlayerToken.html(jGamePlayerToken.html());
			},

			getCardMaximizedPosition: function() {
				var jPlayerBar = $("#revealPlayerBar");
				return {
					'left': ($(document).width() - reveal.i_cardWidth)/2 + 'px',
					'top': jPlayerBar.fullHeight(true, true) + 50 + 'px'
				};
			},

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

				// create a new mini card for the player token
				var jPlayerBar = $("#revealPlayerBar");
				var jRevealPlayerContainer = jPlayerBar.find(".playerTokenContainer[playerId=" + o_player.id + "]");
				var jRevealPlayerMiniCard = jRevealPlayerContainer.find(".revealPlayerMiniCard");
				var jCard = jRevealPlayerMiniCard.children();
				var jCardTemplate = $("#revealCard");
				var b_isMaximized = false;
				if (jCard.length > 0)
				{
					b_isMaximized = !jCard.hasClass('minimized');
				}
				jCard.remove();
				jCard = $(jCardTemplate.html());
				jCard.attr('cardId', o_card.id);
				jCard.css({
					'width': reveal.i_cardWidth + 'px',
					'height': reveal.i_cardHeight + 'px'
				});

				// maximize or minimize the card, as appropriate
				jRevealPlayerMiniCard.append(jCard);
				if (b_isMaximized) {
					jCard.css(reveal.getCardMaximizedPosition());
					reveal.maximizeRevealCard(jCard);
				} else {
					reveal.minimizeRevealCard(jCard);
				}
				jCard.finish();

				// add onclick properties
				var f_maxMinCard = function() { reveal.autoMaximizeRevealCard(jCard); };
				jCard.off("click").on("click", f_maxMinCard);
			},

			autoMaximizeRevealCard: function(jCard) {
				// only maximize cards
				// cards can be minimized by maximizing another card or changed automatically when somebody reveals their card
				if (jCard.hasClass('minimized'))
				{
					// minimize all the other cards
					var jCards = $("#revealPlayerBar").find(".gameCard");
					$.each(jCards, function(k, h_otherCard) {
						// skip this card
						if (h_otherCard == jCard[0])
							return;

						// minimize other cards
						var jOtherCard = $(h_otherCard);
						if (!jOtherCard.hasClass('minimized')) {
							reveal.minimizeRevealCard(jOtherCard);
						}
					});

					// maximize this card
					reveal.maximizeRevealCard(jCard);
				}
			},

			maxMinRevealCard: function(jCard, s_action) {
				var i_transitionTime = 200;
				var o_card = reveal.getCard(jCard.attr('cardId'));
				var o_player = playerFuncs.getPlayer(o_card.authorId);
				var jPlayerBar = $("#revealPlayerBar");
				var jRevealPlayerContainer = jPlayerBar.find(".playerTokenContainer[playerId=" + o_player.id + "]");
				var jRevealPlayerMiniCard = jRevealPlayerContainer.find(".revealPlayerMiniCard");
				var a_miniCardPos = jRevealPlayerMiniCard.fixedPosition();
				var animationOptions = { 'duration': i_transitionTime, 'queue': false, 'easing': 'linear' };
				a_miniCardPos.left += 'px';
				a_miniCardPos.top += 'px';

				if (s_action == 'minimize') {
					// use the game code to minimize the card
					if (jCard.hasClass('minimized'))
						jCard.removeClass('minimized');
					game.minimizeGameCard(jCard, o_card.type, animationOptions);

					// put the card back in the player bar
					jCard.css(reveal.getCardMaximizedPosition());
					jCard.css({ 'position': 'fixed' });
					var animationOptions2 = { 'complete': function() {
						jCard.css({ 'position': 'relative', 'left': 0, 'top': 0 });
					}};
					$.extend(animationOptions2, animationOptions);
					jCard.animate(a_miniCardPos, animationOptions2);
				} else { // if (s_action == 'maximize')
					// use the game code to maximize the card
					if (!jCard.hasClass('minimized'))
						jCard.addClass('minimized');
					game.minimizeGameCard(jCard, o_card.type, animationOptions);

					// move the card out of the player bar to center stage
					jCard.css(a_miniCardPos);
					jCard.css({ 'position': 'fixed' });
					jCard.animate(reveal.getCardMaximizedPosition(), animationOptions);
				}
			},

			minimizeRevealCard: function(jCard) {
				reveal.maxMinRevealCard(jCard, 'minimize');
			},

			maximizeRevealCard: function(jCard) {
				reveal.maxMinRevealCard(jCard, 'maximize');
			}
		};

		a_toExec[a_toExec.length] = {
			"name": "reveal_overrides",
			"dependencies": ["jQuery", "jqueryExtension.js", "game"],
			"function": function() {
				// override some of the game functions
				// we call reveal.addPlayer immediately after game.addPlayer so that we can copy the player token created by the game code
				var oldAddPlayer = game.addPlayer;
				game.addPlayer = function(o_player) {
					oldAddPlayer(o_player);
					reveal.addPlayer(o_player);
				}
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

	<div id="revealPlayerBar">
		<div class="scrollPanel"></div>
	</div>
	<div id="revealCard" style="display:none;">
		<div class="gameCard centered" cardId="__cardId__">
			<div class="noData">
				Waiting to retrieve data from the server...
			</div>
			<div class="card1" style="display: none;">
				<img class="previousImage hideMeFirst centered" />
				<div class="currentText centered"></div>
			</div>
			<div class="card0" style="display: none;">
				<span class="previousText centered"></span><br />
				<br />
				<img src="" class="currentImage centered" command="setCardImage" style="display: none;" />
			</div>
		</div>
	</div>
	<div id="revealPlayerTemplate" style="display:none;">
		<div class="playerTokenContainer" playerId="__playerId__">
			<div class="revealPlayerToken"></div>
			<div class="revealPlayerMiniCard"></div>
		</div>
	</div>
</div>
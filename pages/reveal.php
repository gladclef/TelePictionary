<div class="content" id="reveal" style="display: none;">
	<script type="text/javascript">
		reveal = {
			o_cachedStory: null,

			addPlayer: function(o_player) {
				// we call this immediately after game.addPlayer so that we can copy the player token created by the game code
				var jPlayersCircle = $("#gamePlayersCircle");
				var jPlayerBar = $("#revealPlayerBar");
				var jGamePlayerToken = jPlayersCircle.find(".playerToken[playerId=" + o_player.id + "]");

				// get the container
				var jRevealPlayerContainer = jPlayerBar.find(".playerTokenContainer[playerId=" + o_player.id + "]");
				if (jRevealPlayerContainer.length == 0)
				{
					var jRevealPlayerTemplate = $("#revealPlayerTemplate");
					jRevealPlayerContainer = $(jRevealPlayerTemplate.html());
					jRevealPlayerContainer.attr('playerId', o_player.id);
					jPlayerBar.append(jRevealPlayerContainer);
				}

				// copy over the new player token
				var jRevealPlayerToken = jRevealPlayerContainer.find(".revealPlayerToken");
				jRevealPlayerToken.children().remove();
				jRevealPlayerToken.html(jGamePlayerToken.html());

				// create a new mini card for the player token
				var jRevealPlayerMiniCard = jRevealPlayerContainer.find(".revealPlayerMiniCard");
				var jCard = jRevealPlayerMiniCard.children();
				var jCardTemplate = $("#revealCard");
				var b_isMaximized = (jCard.length > 0 && !jCard.hasClass('minimized'));
				jCard.remove();
				jCard = $(jCardTemplate.html());
				jCard.attr('cardId', -1)
				jRevealPlayerMiniCard.append(jCard);
				if (b_isMaximized) {
					reveal.maximizeCard(jCard);
				} else {
					reveal.minimizeCard(jCard);
				}
			},

			minimizeCard: function(jCard) {

			},

			maximizeCard: function(jCard) {

			}
		};

		a_toExec[a_toExec.length] = {
			"name": "reveal.php",
			"dependencies": ["jQuery", "jqueryExtension.js", "game"],
			"function": function() {
				// override some of the game functions
				var oldAddPlayer = game.addPlayer;
				game.addPlayer = function(o_player) {
					oldAddPlayer(o_player);
					reveal.addPlayer(o_player);
				}
			}
		}
	</script>

	<div id="revealPlayerBar">
		<div class="scrollPanel"></div>
	</div>
	<div id="revealCard">
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
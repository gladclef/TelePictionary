<div class="content" id="about" style="display: none;">
	<script type="text/javascript">
		about = {
			showJoinCreate: function() {
				$('#aboutJoinCreate').show(200);
			},

			setUsername: function(b_synchronous) {
				var jPlayerTag = $('#aboutPlayerTag');
				var options = {};
				if (b_synchronous) {
					options['async'] = false;
				}
				outgoingMessenger.pushData({
					command: 'setUsername',
					username: jPlayerTag.val()
				}, null, options);
			},

			joinGame: function() {
				var jRoomCode = $('#aboutRoomCode');
				about.setUsername(true);
				outgoingMessenger.pushData({
					command: 'joinGame',
					roomCode: jRoomCode.val()
				}, function(o_command, b_postProcessed) {
					if (b_postProcessed) {
						clearTimeout(outgoingMessenger.noPollTimeout);
						outgoingMessenger.noPoll = null;
					}
				});
			},

			createGame: function() {
				about.setUsername(true);
				outgoingMessenger.pushData({
					command: 'createGame'
				});
			}
		};
		// Here to indicate this script has executed.
		// We do this because the "about" div gets registered as being accessible by javascript.
		aboutJsObj = true;

		setTimeout(function() {
			var jPlayerTag = $('#aboutPlayerTag');
			if (jPlayerTag.val() != "")
			{
				about.showJoinCreate();
			}
		}, 1000);
	</script>

	<h1 class="centered">TelePictionary!</h1>
	<br />
	<div class="centered" style="width: 400px;">This game lets you play the classic words-to-pictures-to-words game that you used to play in <i>non-quarantine</i> from a <i>socially-aware</i> distance!<br />Words and pictures are managed by the game and sent to the right person automatically. Then at the end of each game, watch the misinterpretted hilarity get revealed!</div>
	<br />
	<div class="centered bordered" style="width: 500px;">
		<div class="centered" style="width: 400px;">Enter your player tag:</div>
		<div class="centered" style="width: 200px; padding: 25px 0">
			<input id="aboutPlayerTag" class="centered" type="text" style="width: 200px;" placeholder="player tag" onkeyup="about.showJoinCreate();" value="<?php echo $o_globalPlayer->getName(); ?>">
		</div>
	</div>
	<br />
	<div id="aboutJoinCreate" class="centered bordered" style="width: 500px; display: none;">
		<text class="centered">Join or Create New</text>
		<div style="width: 495px;">
			<div style="width: 244px; display: inline-block; padding: 25px 0; border-right: 1px solid black;">
				<div class="centered" style="width: 115px;">
					<input id="aboutRoomCode" type="text" style="width: 65px;" placeholder="room code"><input type="button" value="Join" onclick="about.joinGame();">
				</div>
			</div>
			<div style="width: 244px; display: inline-block; padding: 25px 0;">
				<div class="centered" style="width: 95px;">
					<input type="button" value="Create New" onclick="about.createGame();">
				</div>
			</div>
		</div>
	</div>
	<br />
</div>
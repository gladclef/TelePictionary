<?php

require_once(dirname(__FILE__) . "/globals.php");
require_once(dirname(__FILE__) . "/common_functions.php");
require_once(dirname(__FILE__) . "/db_query.php");
require_once(dirname(__FILE__) . "/../objects/player.php");
require_once(dirname(__FILE__) . "/../objects/game.php");
require_once(dirname(__FILE__) . "/../objects/story.php");
require_once(dirname(__FILE__) . "/../objects/card.php");
require_once(dirname(__FILE__) . "/../objects/image.php");
require_once(dirname(__FILE__) . "/../communication/longPoll/private.php");

global $o_globalPlayer;
$o_globalPlayer = player::getGlobalPlayer(TRUE);

ob_start();
?>
		<script type="text/javascript" src="<?php echo $global_path_to_jquery; ?>"></script>
		<script type="text/javascript" src="<?php echo $global_path_to_jquery_ui; ?>.min.js"></script>
		<script type="text/javascript" src="<?php echo $global_path_to_d3; ?>"></script>
		<script type="text/javascript" src="communication/longPoll/pushPull.js"></script>
		<script type="text/javascript" src="js/common.js"></script>
		<script type="text/javascript" src="js/index.js"></script>
		<script type="text/javascript" src="js/javascriptExtension.js"></script>
		<script type="text/javascript" src="js/jqueryExtension.js"></script>
		<script type="text/javascript" src="js/toExec.js"></script>
		<script type="text/javascript" src="js/control.js"></script>
		<script type="text/javascript" src="js/commands.js"></script>
		<script type="text/javascript" src="js/player.js"></script>
		<script type="text/javascript" src="js/jquery.qrcode.min.js"></script>
		<script>
			if (window.a_toExec === undefined) window.a_toExec = [];
			if (window.serverStats === undefined) window.serverStats = {};

			// "dependencies": ["jQuery", "jqueryExtension.js", "commands.js", "playerFuncs", "game", "control.js", "reveal_overrides"],
			window.f_commonStartupJs = function() {
				// set some things
				playerFuncs.setLocalPlayer(serverStats['localPlayerId']);
				game.setLocalPlayer(serverStats['localPlayerId']);

				// show the content
				var s_content = "about";
				if (serverStats['isInReveal']) {
					s_content = "reveal";
				} else if (serverStats['isInGame']) {
					s_content = "game";
				}
				commands.showContent(s_content);
			}
		</script>
<?php
$s_includeScripts = append_js_timestamps(ob_get_contents());
ob_end_clean();



ob_start();
?>
		<link rel="stylesheet" type="text/css" href="<?php echo $global_path_to_jquery_ui; ?>.min.css" />
		<link rel="stylesheet" type="text/css" href="<?php echo $global_path_to_jquery_ui; ?>.theme.min.css" />
		<link rel="stylesheet" type="text/css" href="css/common.css" />
		<link rel="stylesheet" type="text/css" href="css/game.css" />
		<link rel="stylesheet" type="text/css" href="css/reveal.css" />
<?php
$s_includeStylesheets = ob_get_contents();
ob_end_clean();



function includeContents()
{
	global $o_globalPlayer;
	require(dirname(__FILE__) . "/../pages/about.php");
	require(dirname(__FILE__) . "/../pages/createUser.php");
	require(dirname(__FILE__) . "/../pages/gameSetup.php");
	require(dirname(__FILE__) . "/../pages/game.php");
	require(dirname(__FILE__) . "/../pages/reveal.php");
}



function includeGeneralError()
{
	ob_start();
	?>
	<!-- commands.showError() will:
		 1. Delete the contents of generalError.
	     2. Create a copy of generalErrorReporter and generalErrorDismisser inside of generalError.
	     3. Add a click listener to the new reported to evaluate it's "onclickExec".
	     4. Add a click listener to the new dismisser to close the error. -->
	<script type="text/javascript">
		reportErrorHideTimeout = null;
		reportError = function(h_reporter) {
			var jReporter = $(h_reporter);
			var jGeneralError = jReporter.parent();

			// don't do anything if already reported
			if (jReporter.hasClass("reported")) {
				return;
			}

			// report the error
			if (outgoingMessenger !== undefined && outgoingMessenger !== null) {
				var reportables = {
					'error': jGeneralError.html(),
					'client-game': ((game !== undefined) ? game.o_cachedGame : null),
					'client-players': players,
					'client-events': outgoingMessenger.latestEvents,
				};
				outgoingMessenger.pushData({
					'command': 'reportError',
					'reportables': reportables,
				});
			}

			// show that the error has been reported and close the error
			jReporter.addClass("reported");
			jReporter.text("Thanks!");
			if (reportErrorHideTimeout !== null)
				clearTimeout(reportErrorHideTimeout);
			reportErrorHideTimeout = setTimeout(function() {
				jGeneralError.finish().hide();
				reportErrorHideTimeout = null;
			}, 2000);
		}
	</script>
	<div class="centered generalError"></div>
	<span><span class="generalErrorReporter orig" onclickExec="reportError(this);">Report Error</span></span>
	<span><span class="generalErrorDismisser orig">X</span></span>
	<?php
	$s_includeGeneralError = ob_get_contents();
	ob_end_clean();
	echo $s_includeGeneralError;
}



function includeServerStats()
{
	global $o_globalPlayer;
	$i_gameState = $o_globalPlayer->getGameState()[0];
	$b_hasUsername = $i_gameState > GAME_PSTATE::NOT_READY;
	$b_isInGame = ($b_hasUsername) ? ($i_gameState >= GAME_PSTATE::WAITING) : false;
	$b_isInReveal = ($b_isInGame && ($i_gameState >= GAME_PSTATE::REVEALING));
	$o_game = ($b_isInGame) ? $o_globalPlayer->getGame() : null;
	$a_latestEvents = ($b_isInGame) ? _ajax::getLatestEvents($o_game->getRoomCode()) : array();
	$o_story = ($b_isInGame) ? $o_game->getCurrentStory() : null;
	$a_cards = ($o_story === null) ? array() : $o_story->getCards(TRUE);
	$s_latestEvents = (is_string($a_latestEvents)) ? "[]" : json_encode($a_latestEvents);
	$s_hasUsername = ($b_hasUsername) ? "true" : "false";
	$s_isInGame = ($b_isInGame) ? "true" : "false";
	$s_isInReveal = ($b_isInReveal) ? "true" : "false";
	$s_currentStory = json_encode(json_encode(($o_story !== null) ? $o_story->toJsonObj() : ""));
	$s_currentCards = json_encode(json_encode($a_cards));
	echo "serverStats['latestEvents'] = {$s_latestEvents};\r\n";
	echo "serverStats['hasUsername'] = {$s_hasUsername};\r\n";
	echo "serverStats['isInGame'] = {$s_isInGame};\r\n";
	echo "serverStats['isInReveal'] = {$s_isInReveal};\r\n";
	echo "serverStats['localPlayer'] = " . json_encode(json_encode($o_globalPlayer->toJsonObj())) . ";\r\n";
	echo "serverStats['localPlayerId'] = {$o_globalPlayer->getId()};\r\n";
	echo "serverStats['currentStory'] = {$s_currentStory};\r\n";
	echo "serverStats['currentCards'] = {$s_currentCards};\r\n";
}
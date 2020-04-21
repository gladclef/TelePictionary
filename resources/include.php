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
$o_globalPlayer = player::getGlobalPlayer();

ob_start();
?>
		<script type="text/javascript" src="<?php echo $global_path_to_jquery; ?>"></script>
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
		</script>
<?php
$s_includeScripts = ob_get_contents();
ob_end_clean();



ob_start();
?>
		<link rel="stylesheet" type="text/css" href="css/common.css" />
		<link rel="stylesheet" type="text/css" href="css/game.css" />
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



function includeServerStats()
{
	global $o_globalPlayer;
	$b_hasUsername = $o_globalPlayer->getGameState()[0] > 0;
	$b_isInGame = ($b_hasUsername) ? ($o_globalPlayer->getGameState()[0] >= 2) : false;
	$a_latestEvents = ($b_isInGame) ? _ajax::getLatestEvents($o_globalPlayer->getGame()->getRoomCode()) : array();
	$s_latestEvents = (is_string($a_latestEvents)) ? "[]" : json_encode($a_latestEvents);
	$s_hasUsername = ($b_hasUsername) ? "true" : "false";
	$s_isInGame = ($b_isInGame) ? "true" : "false";
	echo "serverStats['latestEvents'] = {$s_latestEvents};\r\n";
	echo "serverStats['hasUsername'] = {$s_hasUsername};\r\n";
	echo "serverStats['isInGame'] = {$s_isInGame};\r\n";
	echo "serverStats['localPlayer'] = {$o_globalPlayer->getId()};\r\n";
}
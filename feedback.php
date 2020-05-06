<?php

require_once(dirname(__FILE__) . "/resources/include.php");
global $o_globalPlayer;
global $feedback_email;

// get the game
if (!isset($_GET["roomCode"]) && !isset($_POST["roomCode"])) {
	echo "roomCode must be set!";
	die();
}
$s_roomCode = get_post_var("roomCode", $_GET["roomCode"]);
$o_game = game::loadByRoomCode($s_roomCode);
if ($o_game === null) {
	echo "Game with roomCode \"{$_GET['roomCode']}\" not found!";
	die();
}

// check if we just got feedback
if (isset($_POST['feedbackVal'])) {
	$s_userEmail = trim(get_post_var("userEmail"));
	$s_userEmail = ($s_userEmail == "") ? "none" : $s_userEmail;
	$s_subject = "TelePictionary Feedback";
	$s_content = "Game: {$o_game->getRoomCode()}\r\n" .
	             "Player: {$o_globalPlayer->getId()}/{$o_globalPlayer->getName()}\r\n" .
	             "Player Email: {$s_userEmail}\r\n" .
	             "\r\n" .
	             "Feedback:\r\n" .
	             $_POST['feedbackVal'];
	$s_headers = 'From: TelePictionary@bbean.us';
	mail($feedback_email, $s_subject, $s_content, $s_headers);
	echo "Thanks for your feedback!";
	die();
}

?><!DOCTYPE html>
<html>
	<head>
		<meta content="text/html;charset=utf-8" http-equiv="Content-Type">
		<meta content="utf-8" http-equiv="encoding">
	</head>
	<body>
		<form method="post">
			Thanks for playing TelePictionary!<br />Any comments you provide here will be forwarded to the developer to help make the game better: <br />
			<br />
			Email (optional): <input type="text" name="userEmail" style="width:200px" /><br />
			<textarea rows="5" cols="80" name="feedbackVal" placeholder="feedback"></textarea> <br />
			<input type="submit" value="Send Feedback" />
			<input type="hidden" name="playerId" value="<?php echo $o_globalPlayer->getId(); ?>" />
			<input type="hidden" name="roomCode" value="<?php echo $o_game->getRoomCode(); ?>" />
		</form>
	</body>
</html>
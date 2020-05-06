<?php

require_once(dirname(__FILE__) . "/resources/include.php");

function returnZipFile($s_zipFile, $s_downloadFileName="file.zip") {
	header($_SERVER["SERVER_PROTOCOL"] . " 200 OK");
    header("Cache-Control: public"); // needed for internet explorer
    header("Content-Type: application/zip");
    header("Content-Transfer-Encoding: Binary");
    header("Content-Length:".filesize($s_zipFile));
    header("Content-Disposition: attachment; filename={$s_downloadFileName}");
    readfile($s_zipFile);
    die();
}

// get the game
if (!isset($_GET["roomCode"])) {
	echo "roomCode must be set!";
	die();
}
$o_game = game::loadByRoomCode($_GET["roomCode"]);
if ($o_game === null) {
	echo "Game with roomCode \"{$_GET['roomCode']}\" not found!";
	die();
}

// set some values
$s_rootDir = dirname(__FILE__) . "/../../";
$s_imageDir = "{$s_rootDir}telePictionaryUserImages/";
$s_zipfilesDir = "{$s_rootDir}telePictionaryZipFiles/";
$s_zipFileName = "{$o_game->getRoomCode()}.zip";
$s_zipFile = "{$s_zipfilesDir}{$s_zipFileName}";
$s_zipLock = "{$s_zipfilesDir}{$o_game->getRoomCode()}.lock";
$s_downloadFileName = "TelePictionary_{$s_zipFileName}";

// check if the zipfile already exists
while (file_exists($s_zipLock)) {
	sleep(1);
}
if (file_exists($s_zipFile)) {
	returnZipFile($s_zipFile, $s_downloadFileName);
}

// create the zip file
file_put_contents($s_zipLock, "lock");
try {
	$zip = new ZipArchive;
	if ($zip->open($s_zipFile, ZipArchive::CREATE) === TRUE)
	{
		// add each story
		$i_playerCount = $o_game->getPlayerCount();
		for ($i_turn = 0; $i_turn < $i_playerCount; $i_turn++)
		{
			$o_story = $o_game->getStory($i_turn);
			$s_storyName = "story{$i_turn}";
			$s_dirName = $s_storyName;

			// add each card
			$a_cards = $o_story->getCards();
			foreach ($a_cards as $k => $o_card) {
				$s_cardName = "card{$k}";
				$s_fileName = $s_cardName;

				if ($o_card->isText()) {
					$zip->addFromString("{$s_dirName}/{$s_fileName}.txt", $o_card->getText());
				} else {
					$o_image = $o_card->getImage();
					$i_alias = $o_image->getAlias();
					$s_extension = $o_image->getExtension();
					$zip->addFile("{$s_imageDir}{$i_alias}.{$s_extension}", "{$s_dirName}/{$s_fileName}.{$s_extension}");
				}
			}
		}
	 
	    // All files are added, so close the zip file.
	    $zip->close();
	}
} finally {
	unlink($s_zipLock);
}

// return the zip file
returnZipFile($s_zipFile, $s_downloadFileName);

?>
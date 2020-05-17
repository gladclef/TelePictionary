<?php

require_once(dirname(__FILE__) . "/resources/include.php");

global $s_latexHeader;
global $s_latexStoryTemplate;
global $s_latexImageTemplate;
global $s_latexTextTemplate;
global $s_latexBetweenStories;
global $s_latexFooter;
global $o_game;

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

function returnPdfFile($s_pdfFile, $s_downloadFileName="file.pdf") {
	header($_SERVER["SERVER_PROTOCOL"] . " 200 OK");
    header("Cache-Control: public"); // needed for internet explorer
    header("Content-Type: application/pdf");
    header("Content-Transfer-Encoding: Binary");
    header("Content-Length:".filesize($s_pdfFile));
    header("Content-Disposition: attachment; filename={$s_downloadFileName}");
    readfile($s_pdfFile);
    die();
}

function downloadAsZip() {
	global $o_game;

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
		// don't let the script time out while creating the zip archive, which may take a while
		set_time_limit(60 * 5);

		// create the zip archive
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
}

$s_latexHeader = "\\documentclass{article}
\\usepackage{graphicx}
\\usepackage[margin=0.5in]{geometry}
\\graphicspath{ {<imagespath>} }
\\begin{document}
\\pagenumbering{gobble}

";
$s_latexStoryTemplate = "
\\begin{center}
\\topskip0pt
\\vspace*{\\fill}
\\textbf{\\LARGE <playername>'s Story}
\\vspace*{\\fill}
\\end{center}
\\clearpage

";
$s_latexImageTemplate = "\\noindent{\\LARGE <playername>'s drawing:}\\newline
\\includegraphics[width=0.75\\linewidth]{<imagename>}\\newline
\\vspace{0.2in}

";
$s_latexTextTemplate = "\\noindent{\\LARGE <playername>'s description:}\\newline
\\noindent{\\LARGE <textvalue>}\\newline
\\vspace{0.2in}

";
$s_latexBetweenStories = "\\clearpage

";
$s_latexFooter = "
\\end{document}";

function downloadAsPdf() {
	global $s_latexHeader;
	global $s_latexStoryTemplate;
	global $s_latexImageTemplate;
	global $s_latexTextTemplate;
	global $s_latexBetweenStories;
	global $s_latexFooter;
	global $o_game;

	// set some values
	$s_rootDir = dirname(__FILE__) . "/../../";
	$s_roomCode = $o_game->getRoomCode();
	$s_imageDir = "{$s_rootDir}telePictionaryUserImages/";
	$s_pdfFilesDir = "{$s_rootDir}telePictionaryPdfFiles/{$s_roomCode}/";
	$s_pdfFileName = "{$s_roomCode}.pdf";
	$s_texFileName = "{$s_roomCode}.tex";
	$s_pdfFile = "{$s_pdfFilesDir}{$s_pdfFileName}";
	$s_texFile = "{$s_pdfFilesDir}{$s_texFileName}";
	$s_pdfLock = "{$s_pdfFilesDir}{$s_roomCode}.lock";
	$s_downloadFileName = "TelePictionary_{$s_pdfFileName}";

	// check if the file already exists
	while (file_exists($s_pdfLock)) {
		sleep(1);
	}
	if (file_exists($s_pdfFile)) {
		returnPdfFile($s_pdfFile, $s_downloadFileName);
	}

	// create the directory as necessary
	if (!file_exists($s_pdfFilesDir)) {
	    mkdir($s_pdfFilesDir);
	}

	// create the file
	file_put_contents($s_pdfLock, "lock");
	try {
		// don't let the script time out while creating the pdf file, which may take a while
		set_time_limit(60 * 5);

		// set up the text
		$s_contents = str_replace("<imagespath>", $s_imageDir, $s_latexHeader);
		$a_stories = $o_game->getStories();
		$b_first = TRUE;
		foreach ($a_stories as $k => $o_story) {
			if (!$b_first) {
				$s_contents .= $s_latexBetweenStories;
			}
			$b_first = FALSE;

			$a_cards = $o_story->getCardsInOrder();
			$o_startingPlayer = $o_story->getStartingPlayer();
			$s_contents .= str_replace("<playername>", $o_startingPlayer->getName(), $s_latexStoryTemplate);
			foreach ($a_cards as $i_turn => $o_card) {
				$s_playerName = $o_card->getAuthor()->getName();
				$s_imageName = $o_card->getImage()->getFullName();
				$s_text = $o_card->getText();

				$s_val = $s_latexImageTemplate;
				if ($o_card->isText()) {
					$s_val = $s_latexTextTemplate;
				}
				$s_val = str_replace("<playername>", $s_playerName, $s_val);
				$s_val = str_replace("<textvalue>", $s_text, $s_val);
				$s_val = str_replace("<imagename>", $s_imageName, $s_val);

				$s_contents .= $s_val;
			}
		}
		$s_contents .= $s_latexFooter;

		// save to the .tex file
		file_put_contents($s_texFile, $s_contents);

		// create the pdf file
		$cmd_out = shell_exec("cd {$s_pdfFilesDir}; pdflatex {$s_texFileName}");
	} finally {
		unlink($s_pdfLock);
	}

	// return the pdf file
	returnPdfFile($s_pdfFile, $s_downloadFileName);
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

// return either a PDF or ZIP file, if specified
if (isset($_GET["downloadAs"])) {
	if ($_GET["downloadAs"] == "pdf") {
		downloadAsPdf();
	} else if ($_GET["downloadAs"] == "zip") {
		downloadAsZip();
	} else {
		echo "Bad format specified";
	}
} else {
	?>
	<div style="display:inline-block; width:400px; position:fixed; top:0; left:0;">
		<form>
			<input type="hidden" name="roomCode" value="<?php echo $_GET['roomCode']; ?>" />
			<input type="hidden" name="playerId" value="<?php echo $_GET['playerId']; ?>" />
			<input type="hidden" name="downloadAs" value="pdf" />
			<input type="submit" value="Download as PDF" style="width:400px; height:200px; font-size:34px; border-radius:10px;">
		</form>
	</div>
	<div style="display:inline-block; width:400px; position:fixed; top:0; left:420px;">
		<form>
			<input type="hidden" name="roomCode" value="<?php echo $_GET['roomCode']; ?>" />
			<input type="hidden" name="playerId" value="<?php echo $_GET['playerId']; ?>" />
			<input type="hidden" name="downloadAs" value="zip" />
			<input type="submit" value="Download as Zip Archive" style="width:400px; height:200px; font-size:34px; border-radius:10px;">
		</form>
	</div>
	<?php
}

?>
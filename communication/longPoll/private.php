<?php

require_once(dirname(__FILE__) . "/../../resources/common_functions.php");
require_once(dirname(__FILE__) . "/../../resources/globals.php");
require_once(dirname(__FILE__) . "/../../objects/player.php");
require_once(dirname(__FILE__) . "/../../objects/command.php");
require_once(dirname(__FILE__) . "/../../objects/game.php");

class _ajax {
    function serverConnect($s_connectionPurpose) {
        $socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
        if(is_resource($socket)) {
            if (socket_connect($socket, "127.0.0.1", 23456)) {
                return $socket;
            } else {
                $s_msg = "Failed to connect to 127.0.0.1:23456 to {$s_connectionPurpose}";
                error_log($s_msg);
                return $s_msg;
            }
        } else {
            $s_msg = "Failed to create socket to {$s_connectionPurpose}";
            error_log($s_msg);
            return $s_msg;
        }
    }

    function serverDisconnect($socket) {
        // tell the events server that we're don
        socket_set_block($socket);
        self::serverWrite($socket, "disconnect");

        // release the socket
        $i_warningLevel = error_reporting();
        try {
            // don't need warnings about not being connected
            error_reporting(E_ERROR);
            socket_shutdown($socket, 2);
        } finally {
            error_reporting($i_warningLevel);
        }
        socket_close($socket);
    }

    function serverWrite($socket, $s_command, $o_arguments = null) {
        $s_msg = "";

        if ($o_arguments != null) {
            $s_encoded = json_encode($o_arguments);
            $s_msg = "{$s_command} {$s_encoded}\n";
        } else {
            $s_msg = "{$s_command}\n";
        }

        $s_msg = str_pad("".strlen($s_msg), 10) . $s_msg;
        socket_write($socket, $s_msg);
    }

    function serverRead($socket, $o_default = NULL) {
        socket_set_option($socket,SOL_SOCKET, SO_RCVTIMEO, array("sec"=>0, "usec"=>10000));

        $sbo_ret = false;
        $i_count = 10 * 100; // 10 seconds, with 100 timeouts per second
        while ($i_count > 0) {
            $sbo_ret = socket_read($socket, 10);
            if ($sbo_ret === "")
                $sbo_ret = "";
            if ($sbo_ret !== false)
            {
                $s_originalChars = $sbo_ret;
                socket_set_option($socket,SOL_SOCKET, SO_RCVTIMEO, array("sec"=>1, "usec"=>0));

                // read until we've read the incoming length
                $s_len = $sbo_ret;
                $s_part = "";
                while (strlen($s_len) < 10) {
                    $s_part = socket_read($socket, 10 - strlen($s_len));
                    if ($s_part !== false && strlen($s_part) > 0) {
                        $s_len .= $s_part;
                    } else {
                        // error_log("received part of a message " . $s_len . " (s_originalChars \"" . $s_originalChars . "\")");
                        $s_len = false;
                        $sbo_ret = false;
                        break;
                    }
                }
                if ($s_len === false)
                    break;
                $i_retlen = intval(substr($s_len, 0, 10));
                $sbo_ret = (strlen($s_len) <= 10) ? "" : substr($s_len, 10);

                // read the rest of the message
                $s_msg = "";
                $s_part = "";
                while ($s_msg !== false && strlen($s_msg) < $i_retlen)
                {
                    $s_part = socket_read($socket, $i_retlen - strlen($s_msg));
                    if ($s_part !== false)
                        $s_msg .= $s_part;
                    else
                        $s_msg = false;
                }
                if ($s_msg !== false)
                    $sbo_ret = "" . $sbo_ret . $s_msg;
                else
                    $sbo_ret = false;

                // done receiving, return event
                if (is_string($sbo_ret))
                {
                    $o_response = json_decode($sbo_ret);
                    return $o_response;
                }
                else if ($o_default !== NULL)
                {
                    error_log("Didn't get response from server, returning default value \"{$o_default}\"");
                    return $o_default;
                }
                else
                {
                    return $sbo_ret;
                }
            }
            $i_count--;
        }

        return $sbo_ret;
    }

    /**
     * o_commands should be a command type object (has properties "command" and "action")
     * s_roomCode should either be a 4-letter room code or null (will attempt to grab the room code from the o_globalPlayer's current game)
     * b_showError determines if an error gets logged/returned if either the room code can't be found or the event can't be pushed to the python server
     */
    function pushEvent($o_command, $s_roomCode = null, $b_showError = true, $b_waitForPropogation = false) {
        global $maindb;
        global $o_globalPlayer;
        
        // get user and game
        $o_game = null;
        if ($s_roomCode == null) {
            $o_game = $o_globalPlayer->getGame();
        } else {
            $o_game = game::loadByRoomCode($s_roomCode);
        }
        if ($o_game == null) {
            if ($b_showError)
            {
                error_log("can't push event " . print_r($o_command, true));
                return new command("showError", "Can't post event \"" . $o_command->command . "\". Player is not a part of a game.");
            }
            else
            {
                return new command("success", "");
            }
        }
        $s_roomCode = $o_game->getRoomCode();

        // connect to the server
        if (is_string($so_socket = self::serverConnect("propogate message"))) {
            return new command("showError", $so_socket);
        }

        // server connected, send the update event
        try {
            // get the list of current events
            $a_startingEvents = array();
            if ($b_waitForPropogation) {
                $a_startingEvents = self::getLatestEvents($s_roomCode, $so_socket);
            }

            // send the new event
            self::serverWrite($so_socket, "push", array(
                "event" => $o_command,
                "roomCode" => $s_roomCode
            ));
            $o_pushedEvent = self::serverRead($so_socket);
            $s_clientCount = self::serverRead($so_socket);

            // wait for this event to finish propogating
            $a_newEvents = array();
            if ($b_waitForPropogation) {
                while (TRUE) {
                    $a_newEvents = _ajax::getLatestEvents($o_newGame->getRoomCode(), $so_socket);
                    if (count($a_newEvents) > count($a_startingEvents))
                        break;
                    if ($a_newEvents[count($a_newEvents)-1] != $a_startingEvents[count($a_startingEvents)-1])
                        break;
                }
            }
        } finally {
            self::serverDisconnect($so_socket);
        }

        // error_log("sent event " . print_r($o_command, true));
        return new command("success", "");
    }

    function getUpdatePlayerEvent($o_player) {
        return new command(
            "updatePlayer",
            $o_player->toJsonObj()
        );
    }

    function pushPlayer($o_player, $s_roomCode = null, $b_showError = true) {
        return self::pushEvent(self::getUpdatePlayerEvent($o_player), $s_roomCode, $b_showError);
    }

    function getUpdateGameEvent($o_game) {
        return new command(
            "updateGame",
            $o_game->toJsonObj()
        );
    }

    function pushGame($o_game, $s_roomCode = null, $b_showError = true) {
        if ($s_roomCode === null)
            $s_roomCode = $o_game->getRoomCode();
        return self::pushEvent(self::getUpdateGameEvent($o_game), $s_roomCode, $b_showError);
    }

    function getUpdateStoryEvent($o_story) {
        return new command(
            "updateStory",
            $o_story->toJsonObj()
        );
    }

    function pushStory($o_story, $s_roomCode = null, $b_showError = true) {
        return self::pushEvent(self::getUpdateStoryEvent($o_story), $s_roomCode, $b_showError);
    }

    function getUpdateCardEvent($o_card) {
        return new command(
            "updateCard",
            $o_card->toJsonObj()
        );
    }

    function pushCard($o_card, $s_roomCode = null, $b_showError = true) {
        return self::pushEvent(self::getUpdateCardEvent($o_card), $s_roomCode, $b_showError);
    }

    function isPlayerInGame($o_player, $o_game) {
        $a_gameState = $o_player->getGameState();
        if ($a_gameState[0] < GAME_PSTATE::WAITING || $a_gameState[0] > GAME_PSTATE::DONE || $o_game === null)
        {
            return new command("showError", "Player not in a game");
        }
        return true;
    }

    function getLatestEvents($s_roomCode, $socket = null) {
        // send the update event
        if ($socket !== null) {
            self::serverWrite($socket, "getLatestEvents", array(
                "roomCode" => $s_roomCode
            ));
            $a_response = self::serverRead($socket, array());

            return $a_response;
        } else {
            // connect to the server
            if (is_string($so_socket = self::serverConnect("get latest events"))) {
                return new command("showError", $so_socket);
            }

            // server connected, get the latest events
            $a_ret = array();
            try {
                $a_ret = self::getLatestEvents($s_roomCode, $so_socket);
            } finally {
                self::serverDisconnect($so_socket);
            }

            return $a_ret;
        }
    }

    function rotateIphonePhotos($imageFile) {
        // Rotate iOS image
        // @author Richard Sumilang <me@richardsumilang.com>
        // https://www.richardsumilang.com/programming/php/graphics/working-with-apples-ios-image-orientation/

        // $imageFile = '/foo/bar.jpg';
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $imageFile);
        finfo_close($finfo);

        // Detect if jpeg or tiff
        if ( in_array($mimeType, ['image/jpeg', 'image/tiff']) ) {
            $exif = @exif_read_data($imageFile);
            if ( isset($exif['Orientation']) && !empty($exif['Orientation']) ) {

                // Decide orientation
                if ( $exif['Orientation'] == 3 ) {
                    $rotation = 180;
                } else if ( $exif['Orientation'] == 6 ) {
                    $rotation = 90;
                } else if ( $exif['Orientation'] == 8 ) {
                    $rotation = -90;
                } else {
                    $rotation = 0;
                }

                // Rotate the image
                if ( $rotation ) {
                    $imagick = new Imagick();
                    $imagick->readImage($imageFile);
                    $imagick->rotateImage(new ImagickPixel('none'), $rotation);

                    // Now that it's auto-rotated, make sure the EXIF data is correct in case the EXIF gets saved with the image!
                    // Thanks, orrd101! https://www.php.net/manual/en/imagick.getimageorientation.php#111448
                    $imagick->setImageOrientation(imagick::ORIENTATION_TOPLEFT); 

                    return $imagick;
                }
            }
        }

        return NULL;
    }

    function checkFileUpload($a_fileUpload, $b_isImage = TRUE) {
        // Check $_FILES['upfile']['error'] value.
        if (isset($a_fileUpload['error'])) {
            switch ($a_fileUpload['error']) {
                case UPLOAD_ERR_OK:
                    break;
                case UPLOAD_ERR_NO_FILE:
                    return 'No file sent.';
                case UPLOAD_ERR_INI_SIZE:
                case UPLOAD_ERR_FORM_SIZE:
                    return 'Exceeded filesize limit.';
                default:
                    return 'Unknown errors.';
            }
        }

        // Verify the image type
        if ($b_isImage)
        {
            $finfo = new finfo(FILEINFO_MIME_TYPE);
            $ext = array_search(
                $finfo->file($a_fileUpload['tmp_name']),
                array(
                    'jpg' => 'image/jpeg',
                    'jpeg' => 'image/jpeg',
                    'png' => 'image/png',
                    'gif' => 'image/gif',
                    'bmp' => 'image/bmp',
                    'tiff' => 'image/tiff'
                ),
                TRUE
            );
            if (FALSE === $ext) {
                return 'Invalid file format.';
            }
        }

        return TRUE;
    }

    /**
     * @param s_fileOrigName The name of the file that the user uploaded.
     * @param s_fileTmpName The name of the file that PHP uses.
     * @return either [TRUE, destination filename], or [FALSE, error string]
     */
    function uploadFile($s_fileOrigName, $s_fileTmpName, $b_cropSquare, $i_maxWidth = -1, $i_maxHeight = -1) {
        global $maindb;

        // verify the file extension and size
        $a_acceptableExtensions = array("jpg", "jpeg", "png", "gif", "bmp", "tiff");
        $s_file_extension = strtolower(pathinfo($s_fileOrigName, PATHINFO_EXTENSION));
        if (!in_array($s_file_extension, $a_acceptableExtensions)) {
            return json_encode(array(
                new command("print failure", "File type must be one of .".join(", .", $a_acceptableExtensions))));
        }
        $i_imagesize = filesize($s_fileTmpName);
        if ($i_imagesize > 12 * 1048576) { // ~12MB
            return json_encode(array(
                new command("print failure", "Image is too big.")));
        }

        // find a new name to use to save the file
        // move the file to that new name
        $a_imgvals = array(
            "alias"=>""
        );
        $i_maxcnt = 1000;
        $s_pathPrefix = dirname(__FILE__).'/../../../../telePictionaryUserImages/';
        $s_fileNewPath = "";
        while ($i_maxcnt > 0)
        {
            $s_fileNewPath = $s_pathPrefix.rand(1000,1000000000).'.'.$s_file_extension;
            if (!file_exists($s_fileNewPath)) {
                $a_imgvals['alias'] = basename($s_fileNewPath);
                $ab_result = db_query("SELECT `alias` FROM `{$maindb}`.`images` WHERE `alias`='[alias]'", $a_imgvals);
                if (is_array($ab_result) && sizeof($ab_result) === 0) {
                    break;
                }
            }
            $i_maxcnt--;
        };
        if ($i_maxcnt == 0) {
            return array(FALSE, "Error finding good alias. Try again.");
        }
        if (!move_uploaded_file($s_fileTmpName, $s_fileNewPath)) {
            error_log("Filesystem error for moving uploaded file from " . $s_fileTmpName . " to " . $s_fileNewPath);
            return array(FALSE, "Filesystem error");
        }

        // rotate if an iphone image
        $im_tmp = NULL;
        $b_needToSave = FALSE;
        try {
            $im_tmp = _ajax::rotateIphonePhotos($s_fileNewPath);
            if ($im_tmp !== NULL) {
                $b_needToSave = TRUE;
            } else {
                $im_tmp = new imagick();
                $im_tmp->readImage($s_fileNewPath);
            }
        } catch (Exception $e) {
            error_log($e);
            return array(FALSE, "Error parsing image");
        }

        // resize the image to be square (eg for user portraits)
        if ($b_cropSquare)
        {
            try {
                $i_width = $im_tmp->getImageWidth();
                $i_height = $im_tmp->getImageHeight();
                $i_newSize = min($i_width, $i_height);
                $i_newX = ($i_width - $i_newSize)/2;
                $i_newY = ($i_height - $i_newSize)/2;
                $im_tmp->cropImage($i_newSize, $i_newSize, $i_newX, $i_newY);
            } catch (Exception $e) {
                return array(FALSE, "Error cropping image");
            }
            $b_needToSave = TRUE;
        }

        // resize the image to a new maximum size
        if ($i_maxWidth > 0 || $i_maxHeight > 0)
        {
            $b_changed = FALSE;
            try {
                $i_width = $im_tmp->getImageWidth();
                $i_height = $im_tmp->getImageHeight();
                $i_newWidth = $i_width;
                $i_newHeight = $i_height;
                $f_ratio = 1.0;

                if ($i_maxWidth > 0 && $i_width * $f_ratio > $i_maxWidth)
                {
                    $f_ratio = min((double)$i_maxWidth / (double)$i_width, $f_ratio);
                }
                if ($i_maxHeight > 0 && $i_height * $f_ratio > $i_maxHeight)
                {
                    $f_ratio = min((double)$i_maxHeight / (double)$i_height, $f_ratio);
                }

                if ($f_ratio !== 1.0)
                {
                    $b_changed = TRUE;
                    $im_tmp->scaleImage($i_width * $f_ratio, $i_height * $f_ratio);
                }
            } catch (Exception $e) {
                return array(FALSE, "Error resizing image");
            }
            if ($b_changed) {
                $b_needToSave = TRUE;
            }
        }

        // save the image, if modified
        if ($b_needToSave) {
            try {
                $im_tmp->writeImage($s_fileNewPath);
            } catch (Exception $e) {
                return array(FALSE, "Error saving image after cropping");
            }
        }

        return array(TRUE, $s_fileNewPath);
    }
}

?>
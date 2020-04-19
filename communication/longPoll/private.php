<?php

require_once(dirname(__FILE__) . "/../../resources/common_functions.php");
require_once(dirname(__FILE__) . "/../../resources/globals.php");
require_once(dirname(__FILE__) . "/../../objects/player.php");
require_once(dirname(__FILE__) . "/../../objects/command.php");
require_once(dirname(__FILE__) . "/../../objects/game.php");

class _ajax {
    /**
     * o_commands should be a command type object (has properties "command" and "action")
     * s_roomCode should either be a 4-letter room code or null (will attempt to grab the room code from the o_globalPlayer's current game)
     * b_showError determines if an error gets logged/returned if either the room code can't be found or the event can't be pushed to the python server
     */
    function pushEvent($o_command, $s_roomCode = null, $b_showError = true) {
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

        // send the update event
        $socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
        if(is_resource($socket)) {
            if (socket_connect($socket, "127.0.0.1", 23456)) {
                $s_encoded = json_encode(array(
                    "event" => $o_command,
                    "roomCode" => $s_roomCode
                ));
                $s_encoded = "push " . $s_encoded . "\n";
                $s_encoded = str_pad("".strlen($s_encoded), 10) . $s_encoded;
                socket_write($socket, $s_encoded);
            } else {
                $s_msg = "Failed to connect to 127.0.0.1:23456 to propogate message";
                error_log($s_msg);
                return new command("showError", $s_msg);
            }
        } else {
            $s_msg = "Failed to create socket to propogate message";
            error_log($s_msg);
            return new command("showError", $s_msg);
        }

        // error_log("sent event " . print_r($o_command, true));
        return new command("success", "");
    }

    function pushPlayer($o_player, $s_roomCode = null, $b_showError = true)
    {
        return self::pushEvent(new command(
            "addPlayer",
            $o_player->toJsonObj()
        ), $s_roomCode, $b_showError);
    }

    function pushGame($o_game, $s_roomCode = null, $b_showError = true)
    {
        if ($s_roomCode === null)
            $s_roomCode = $o_game->getRoomCode();
        return self::pushEvent(new command(
            "updateGame",
            $o_game->toJsonObj()
        ), $s_roomCode, $b_showError);
    }

    function pushStory($o_story, $s_roomCode = null, $b_showError = true)
    {
        return self::pushEvent(new command(
            "updateStory",
            $o_story->toJsonObj()
        ), $s_roomCode, $b_showError);
    }

    function pushCard($o_card, $s_roomCode = null, $b_showError = true)
    {
        return self::pushEvent(new command(
            "updateCard",
            $o_card->toJsonObj()
        ), $s_roomCode, $b_showError);
    }

    function isPlayerInGame($o_player, $o_game)
    {
        $a_gameState = $o_player->getGameState();
        if ($a_gameState[0] < 2 || $a_gameState[0] > 4 || $o_game === null)
        {
            return new command("showError", "Player not in a game");
        }
        return true;
    }

    function getLatestEvents($s_roomCode)
    {
        // send the update event
        $socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
        if(is_resource($socket)) {
            if (socket_connect($socket, "127.0.0.1", 23456)) {
                $s_encoded = json_encode(array(
                    "roomCode" => $s_roomCode
                ));
                $s_encoded = "getLatestEvents " . $s_encoded . "\n";
                $s_encoded = str_pad("".strlen($s_encoded), 10) . $s_encoded;
                socket_write($socket, $s_encoded);
                
                $sbo_ret = _ajax::getResponse($socket);
                if (is_string($sbo_ret))
                {
                    $sbo_ret = json_decode($sbo_ret);
                    return $sbo_ret;
                }
                else
                {
                    return array();
                }
            } else {
                $s_msg = "Failed to connect to 127.0.0.1:23456 to propogate message";
                error_log($s_msg);
                return $s_msg;
            }
        } else {
            $s_msg = "Failed to create socket to propogate message";
            error_log($s_msg);
                return $s_msg;
        }
    }

    function getResponse($socket)
    {
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
                break;
            }
            $i_count--;
        }

        return $sbo_ret;
    }

    /**
     * @param s_fileOrigName The name of the file that the user uploaded.
     * @param s_fileTmpName The name of the file that PHP uses.
     * @return either [TRUE, destination filename], or [FALSE, error string]
     */
    function uploadFile($s_fileOrigName, $s_fileTmpName, $b_cropSquare, $i_maxWidth = -1, $i_maxHeight = -1)
    {
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
            return array(FALSE, "Filesystem error");
        }

        // verify is a good image
        $im_tmp = new imagick();
        try {
            $im_tmp->readImage($s_fileNewPath);
        } catch (Exception $e) {
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
            try {
                $im_tmp->writeImage($s_fileNewPath);
            } catch (Exception $e) {
                return array(FALSE, "Error saving image after cropping");
            }
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
                if ($i_maxWidth > 0 && $i_newWidth < $i_maxWidth)
                {
                    $f_ratio = (double)$i_maxWidth / (double)$i_newWidth;
                    $i_newWidth *= $f_ratio;
                    $i_newHeight *= $f_ratio;
                    $b_changed = TRUE;
                }
                if ($i_maxHeight > 0 && $i_newHeight < $i_maxHeight)
                {
                    $f_ratio = (double)$i_maxHeight / (double)$i_newHeight;
                    $i_newWidth *= $f_ratio;
                    $i_newHeight *= $f_ratio;
                    $b_changed = TRUE;
                }
                $im_tmp->scaleImage($i_newWidth, $i_newHeight);
            } catch (Exception $e) {
                return array(FALSE, "Error resizing image");
            }
            try {
                if ($b_changed) {
                    $im_tmp->writeImage($s_fileNewPath);
                }
            } catch (Exception $e) {
                return array(FALSE, "Error saving image after resizing");
            }
        }

        return array(TRUE, $s_fileNewPath);
    }
}

?>
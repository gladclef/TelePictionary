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
                        error_log("received part of a message " . $s_len . " (s_originalChars \"" . $s_originalChars . "\")");
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
}

?>
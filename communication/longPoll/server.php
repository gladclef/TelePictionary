<?php

require_once(dirname(__FILE__) . "/../../resources/common_functions.php");
require_once(dirname(__FILE__) . "/../../resources/globals.php");
require_once(dirname(__FILE__) . "/../../objects/player.php");
require_once(dirname(__FILE__) . "/../../objects/command.php");
require_once(dirname(__FILE__) . "/../../objects/game.php");

class _ajax {
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
                socket_write($socket, "push ${s_encoded}\n");
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
        return self::pushEvent(new command(
            "updateGame",
            $o_game->toJsonObj()
        ), $s_roomCode, $b_showError);
    }
}

// only functions within this class can be called by ajax
class ajax {

    function setUsername() {
        global $maindb;
        global $o_globalPlayer;

        $s_username = get_post_var("username");
        
        // get user
        $o_globalPlayer->s_name = $s_username;
        $o_globalPlayer->save();

        // push this event
        _ajax::pushPlayer($o_globalPlayer, null, false);

        return new command("success", "");
    }

    function setGameName() {
        global $maindb;
        global $o_globalPlayer;

        $s_gameName = get_post_var("gameName");
        
        // get the game
        $o_game = $o_globalPlayer->getGame();
        $o_game->s_name = $s_gameName;
        $o_game->save();

        // push this event
        return _ajax::pushGame($o_game);
    }

    function createGame() {
        global $maindb;
        global $o_globalPlayer;
        $a_commands = array();

        // check to make sure that the player isn't already in a game
        $a_gameState = $o_globalPlayer->getGameState();
        if ($a_gameState[0] >= 2 && $a_gameState[0] <= 4)
        {
            return new command("showError", "Can't create a game while in a game.");
        }

        // create a new game
        $o_game = new game($o_globalPlayer->getName() . "'s Game", $o_globalPlayer->getId());
        $o_oldGame = $o_globalPlayer->getGame();
        $b_old = FALSE;
        if ($o_oldGame != null && $o_oldGame->getPlayer1Id() == $o_globalPlayer->getId())
        {
            $o_game->copyOldGame($o_oldGame);
            $b_old = TRUE;
        }

        // join the game
        if ($b_old)
        {
            // update values locally
            $a_players = $o_oldGame->getPlayers();
            for ($i = 0; $i < count($a_players); $i++)
            {
                $o_player = $a_players[$i];
                $o_player->joinGame($o_game);
                $o_player->save();
            }
            $o_game->player1Id = $o_globalPlayer->getId();
            $o_game->save();

            // push events to all the listening players
            $o_cmd = pushEvent(
                new command(
                    "joinGame",
                    $o_game->toJsonObj()
                ),
                $o_oldGame->getRoomCode()
            );
            if ($o_cmd->command != "success") return $o_cmd;
            $o_cmd = pushEvent(
                new command(
                    "setPlayer1",
                    $o_globalPlayer->getId()
                ),
                $o_game->getRoomCode()
            );
            if ($o_cmd->command != "success") return $o_cmd;

            // Don't need to save the player state.
            // That will happen when the local clients tell us that they have joined
            // the game room with joinGame().
        }
        else
        {
            // save current state
            $o_globalPlayer->save();
            $o_game->save();
        }

        return new command("joinGame", $o_game->toJsonObj());
    }

    function joinGame() {
        global $maindb;
        global $o_globalPlayer;
        $a_commands = array();

        $s_roomCode = get_post_var("roomCode");

        // check to make sure that the player isn't already in a game
        $a_gameState = $o_globalPlayer->getGameState();
        if ($a_gameState[0] >= 2 && $a_gameState[0] <= 4)
        {
            return new command("showError", "Can't join a game while in a game.");
        }

        // find the game
        $o_game = game::loadByRoomCode($s_roomCode);
        if ($o_game === null)
        {
            return new command("showError", "Can't find a game with room code \"{$s_roomCode}\".");
        }

        // join the game
        $o_globalPlayer->joinGame($o_game);
        $o_globalPlayer->save();
        $o_game->save();

        $a_commands = array(
            new command("clearPlayers", ""),
            new command("updateGame", $o_game->toJsonObj()),
            new command("showContent", "game")
        );
        foreach ($o_game->getPlayers() as $i => $o_player) {
            array_push(   $a_commands, new command( "addPlayer", $o_player->toJsonObj() )   );
        }
        array_push(   $a_commands, new command( "setLocalPlayer", $o_globalPlayer->getId() )   );
        array_push(   $a_commands, new command( "setPlayer1", $o_game->getPlayer1Id() )   );
        return new command("composite", $a_commands);
    }

    function pushEvent() {
        $s_event = get_post_var("event");
        $o_event = json_decode($s_event);

        return _ajax::pushEvent($o_event);
    }

    function pull() {
        global $maindb;
        global $o_globalPlayer;

        $i_clientId = intval(get_post_var("clienId"));
        $s_latestEvents = get_post_var("latestEvents");
        $o_latestEvents = json_decode($s_latestEvents);
        
        // get user and game
        if ($o_globalPlayer->getGameState()[0] < 2)
        {
            return new command("noPoll", 5);
        }
        $o_game = $o_globalPlayer->getGame();

        // listen for the next camera value update
        $socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
        if(is_resource($socket)) {
            if (socket_connect($socket, "127.0.0.1", 23456)) {
                $s_encoded = json_encode(array(
                    "latestEvents" => $o_latestEvents,
                    "roomCode" => $o_game->getRoomCode()
                ));
                socket_write($socket, "subscribe ${s_encoded}\n");
                socket_set_option($socket,SOL_SOCKET, SO_RCVTIMEO, array("sec"=>0, "usec"=>100000));

                $sbo_ret = false;
                $i_count = 10 * 10; // 10 seconds, with 10 timeouts per second
                while ($i_count > 0) {
                    $sbo_ret = socket_read($socket, 4096);
                    if ($sbo_ret !== false)
                    {
                        // read for 10 more milliseconds to make sure we've received the full message
                        socket_set_option($socket,SOL_SOCKET, SO_RCVTIMEO, array("sec"=>0, "usec"=>10000));
                        $sbo_ret2 = socket_read($socket, 4096);
                        if ($sbo_ret2 !== false)
                            $sbo_ret .= $sbo_ret2;
                        break;
                    }
                    $i_count--;
                }
                socket_write($socket, "disconnect\n");

                if (is_bool($sbo_ret))
                {
                    $sbo_ret = new command("success", "no new events");
                }
                return $sbo_ret;
            } else {
                error_log("Failed to connect to 127.0.0.1:23456 to propogate message");
            }
        } else {
            error_log("Failed to create socket to propogate message");
        }
    }
}

$s_command = get_post_var("command");

$o_ret = new command("showError", "missing \"command\" post var");
if ($s_command != '') {
    $o_ajax = new ajax();
    $o_ret = new command("showError", "bad \"command\" post var \"{$s_command}\"");
    if (method_exists($o_ajax, $s_command)) {
        player::getGlobalPlayer();
        $o_ret = $o_ajax->$s_command();
        if (is_string($o_ret))
        {
            $o_ret = new command("showError", $o_ret);
        }
    }
}

if ($o_ret !== false)
{
    echo json_encode($o_ret);
}

?>
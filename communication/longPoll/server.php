<?php

require_once(dirname(__FILE__) . "/../../resources/common_functions.php");
require_once(dirname(__FILE__) . "/../../resources/globals.php");
require_once(dirname(__FILE__) . "/../../objects/player.php");
require_once(dirname(__FILE__) . "/../../objects/command.php");
require_once(dirname(__FILE__) . "/../../objects/game.php");

// only functions within this class can be called by ajax
class ajax {

    function setUsername() {
        global $maindb;
        global $o_globalPlayer;

        $s_username = intval(get_post_var("username"));
        
        // get user
        player::getGlobalPlayer();
        $o_globalPlayer->s_name = $s_username;

        return json_encode(new command("success", ""));
    }

    function createGame() {
        global $maindb;
        global $o_globalPlayer;

        // get user
        player::getGlobalPlayer();

        // check to make sure that the player isn't already in a game
        $a_gameState = $o_globalPlayer->getGameState();
        if ($a_gameState[0] >= 2 && $a_gameState[0] <= 4)
        {
            return json_encode(new command("showError", "Can't create a game while in a game."));
        }

        // create a new game
        $o_game = $o_globalPlayer->getGame();
        $s_gameName = $o_globalPlayer->getName() + "'s Game";
        if ($o_game != null && $o_game->getPlayer1Id() == $o_globalPlayer->getId())
        {
            $s_gameName = $o_game->getName();
        }
        $o_game = new game($s_gameName, $o_globalPlayer->getId());
        $o_game->save();

        // join the game
        $o_globalPlayer->joinGame($o_game);
    }

    function pushEvent() {
        global $maindb;
        global $o_globalPlayer;

        $i_clientId = intval(get_post_var("clienId"));
        $s_latestIndexes = get_post_var("latestIndexes");
        $s_key = get_post_var("key");
        $s_event = get_post_var("event");
        $o_event = json_decode($s_event);
        
        // get user
        player::getGlobalPlayer();

        // listen for the next camera value update
        $socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
        if(is_resource($socket)) {
            if (socket_connect($socket, "127.0.0.1", 23456)) {
                $s_encoded = json_encode([ "clientId"=>$i_clientId, "latestIndexes"=>$s_latestIndexes, "key"=>$s_key, "event"=>$o_event ]);
                socket_write($socket, "push ${s_encoded}\n");
            } else {
                error_log("Failed to connect to 127.0.0.1:23456 to propogate message");
            }
        } else {
            error_log("Failed to create socket to propogate message");
        }
    }

    function pull() {
        global $maindb;
        global $o_globalPlayer;

        $i_clientId = intval(get_post_var("clienId"));
        $s_latestIndexes = get_post_var("latestIndexes");
        
        // get user
        player::getGlobalPlayer();
        if ($o_globalPlayer->getGameState()[0] < 2)
        {
            sleep(1);
            return "";
        }

        // listen for the next camera value update
        $socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
        if(is_resource($socket)) {
            if (socket_connect($socket, "127.0.0.1", 23456)) {
                $s_encoded = json_encode([ "clientId"=>$i_clientId, "latestIndexes"=>$s_latestIndexes ]);
                socket_write($socket, "subscribe ${s_encoded}\n");
                socket_set_option($socket,SOL_SOCKET, SO_RCVTIMEO, array("sec"=>2, "usec"=>0));
                while (true) {
                    $s_ret = socket_read($socket, 2048);
                    $i_newlinePos = strpos($s_ret, "\n");
                    if ($i_newlinePos !== FALSE)
                        $s_ret = substr($s_ret, 0, $i_newlinePos);

                    // only return values not created by this same client 
                    // $a_ret = json_decode($s_ret, TRUE);
                    // if (intval($a_ret['clientId']) != $i_clientId) {
                    //     socket_write($socket, "disconnect\n");
                    //     return $s_ret;
                    // }

                    // return all values, including those created by this same client
                    socket_write($socket, "disconnect\n");
                    return $s_ret;
                }
            } else {
                error_log("Failed to connect to 127.0.0.1:23456 to propogate message");
            }
        } else {
            error_log("Failed to create socket to propogate message");
        }
    }
}

$s_command = get_post_var("command");

if ($s_command != '') {
    $o_ajax = new ajax();
    if (method_exists($o_ajax, $s_command)) {
        echo $o_ajax->$s_command();
    } else {
        echo "bad \"command\" post var";
    }
} else {
    echo "missing \"command\" post var";
}

?>
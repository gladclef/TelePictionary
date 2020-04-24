<?php

require_once(dirname(__FILE__) . "/../../resources/common_functions.php");
require_once(dirname(__FILE__) . "/../../resources/globals.php");
require_once(dirname(__FILE__) . "/../../objects/player.php");
require_once(dirname(__FILE__) . "/../../objects/command.php");
require_once(dirname(__FILE__) . "/../../objects/game.php");
require_once(dirname(__FILE__) . "/../../objects/story.php");
require_once(dirname(__FILE__) . "/../../objects/card.php");
require_once(dirname(__FILE__) . "/../../objects/image.php");
require_once(dirname(__FILE__) . "/private.php");

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

        // push this event to all clients in the game
        return _ajax::pushGame($o_game);
    }

    function createGame() {
        global $maindb;
        global $o_globalPlayer;
        $a_commands = array();

        // check to make sure that the player isn't already in a game
        $o_game = $o_globalPlayer->getGame();
        if (($bo_playerInGame = _ajax::isPlayerInGame($o_globalPlayer, $o_game)) === true)
            return new command("showError", "Can't create a game while in a game.");

        // create a new game
        $o_game = new game($o_globalPlayer->getName() . "'s Game", $o_globalPlayer->getId());
        $o_oldGame = $o_globalPlayer->getGame();
        $b_old = FALSE;
        if ($o_oldGame != null && $o_oldGame->getPlayer1Id() == $o_globalPlayer->getId())
        {
            $o_game->copyOldGame($o_oldGame);
            $b_old = TRUE;
        }

        // have all the players from the old game join the new game
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
                    "composite",
                    array(new command(
                        "clearPlayers",
                        ""
                    ), new command(
                        "joinGame",
                        $o_game->toJsonObj()
                    ))
                ),
                $o_oldGame->getRoomCode()
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

        return new command("success", "");
    }

    function joinGame() {
        global $maindb;
        global $o_globalPlayer;
        $a_commands = array();

        $s_roomCode = get_post_var("roomCode");

        // check to make sure that the player isn't already in a game
        $o_game = $o_globalPlayer->getGame();
        if (($bo_playerInGame = _ajax::isPlayerInGame($o_globalPlayer, $o_game)) === true)
            return new command("showError", "Can't join a game while in a game.");

        // find the game
        $o_game = game::loadByRoomCode($s_roomCode);
        if ($o_game === null) {
            return new command("showError", "Can't find a game with room code \"{$s_roomCode}\".");
        }

        // check to make sure the game hasn't already started
        if ($o_game->getGameState()[0] >= 2 && $o_game->getCurrentTurn() > 0) {
            return new command("showError", "Can't join a game that's already in progress.");
        }

        // join the game
        $o_globalPlayer->joinGame($o_game);
        $o_globalPlayer->save();
        $o_game->save();

        // push this event to other clients in the game
        $a_commands = array(
            new command("addPlayer", $o_globalPlayer->toJsonObj()),
            new command("updateGame", $o_game->toJsonObj())
        );
        _ajax::pushEvent(new command("composite", $a_commands));

        // respond to this client
        $a_commands = array(
            new command("setLatestEvents", _ajax::getLatestEvents($o_game->getRoomCode())),
            new command("clearPlayers", ""),
            new command("updateGame", $o_game->toJsonObj()),
            new command("showContent", "game"),
        );
        foreach ($o_game->getPlayers() as $i => $o_player) {
            array_push(   $a_commands, new command( "addPlayer", $o_player->toJsonObj() )   );
        }
        array_push(   $a_commands, new command( "setLocalPlayer", $o_globalPlayer->getId() )   );
        array_push(   $a_commands, new command( "setPlayer1", $o_game->getPlayer1Id() )   );
        return new command("composite", $a_commands);
    }

    function leaveGame() {
        global $maindb;
        global $o_globalPlayer;
        $a_commands = array();

        // get the global user id and the kicked user id
        $i_globalId = $o_globalPlayer->getId();
        $i_id = intval(get_post_var("otherPlayerId", "" . $i_globalId));
        $o_player = player::loadById($i_id);
        if ($o_player === null || $i_id == 0)
        {
            return new command("showError", "Unknown player");
        }

        // check to make sure that the player is in a game
        $o_game = $o_player->getGame();
        if (($bo_playerInGame = _ajax::isPlayerInGame($o_player, $o_game)) !== true)
            return $bo_playerInGame;

        // push this event to all clients in the game
        $o_removeCmd = new command("removePlayer", $o_player->toJsonObj());
        _ajax::pushEvent($o_removeCmd, $o_game->getRoomCode());

        // leave the game
        $o_player->leaveGame();
        $o_player->save();
        $o_game->save();

        // push the changes to the game to other clients
        _ajax::pushGame($o_game);

        // respond to this client
        if ($i_id == $i_globalId) {
            return $o_removeCmd;
        } else {
            return new command("success", "");
        }
    }

    function promotePlayer() {
        global $maindb;
        global $o_globalPlayer;
        $a_commands = array();

        // get the promoted user
        $i_id = intval(get_post_var("otherPlayerId", "0"));
        $o_player = player::loadById($i_id);
        if ($o_player === null || $i_id === 0)
        {
            return new command("showError", "Unknown player");
        }

        // check to make sure that the player is in a game
        $o_game = $o_player->getGame();
        if (($bo_playerInGame = _ajax::isPlayerInGame($o_player, $o_game)) !== true)
            return $bo_playerInGame;

        // update the game
        $o_game->i_player1Id = $i_id;
        $o_game->save();

        // push the changes to the game to other clients
        _ajax::pushGame($o_game);

        // respond to this client
        return new command("success", "");
    }

    function setSharingTurn($i_turn = -1) {
        global $maindb;
        global $o_globalPlayer;

        // get the post variables
        if ($i_turn < 0) {
            $i_turn = intval(get_post_var("turn"));
        }

        // check to make sure that the player is in a game
        $o_game = $o_globalPlayer->getGame();
        if (($bo_playerInGame = _ajax::isPlayerInGame($o_globalPlayer, $o_game)) !== TRUE)
            return $bo_playerInGame;

        // get a good value for the turn
        $i_turn %= $o_game->getPlayerCount();

        // change the content
        if ($i_turn == 0) {
            _ajax::pushEvent(new command("showContent", "reveal"));
        }

        // update the game
        if ($o_game->setCurrentTurn($i_turn + $o_game->getPlayerCount()))
        {
            $o_game->save();

            // push the changes to the game to other clients
            _ajax::pushGame($o_game);
        }

        // update the players
        $a_commands = array();
        foreach ($o_game->getPlayers() as $i => $o_player) {
            $o_player->b_ready = FALSE;
            $o_player->save();
            array_push(  $a_commands, $o_player->toJsonObj()  );
        }
        _ajax::pushEvent(new command("composite", $a_commands));

        // push the story
        $a_playerIds = $o_game->getPlayerIds();
        $o_player = player::loadById($a_playerIds[$i_turn]);
        $o_story = $o_player->getStory();
        $a_cards = $o_story->getCards();
        $a_commands = array(new command("updateStory", $o_story->toJsonObj()));
        for ($i = 0; $i < count($a_cards); $i++) {
            $o_card = $a_cards[$i];
            array_push(  $a_commands, new command("updateCard", $o_card->toJsonObj())  );
        }
        _ajax::pushEvent(new command("composite", $a_commands));

        // respond to this client
        return new command("success", "");
    }

    function startSharing() {
        return ajax::setSharingTurn(0);
    }

    function setGameTurn() {
        global $maindb;
        global $o_globalPlayer;
        $a_commands = array();

        // check to make sure that the player is in a game
        $o_game = $o_globalPlayer->getGame();
        if (($bo_playerInGame = _ajax::isPlayerInGame($o_globalPlayer, $o_game)) !== TRUE)
            return $bo_playerInGame;

        // get the new turn
        $i_newTurn = intval(get_post_var("turn", "-2"));
        if ($i_newTurn === -2)
            return new command("showError", "Bad turn number");

        // update the game
        if ($o_game->setCurrentTurn($i_newTurn))
        {
            $o_game->save();

            // push the changes to the game to other clients
            _ajax::pushGame($o_game);
        }

        // update the players
        foreach ($o_game->getPlayers() as $i => $o_player) {
            $o_player->b_ready = FALSE;
            $o_player->save();
            _ajax::pushPlayer($o_player, $o_game->getRoomCode(), FALSE);
        }

        // respond to this client
        return new command("success", "");
    }

    function getCurrentCard() {
        global $o_globalPlayer;
        $a_commands = array();

        // check to make sure that the player is in a game
        $o_game = $o_globalPlayer->getGame();
        if (($bo_playerInGame = _ajax::isPlayerInGame($o_globalPlayer, $o_game)) !== TRUE)
            return $bo_playerInGame;

        // get the current story and card
        $o_card = $o_globalPlayer->getCurrentCard();
        $o_story = $o_card->getStory();
        if ($o_card === null)
            return new command("showError", "Error while retrieving current card!");

        // return the current card + story
        return new command("composite", array(
            new command("setCurrentCard", $o_card->toJsonObj()),
            new command("updateStory", $o_story->toJsonObj())
        ));
    }

    function setPlayerImage() {
        global $o_globalPlayer;

        $s_fileOrigName = $_FILES['file']['name'];
        $s_fileTmpName = $_FILES['file']['tmp_name'];
        $a_uploadSuccess = _ajax::uploadFile($s_fileOrigName, $s_fileTmpName, TRUE, 100, 100);

        // check that the file uploaded successfully
        if ($a_uploadSuccess[0] === FALSE) {
            return new command("showError", $a_uploadSuccess[1]);
        }
        $s_fileNewPath = $a_uploadSuccess[1];
        $s_alias = basename($s_fileNewPath);
        $s_extension = strtolower(pathinfo($s_fileNewPath, PATHINFO_EXTENSION));

        // update the image
        $o_globalPlayer->updateImage(intval($s_alias), $s_extension);

        // alert everybody in the game
        _ajax::pushPlayer($o_globalPlayer);

        // return success
        return new command("success", "");
    }

    function setCardImage() {
        global $o_globalPlayer;

        $sb_uploadSuccess = _ajax::checkFileUpload($_FILES['file']);
        if ($sb_uploadSuccess !== TRUE)
        {
            return new command("showError", $sb_uploadSuccess);
        }

        $s_fileOrigName = $_FILES['file']['name'];
        $s_fileTmpName = $_FILES['file']['tmp_name'];
        $a_uploadSuccess = _ajax::uploadFile($s_fileOrigName, $s_fileTmpName, FALSE, 500, 600);

        // check that the file uploaded successfully
        if ($a_uploadSuccess[0] === FALSE) {
            return new command("showError", $a_uploadSuccess[1]);
        }
        $s_fileNewPath = $a_uploadSuccess[1];
        $s_alias = basename($s_fileNewPath);
        $s_extension = strtolower(pathinfo($s_fileNewPath, PATHINFO_EXTENSION));

        // get the current card
        $o_card = $o_globalPlayer->getCurrentCard();
        $o_story = $o_card->getStory();

        // update the image and the player
        $o_card->updateImage(intval($s_alias), $s_extension);
        $o_globalPlayer->b_ready = TRUE;
        $o_globalPlayer->save();

        // alert everybody in the game
        _ajax::pushStory($o_story);
        _ajax::pushCard($o_card);
        _ajax::pushPlayer($o_globalPlayer);

        // return success
        return new command("success", "");
    }

    function setCardText() {
        global $o_globalPlayer;

        $s_text = get_post_var("text");

        // check to make sure that the player is in a game
        $o_game = $o_globalPlayer->getGame();
        if (($bo_playerInGame = _ajax::isPlayerInGame($o_globalPlayer, $o_game)) !== TRUE)
            return $bo_playerInGame;

        // get the current card
        $o_card = $o_globalPlayer->getCurrentCard();
        $o_story = $o_card->getStory();

        // update the text and the player
        $o_card->updateText($s_text);
        $o_globalPlayer->b_ready = TRUE;
        $o_globalPlayer->save();

        // alert everybody in the game
        _ajax::pushStory($o_story);
        _ajax::pushCard($o_card);
        _ajax::pushPlayer($o_globalPlayer);

        // return success
        return new command("success", "");
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
        $a_latestEvents = json_decode($s_latestEvents);

        // check to make sure that the player is in a game
        $o_game = $o_globalPlayer->getGame();
        if (($bo_playerInGame = _ajax::isPlayerInGame($o_globalPlayer, $o_game)) !== true)
            return new command("noPoll", 5);

        // listen for the next camera value update
        $socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
        if(is_resource($socket)) {
            if (socket_connect($socket, "127.0.0.1", 23456)) {
                $s_encoded = json_encode(array(
                    "latestEvents" => $a_latestEvents,
                    "roomCode" => $o_game->getRoomCode()
                ));
                $s_encoded = "subscribe " . $s_encoded . "\n";
                $s_encoded = str_pad("".strlen($s_encoded), 10) . $s_encoded;
                socket_write($socket, $s_encoded);
                $sbo_ret = _ajax::getResponse($socket);
                $s_encoded = "disconnect\n";
                $s_encoded = str_pad("".strlen($s_encoded), 10) . $s_encoded;
                socket_write($socket, $s_encoded);

                if (is_bool($sbo_ret))
                {
                    $sbo_ret = new command("success", "no new events");
                }
                else if (is_string($sbo_ret))
                {
                    $o_command = json_decode($sbo_ret);
                    if ($o_command !== null) // check if the decode was successful
                    {
                        $sbo_ret = $o_command;
                    }
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

        global $o_globalPlayer;
        global $b_badPlayerPostId;
        player::getGlobalPlayer();
        if ($b_badPlayerPostId) {
            // 'playerId' post variable is set and is bad
            $o_ret = new command("composite", array(
                new command("clearPlayers", ""),
                new command("addPlayer", $o_globalPlayer->toJsonObj()),
                new command("setPlayer1", $o_globalPlayer->getId())
            ));
        } else {
            $o_ret = $o_ajax->$s_command();
            if (is_string($o_ret))
            {
                $o_ret = new command("showError", $o_ret);
            }
        }
    }
}

if ($o_ret !== false)
{
    echo json_encode($o_ret);
}

?>
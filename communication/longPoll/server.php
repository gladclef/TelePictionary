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

    function composite() {
        $o_ajax = $this;
        $a_ret = array();

        $sa_subcommands = get_post_var("action", "");
        if (!is_array($sa_subcommands))
            return new command("showError", "action must be set");

        for ($i = 0; $i < count($sa_subcommands); $i++) {
            $a_subcommand = $sa_subcommands[$i];
            $s_command = $a_subcommand['command'];

            if (method_exists($o_ajax, $s_command)) {

                // execute the method
                foreach ($a_subcommand as $postVarName => $postVarVal) {
                    if ($postVarName != 'command') {
                        $_POST[$postVarName] = $postVarVal;
                    }
                }
                $o_ret = $o_ajax->$s_command();

                // return the result
                if (is_string($o_ret)) {
                    array_push($a_ret, new command("showError", $o_ret));
                } else {
                    array_push($a_ret, $o_ret);
                }
            } else {
                array_push($a_ret, new command("showError", "method {$s_command} does not exist"));
            }
        }

        $o_ret = new command("composite", $a_ret);
    }

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

        // check to make sure that the player isn't already in a game
        $o_game = $o_globalPlayer->getGame();
        $bo_playerInGame = _ajax::isPlayerInGame($o_globalPlayer, $o_game);
        if ($bo_playerInGame === true) {
            if (!$o_game->isFinished()) {
                return new command("showError", "Can't create a game while in a game.");
            }
        }

        // create a new game
        $o_oldGame = $o_game;
        $b_old = FALSE;
        if ($bo_playerInGame === TRUE && $o_oldGame != null && $o_oldGame->getPlayer1Id() == $o_globalPlayer->getId()) {
            $o_game = new game($o_globalPlayer->getName() . "'s Game", $o_globalPlayer->getId(), $o_oldGame);
            $b_old = TRUE;
        } else {
            $o_game = new game($o_globalPlayer->getName() . "'s Game", $o_globalPlayer->getId());
        }
        $o_game->save();

        // have all the players from the old game join the new game
        $a_commands = array();
        array_push( $a_commands, new command("clearPlayers", "dontClearLocal") );
        array_push( $a_commands, new command("joinGame", $o_game->toJsonObj()) );
        $o_command = new command("composite", $a_commands);
        if ($b_old)
        {
            // push events to all the listening players
            return _ajax::pushEvent($o_command, $o_oldGame->getRoomCode());

            // Don't need to save the player state.
            // That will happen when the local clients tell us that they have joined
            // the game room with joinGame().
        }
        else
        {
            // have the player join the game
            return $o_command;
        }

        error_log("programmer error, should not be able to reach this code");
        return new command("success", "");
    }

    function joinGame() {
        global $maindb;
        global $o_globalPlayer;
        $a_commands = array();

        $s_newRoomCode = get_post_var("roomCode");

        // check to make sure that the player isn't already in a game
        $o_oldGame = $o_globalPlayer->getGame();
        $bo_playerInGame = _ajax::isPlayerInGame($o_globalPlayer, $o_oldGame);
        $b_isSameGame = FALSE;
        if ($bo_playerInGame === true) {
            if ($o_oldGame->getRoomCode() == $s_newRoomCode) {
                // allowed to join the same game that the player is already in
                $b_isSameGame = TRUE;
            }
            else if (!$o_oldGame->isFinished()) {
                return new command("showError", "Can't join a game while in a game.");
            }
        }

        // find the game
        $o_newGame = game::loadByRoomCode($s_newRoomCode);
        if ($o_newGame === null) {
            return new command("showError", "Can't find a game with room code \"{$s_newRoomCode}\".");
        }

        // check to make sure the game hasn't already started
        if (!$b_isSameGame) {
            if ($o_newGame->getGameState()[0] >= GAME_GSTATE::IN_PROGRESS && $o_newGame->getCurrentTurn() > 0) {
                return new command("showError", "Can't join a game that's already in progress.");
            }
        }

        // Lock and reload the game.
        // We lock the game so that only one client can join the game at a time and we don't end up with a weird game state
        // due to latency between loading the game from MySQL and updating the game row with the new player id in MySQL.
        if (!$o_newGame->lock()) {
            return new command("showError", "Failed to join game. Ran out of time while waiting for access to the game object.");
        }
        try
        {
            // join the game
            if (!$b_isSameGame) {
                $o_globalPlayer->joinGame($o_newGame);
            }
            $o_globalPlayer->save();
            $o_newGame->save();

            // Push this event to other clients already in the game.
            // Push players, then the game, then the players again.
            // We do this because correctly setting up both requires knowledge of the other.
            $o_command = new command("composite", array(
                _ajax::getUpdatePlayerEvent($o_globalPlayer),
                _ajax::getUpdateGameEvent($o_newGame),
                _ajax::getUpdatePlayerEvent($o_globalPlayer) // updating the 
            ));
            _ajax::pushEvent($o_command, $s_newRoomCode);

            // respond to this client
            $a_commands = array();
            
            // Don't try to pull from the server during the time it takes to fully redraw the game board,
            // and while waiting for other players to join the game.
            array_push(   $a_commands, new command("noPoll", 2)   );
            // don't execute any events later than the ones already pushed
            array_push(   $a_commands, new command("setLatestEvents", _ajax::getLatestEvents($o_newGame->getRoomCode()))   );
            // show the game
            array_push(   $a_commands, new command("showContent", "game")  );

            // clear all existing players in preperation for the new incoming players
            array_push(   $a_commands, new command("clearPlayers", "dontClearLocal")   );
            // Push players, then the game, then the players again.
            // We do this because correctly setting up both requires knowledge of the other.
            foreach ($o_newGame->getPlayers() as $i => $o_player) {
                array_push(   $a_commands, _ajax::getUpdatePlayerEvent($o_player)   );
            }
            array_push(   $a_commands, new command("updateGame", $o_newGame->toJsonObj())   );
            foreach ($o_newGame->getPlayers() as $i => $o_player) {
                array_push(   $a_commands, _ajax::getUpdatePlayerEvent($o_player)   );
            }
            array_push(   $a_commands, new command( "setLocalPlayer", $o_globalPlayer->getId() )   );
            array_push(   $a_commands, new command( "setPlayer1", $o_newGame->getPlayer1Id() )   );

            return new command("composite", $a_commands);
        }
        finally
        {
            // unlock the game
            $o_newGame->unlock();
        }
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
        array_push($a_commands, new command("removePlayer", $o_player->toJsonObj()));

        // leave the game
        $o_player->leaveGame();
        $o_player->save();
        $o_game->save();

        // push the changes to the game to other clients
        array_push($a_commands, _ajax::getUpdateGameEvent($o_game));
        $o_removeCmd = new command("composite", $a_commands);
        _ajax::pushEvent($o_removeCmd, $o_game->getRoomCode());

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
        $a_commands = array();

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
            array_push($a_commands, new command("showContent", "reveal"));
        }

        // update the game
        if ($o_game->setCurrentTurn($i_turn + $o_game->getPlayerCount())) {
            $o_game->save();

            // push the changes to the game to other clients
            array_push($a_commands, _ajax::getUpdateGameEvent($o_game));
        } else {
            return new command("showError", "Failed to update game sharing state");
        }

        // update the players
        foreach ($o_game->getPlayers() as $i => $o_player) {
            $o_player->b_isReady = FALSE;
            $o_player->save();
            array_push(  $a_commands, _ajax::getUpdatePlayerEvent($o_player)  );
        }

        // push the story
        $o_story = $o_game->getStory($i_turn);
        $a_cards = $o_story->getCards();
        array_push($a_commands, _ajax::getUpdateStoryEvent($o_story));
        for ($i = 0; $i < count($a_cards); $i++) {
            $o_card = $a_cards[$i];
            array_push(  $a_commands, _ajax::getUpdateCardEvent($o_card)  );
        }
        _ajax::pushEvent(new command("composite", $a_commands));

        // respond to this client
        return new command("success", "");
    }

    function startSharing() {
        return self::setSharingTurn(0);
    }

    function setStartCard() {
        global $o_globalPlayer;

        // check to make sure that the player is in a game
        $o_game = $o_globalPlayer->getGame();
        if (($bo_playerInGame = _ajax::isPlayerInGame($o_globalPlayer, $o_game)) !== TRUE)
            return $bo_playerInGame;

        // get the new start card
        $i_newStartCard = intval(get_post_var("startCard", "-1"));
        if ($i_newStartCard < 0 || $i_newStartCard > 1)
            return new command("showError", "Bad start card type. Must be one of '0' or '1'.");

        // update the game and broadcast the change
        $o_game->i_cardStartType = $i_newStartCard;
        _ajax::pushGame($o_game);

        // respond to this client
        return new command("success", "");
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
            array_push($a_commands, _ajax::getUpdateGameEvent($o_game));
        }

        // update the players
        foreach ($o_game->getPlayers() as $i => $o_player) {
            $o_player->b_isReady = FALSE;
            $o_player->save();
            array_push($a_commands, _ajax::getUpdatePlayerEvent($o_player));
        }

        // push the events
        _ajax::pushEvent(new command("composite", $a_commands), $o_game->getRoomCode(), FALSE);

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
        $o_globalPlayer->b_isReady = TRUE;
        $o_globalPlayer->save();

        // alert everybody in the game
        $a_commands = array(
            _ajax::getUpdateStoryEvent($o_story),
            _ajax::getUpdateCardEvent($o_card),
            _ajax::getUpdatePlayerEvent($o_globalPlayer)
        );
        _ajax::pushEvent(new command("composite", $a_commands));

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
        $o_globalPlayer->b_isReady = TRUE;
        $o_globalPlayer->save();

        // alert everybody in the game
        $a_commands = array(
            _ajax::getUpdateStoryEvent($o_story),
            _ajax::getUpdateCardEvent($o_card),
            _ajax::getUpdatePlayerEvent($o_globalPlayer)
        );
        _ajax::pushEvent(new command("composite", $a_commands), $o_game->getRoomCode());

        // return success
        return new command("success", "");
    }

    function revealCard() {
        global $o_globalPlayer;

        $i_cardId = intval(get_post_var("cardId"));

        // check to make sure that the player is in a game
        $o_game = $o_globalPlayer->getGame();
        if (($bo_playerInGame = _ajax::isPlayerInGame($o_globalPlayer, $o_game)) !== TRUE)
            return $bo_playerInGame;

        // get the card and make sure this player matches the author
        $o_card = card::loadById($i_cardId);
        if ($o_card->getAuthorId() != $o_globalPlayer->getId()) {
            return new command("showError", "Can't reveal this card. Not the card author.");
        }

        // check if already revealed
        $a_revealStatus = $o_card->getRevealStatus();
        if ($a_revealStatus[0] != 0) {
            return new command("showError", $a_revealStatus[1]);
        }

        // update the card
        $o_card->b_isRevealed = TRUE;
        $o_card->save();

        // update the player
        $o_globalPlayer->b_isReady = TRUE;
        $o_globalPlayer->save();

        // push event
        $a_commands = array(
            _ajax::getUpdateCardEvent($o_card),
            _ajax::getUpdatePlayerEvent($o_globalPlayer),
            _ajax::getUpdateStoryEvent($o_card->getStory())
        );
        _ajax::pushEvent(new command("composite", $a_commands), $o_game->getRoomCode());

        // return success
        return new command("success", "");
    }

    function rateGame() {
        global $o_globalPlayer;

        $s_roomCode = get_post_var("roomCode");
        $s_rating = get_post_var("rating");

        // get the reported game
        $o_game = $o_globalPlayer->getGame();
        if ($o_game === null)
            return "Game with roomCode \"{$s_roomCode}\" does not exist!"; // don't report errors for game ratings
            // return new command("success", "");

        // check to make sure that the player is part of the reported game
        if (!$o_game->containsPlayer($o_globalPlayer->getId()))
            return "Player not a part of the game with roomCode \"{$s_roomCode}\"!"; // don't report errors for game ratings
            // return new command("success", "");

        // update the rating
        $ob_result = $o_globalPlayer->rateGame($o_game, $s_rating);
        if ($ob_result !== TRUE)
            return $ob_result; // don't report errors for game ratings
            // return new command("success", "");
        
        return new command("success", "");
    }

    function reportError() {
        global $o_globalPlayer;
        global $maindb;
        global $feedback_email;

        // rate limiting
        $s_remoteIp = $_SERVER['REMOTE_ADDR'];
        $d_now = new DateTime('now');
        $d_rateLimitTime = $d_now->sub(new DateInterval('PT15M')); // 15 minutes ago
        $s_rateLimitTime = getStringFromDateTime($d_rateLimitTime);
        $a_queryVals = array(
            "ip" => $s_remoteIp,
            "time" => $s_rateLimitTime,
        );
        $a_rateCount = db_query("SELECT COUNT(`id`) AS 'CNT' FROM `{$maindb}`.`reportedErrors` WHERE `ip`='[ip]' AND `time`>'[time]'", $a_queryVals, TRUE);
        if (is_array($a_rateCount) && intval($a_rateCount[0]['CNT']) > 1) {
            error_log("TelePictionary error reporting rate limiting in effect");
            return new command("success", "can't report more than 5 errors in 15 minutes");
        }

        // collect the reportables
        $a_reportables = get_post_var('reportables', array());
        $a_reportables['server-player'] = $o_globalPlayer->toJsonObj();
        $s_roomCode = "----";
        if ($o_globalPlayer->getGameState()[0] !== GAME_PSTATE::NOT_READY) {
            $o_game = $o_globalPlayer->getGame();
            $a_reportables['server-game'] = $o_game->toJsonObj();
            $a_reportables['server-latestEvents'] = _ajax::getLatestEvents($o_game->getRoomCode());
            $s_roomCode = $o_game->getRoomCode();
        } else {
            $a_reportables['server-game'] = null;
            $a_reportables['server-latestEvents'] = array();
        }

        // log to the database
        $s_remoteIp = $_SERVER['REMOTE_ADDR'];
        $s_time = getStringFromDateTime(new DateTime('now'));
        $a_insertVals = array(
            "player" => $o_globalPlayer->getId(),
            "roomCode" => $s_roomCode,
            "reportables" => json_encode($a_reportables),
            "ip" => $s_remoteIp,
            "time" => $s_time
        );
        $s_insertVals = array_to_insert_clause($a_insertVals);
        db_query("INSERT INTO `{$maindb}`.`reportedErrors` {$s_insertVals}", $a_insertVals);

        // send myself an email
        $a_reportables['server-player'] = $o_globalPlayer->toJsonObj();
        $a_reportables['server-roomCode'] = $s_roomCode;
        $a_reportables['ip'] = $s_remoteIp;
        $a_reportables['time'] = $s_time;
        $s_subject = "TelePictionary Reported Error";
        $s_content = array_to_str($a_reportables);
        $s_headers = 'From: TelePictionary@bbean.us';
        mail($feedback_email, $s_subject, $s_content, $s_headers);

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

        // connect to the server
        if (is_string($so_socket = _ajax::serverConnect("poll new events"))) {
            return new command("showError", $so_socket);
        }

        // server connected, get any new events
        $sbo_ret = "failed to connect to events server";
        try {
            _ajax::serverWrite($so_socket, "subscribe", array(
                "latestEvents" => $a_latestEvents,
                "roomCode" => $o_game->getRoomCode()
            ));
            $sbo_ret = _ajax::serverRead($so_socket);

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
        } finally {
            _ajax::serverDisconnect($so_socket);
        }

        return $sbo_ret;
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
            $a_commands = array();
            array_push( $a_commands, new command("clearPlayers", "") );
            array_push( $a_commands, new command("updatePlayer", $o_globalPlayer->toJsonObj()) );
            array_push( $a_commands, new command("setPlayer1", $o_globalPlayer->getId()) );
            $o_ret = new command("composite", $a_commands);
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
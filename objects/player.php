<?php

require_once(dirname(__FILE__)."/../resources/db_query.php");
require_once(dirname(__FILE__)."/../resources/globals.php");

abstract class GAME_PSTATE
{
	const NOT_READY   = 0;
	const READY       = 1;
	const WAITING     = 2;
	const IN_PROGRESS = 3;
	const REVEALING   = 4;
	const DONE        = 5;
}

class player
{
	public $s_name = '';
	public $i_id = 0;
	public $s_roomCode = '';
	public $i_storyId = -1;
	public $s_storyName = '';
	public $a_gameIds = array();
	public $i_imageId = -1;
	public $b_ready = FALSE;

	function __construct($s_name) {
		$this->s_name = $s_name;
	}

	/*********************************************************************
	 *                     P U B L I C   F U N C T I O N S               *
	 *********************************************************************/
	public function getId() {
		return $this->i_id;
	}
	public function getName() {
		return $this->s_name;
	}
	public function getRoomCode() {
		return $this->s_roomCode;
	}
	public function getGame() {
		return game::loadByRoomCode($this->s_roomCode);
	}
	public function getCurrentCard() {
		if ($this->getGameState()[0] != GAME_PSTATE::IN_PROGRESS)
			return null;
		$o_game = $this->getGame();
		$o_prevPlayer = $o_game->getPlayerRevOrder($this->i_id, $o_game->getCurrentTurn());
		$o_story = $o_prevPlayer->getStory();
		return $o_story->getCard($o_game->getCurrentTurn());
	}
	public function getStory() {
		$o_game = $this->getGame();
		if ($o_game === null)
			return null;
		if ($this->i_storyId < 0)
		{
			$o_story = new story($this->getRoomCode(), $this->getId());
			$o_story->save();
			$this->i_storyId = $o_story->getId();
			$this->save();
		}
		return story::loadById($this->i_storyId);
	}
	public function getImageURL() {
		$o_image = $this->getImage();
		if ($o_image === null)
			return "";
		return $o_image->getURL();
	}
	public function getImage() {
		return image::loadById($this->i_imageId);
	}
	public function getImageId() {
		return $this->i_imageId;
	}
	public function getGameState() {
		if ($this->s_name == '')
		{
			return array(GAME_PSTATE::NOT_READY, 'Player not ready');
		}

		$o_game = $this->getGame();
		if ($o_game == null)
		{
			return array(GAME_PSTATE::READY, 'Ready to join game');
		}

		$a_gameState = $o_game->getGameState();
		if ($a_gameState[0] == GAME_GSTATE::READY)
		{
			return array(GAME_PSTATE::WAITING, 'Ready to begin');
		}

		if ($a_gameState[0] == GAME_GSTATE::IN_PROGRESS)
		{
			return array(GAME_PSTATE::IN_PROGRESS, 'Game in progress');
		}

		if ($a_gameState[0] == GAME_GSTATE::REVEALING)
		{
			return array(GAME_PSTATE::REVEALING, 'Finishing game'); // in reveal
		}

		if ($a_gameState[0] == GAME_GSTATE::DONE)
		{
			return array(GAME_PSTATE::DONE, 'Game done'); // done revealing, ready to start next game
		}

		return array(-1, 'Error: unknown player game state');
	}
	public function isReady() {
		return $this->b_ready;
	}
	public function toJsonObj() {
		return array(
			'name' => $this->s_name,
			'id' => $this->i_id,
			'roomCode' => $this->s_roomCode,
			'storyId' => $this->i_storyId,
			'storyName' => $this->s_storyName,
			'imageURL' => $this->getImageURL(),
			'ready' => $this->b_ready,
			'gameState' => $this->getGameState()
		);
	}

	public function joinGame($o_game) {
		if ($this->s_roomCode == $o_game->getRoomCode())
			return;
		$this->b_ready = FALSE;
		$this->i_storyId = -1;
		$o_game->updatePlayer($this->i_id);
		$this->s_roomCode = $o_game->s_roomCode;
		if (!in_array($o_game->getId(), $this->a_gameIds)) {
			array_push($this->a_gameIds, $o_game->getId());
		}
		if ($this->getGameState()[0] <= GAME_PSTATE::IN_PROGRESS) {
			$this->i_storyId = -1;
		}
	}
	public function leaveGame() {
		$o_game = $this->getGame();
		if ($o_game !== null)
			$o_game->removePlayer($this->i_id);
		$this->s_roomCode = '';
	}
	public function updateImage($i_alias, $s_extension) {
		// create/update the image
		$b_oldImage = FALSE;
        $o_image = $this->getImage();
        if ($o_image === null) {
            $o_image = new image(TRUE, $this->getId());
        } else {
        	$b_oldImage = TRUE;
        	$i_oldAlias = $o_image->getAlias();
        	$s_oldExtension = $o_image->getExtension();
        }
        $o_image->setImage($i_alias, $s_extension);
        $o_image->s_roomCode = $this->getRoomCode();
        $o_image->i_playerId = $this->getId();
        $o_image->save();

        // update this player
        $this->i_imageId = $o_image->getId();
        $this->save();

        // delete the old image
        if ($b_oldImage) {
	        $s_oldFilePath = dirname(__FILE__) . "/../../../telePictionaryUserImages/{$i_oldAlias}.{$s_oldExtension}";
	        if (file_exists($s_oldFilePath)) {
	        	unlink($s_oldFilePath);
	        }
	    }
	}
	public function save() {
		global $maindb;
		$b_isNew = FALSE;

		$a_whereVals = array(
			"id" => $this->i_id
		);
		$s_whereClause = array_to_where_clause($a_whereVals);
		$a_updateVals = array(
			"name" => $this->s_name,
			"roomCode" => $this->s_roomCode,
			"storyId" => $this->i_storyId,
			"gameIds" => implodeIds($this->a_gameIds),
			"imageId" => $this->i_imageId,
			"ready" => ($this->b_ready ? 1 : 0)
		);
		$s_updateClause = array_to_update_clause($a_updateVals);

		// insert this player as a new row if necessary
		$a_players = db_query("SELECT * FROM `{$maindb}`.`players` WHERE `id`='[id]'", array("id"=>$this->i_id));
		if (!is_array($a_players) || count($a_players) == 0) {
			// insert a new row for this player
			$s_setClause = array_to_set_clause($a_updateVals);
			db_query("INSERT INTO `{$maindb}`.`players` {$s_setClause}", $a_updateVals);

			// update the player id with the mysql id
			$s_whereClause2 = array_to_where_clause($a_updateVals);
			$a_players2 = db_query("SELECT `id` FROM `{$maindb}`.`players` WHERE {$s_whereClause2} ORDER BY `id` DESC", $a_updateVals);
			if (is_array($a_players2) && count($a_players2) > 0) {
				$this->i_id = intval($a_players2[0]['id']);
				$a_whereVals['id'] = $this->i_id;
				$s_whereClause = array_to_where_clause($a_whereVals);
			}

			$b_isNew = TRUE;
		}

		// update the player
		db_query("UPDATE `{$maindb}`.`players` SET {$s_updateClause} WHERE {$s_whereClause}", array_merge($a_updateVals, $a_whereVals));

		return $b_isNew;
	}

	/*******************************************************
	 *           S T A T I C   F U N C T I O N S           *
	 ******************************************************/
	
	public static function readyToJoinGame($i_playerId) {
		$o_player = self::loadById($i_playerId);
		if ($o_player == null)
		{
			return array(FALSE, "Error: uknown player with id '{$i_playerId}'");
		}

		$a_gameState = $o_player->getGameState();
		if ($a_gameState[0] == GAME_PSTATE::NOT_READY)
		{
			return array(FALSE, "Error: player not ready");
		}
		if ($a_gameState[0] >= GAME_PSTATE::WAITING && $a_gameState[0] < GAME_PSTATE::DONE)
		{
			return array(FALSE, "Error: player already in a game");
		}
		if ($a_gameState[0] == GAME_PSTATE::READY || $a_gameState[0] == GAME_PSTATE::DONE)
		{
			return array(TRUE, "Player can be added");
		}

		return array(FALSE, $a_gameState[1]);
	}

	/**
	 * @return             object  either a player object or NULL
	 */
	public static function loadById($i_playerId) {
		global $maindb;
		global $player_staticPlayers;
		
		// check if already loaded
		if (!isset($player_staticPlayers))
			$player_staticPlayers = [];
		if (isset($player_staticPlayers[$i_playerId]))
		{
			return $player_staticPlayers[$i_playerId];
		}
		
		// load the player
		$o_player = null;
		$a_players = db_query("SELECT * FROM `{$maindb}`.`players` WHERE `id`='[id]'", array("id"=>$i_playerId));
		if (is_array($a_players) && count($a_players) > 0) {
			$o_player = new player($a_players[0]['name']);
			$o_player->i_id = intval($a_players[0]['id']);
			$o_player->s_roomCode = $a_players[0]['roomCode'];
			$o_player->i_storyId = intval($a_players[0]['storyId']);
			$o_player->a_gameIds = explodeIds($a_players[0]['gameIds'], 'intval');
			$o_player->i_imageId = intval($a_players[0]['imageId']);
			$o_player->b_ready = (intval($a_players[0]['ready']) == 0) ? FALSE : TRUE;

			$player_staticPlayers[$i_playerId] = $o_player;
		}
		return $o_player;
	}

	/**
	 * Tries to access the global player by looking for the
	 * 'playerId' _GET and then _POST variable. If those are not set,
	 * this function will try to access the playerId from the
	 * _SESSION variable. If that is not set, then a new player
	 * instance is created and the _SESSION['playerId'] is set to the
	 * id of that new instance.
 	 *
 	 * TODO not implemented yet: the player secret is attempted to be
 	 * loaded from the same source and compared to the secret from
 	 * the MySQL row. If the secret doesn't match then the global
 	 * player instance is not set, an error message is flushed to
 	 * the output buffer, and this function returns FALSE.
	 *
	 * Because this function uses the $_SESSION variable, any thread
	 * that accesses this function will be forced to wait until other
	 * threads release the $_SESSION object.
	 */
	public static function getGlobalPlayer($b_forceMatchSession = FALSE) {
		global $o_globalPlayer;
		global $b_badPlayerPostId;

		// check if already loaded
		if (isset($o_globalPlayer) && $o_globalPlayer !== undefined && $o_globalPlayer !== null)
		{
			if (!$b_forceMatchSession)
			{
				return $o_globalPlayer;
			}
		}

		// not loaded, get the player id and player
		$i_playerId = -1;
		$o_player = null;
		if (isset($_GET['playerId']))
		{
			// load from get var
			$i_playerId = intval($_GET['playerId']);
			$o_player = self::loadById($i_playerId);
			if ($o_player === null) {
				$url = "https://{$_SERVER['HTTP_HOST']}{$_SERVER['REQUEST_URI']}";
				$url = preg_replace('/playerId=[0-9]+/', '', $url);
				header('Location: ' . $url);
			}
			// error_log("getting player from get var: {$i_playerId}");
		}
		if ($o_player === null && isset($_POST['playerId']))
		{
			// loading from get var failed
			// load from post var
			$i_playerId = intval($_POST['playerId']);
			$o_player = self::loadById($i_playerId);
			$b_badPlayerPostId = ($o_player === null); // indicates that the post var is bad
			// error_log("getting player from post var: {$i_playerId}");
		}

		// get/update session variables
		if ($o_player === null)
		{
			// loading from get and post vars failed
			// load from session
			my_session_start();
			if (isset($_SESSION['playerId']))
			{
				$i_playerId = $_SESSION['playerId'];
				$o_player = self::loadById($i_playerId);
				// error_log("getting player from session: {$i_playerId}");
			}
			if ($o_player === null)
			{
				// player not set in session variable
				$o_player = new player('');
				$o_player->save();
				$_SESSION['playerId'] = $o_player->getId();
				$i_playerId = $o_player->getId();
				// error_log("created new player: {$i_playerId}");
			}
		}
		else // if ($o_player != NULL)
		{
			if ($b_forceMatchSession)
			{
				// double-check that the session playerId matches
				// the get/post var playerId
				my_session_start();
				$_SESSION['playerId'] = $o_player->getId();
			}
		}

		$o_globalPlayer = $o_player;
		return $o_globalPlayer;
	}
}
?>
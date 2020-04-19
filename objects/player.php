<?php

require_once(dirname(__FILE__)."/../resources/db_query.php");
require_once(dirname(__FILE__)."/../resources/globals.php");

class player
{
	public $s_name = '';
	public $i_id = 0;
	public $s_roomCode = '';
	public $i_leftNeighbor = -1;
	public $i_rightNeighbor = -1;
	public $o_leftNeighbor = null;
	public $o_rightNeighbor = null;
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
	public function getNeighbor($b_isLeft = FALSE) {
		if ($b_isLeft)
		{
			if ($o_leftNeighbor == null)
			{
				$o_leftNeighbor = player::loadById($this->i_leftNeighbor);
			}
			return $this->o_leftNeighbor;
		}
		else
		{
			if ($o_rightNeighbor == null)
			{
				$o_rightNeighbor = player::loadById($this->i_rightNeighbor);
			}
			return $this->o_rightNeighbor;
		}
	}
	public function getGame() {
		return game::loadByRoomCode($this->s_roomCode);
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
			return array(0, 'Player not ready');
		}

		$o_game = $this->getGame();
		if ($o_game == null)
		{
			return array(1, 'Ready to join game');
		}

		$a_gameState = $o_game->getGameState();
		if ($a_gameState[0] == 1)
		{
			return array(2, 'Ready to begin');
		}

		if ($a_gameState[0] == 2)
		{
			return array(3, 'Game in progress');
		}

		if ($a_gameState[0] == 3)
		{
			return array(4, 'Finishing game');
		}

		if ($a_gameState[0] == 4)
		{
			return array(5, 'Game done');
		}

		return array(-1, 'Error: unknown player game state');
	}
	public function isReady()
	{
		return $this->b_ready;
	}
	public function toJsonObj()
	{
		return array(
			'name' => $this->s_name,
			'id' => $this->i_id,
			'roomCode' => $this->s_roomCode,
			'leftNeighbor' => $this->i_leftNeighbor,
			'rightNeighbor' => $this->i_rightNeighbor,
			'storyId' => $this->i_storyId,
			'storyName' => $this->s_storyName,
			'imageURL' => $this->getImageURL(),
			'ready' => $this->b_ready
		);
	}

	public function joinGame($o_game)
	{
		$o_game->addPlayer($this->i_id);
		$this->s_roomCode = $o_game->s_roomCode;
		if ($this->getGameState()[0] <= 2)
		{
			$this->i_storyId = -1;
		}
	}
	public function leaveGame()
	{
		$o_game = $this->getGame();
		if ($o_game !== null)
			$o_game->removePlayer($this->i_id);
		$this->s_roomCode = '';
	}
	public function updateImage($i_alias, $s_extension)
	{
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
	public function save()
	{
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
		if ($a_gameState[0] == 0)
		{
			return array(FALSE, "Error: player not ready");
		}
		if ($a_gameState[0] > 1)
		{
			return array(FALSE, "Error: player already in a game");
		}
		if ($a_gameState[0] != 1)
		{
			return array(FALSE, $a_gameState[1]);
		}

		return array(TRUE, "Player can be added");
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

			$o_player->i_leftNeighbor = -1;
			$o_player->i_rightNeighbor = -1;
			$o_player->o_leftNeighbor = null;
			$o_player->o_rightNeighbor = null;
			$o_game = $o_player->getGame();
			if ($o_game != null)
			{
				$a_playerOrder = $o_game->getPlayerOrder();
				if (count($a_playerOrder) > 0)
				{
					for ($i = 0; $i < count($a_playerOrder); $i++)
					{
						if ($a_playerOrder[$i] == $o_player->i_id)
						{
							break;
						}
					}
					$o_player->i_leftNeighbor = ($i > 0) ? $a_playerOrder[$i - 1] : $a_playerOrder[count($a_playerOrder) - 1];
					$o_player->i_rightNeighbor = ($i < count($a_playerOrder)-1) ? $a_playerOrder[$i+1] : $a_playerOrder[0];
				}
			}

			$player_staticPlayers[$i_playerId] = $o_player;
		}
		return $o_player;
	}

	/**
	 * Tries to access the global player by looking for the
	 * 'playerId' _POST variable. If it is not set, this function
	 * will try to access the playerId from the _SESSION variable.
	 *
	 * Because this function uses the $_SESSION variable, any thread
	 * that accesses this function will be forced to wait until other
	 * threads release the $_SESSION object.
	 */
	public static function getGlobalPlayer() {
		global $o_globalPlayer;

		// check if already loaded
		if (isset($o_globalPlayer) && $o_globalPlayer !== undefined && $o_globalPlayer !== null)
		{
			return $o_globalPlayer;
		}

		// not loaded, get the player id
		$i_playerId = -1;
		if (isset($_POST['playerId']))
		{
			// load from post var
			$i_playerId = intval($_POST['playerId']);
		}
		else
		{
			// load from session
			my_session_start();
			if (isset($_SESSION['playerId']))
			{
				$i_playerId = $_SESSION['playerId'];
			}
			else
			{
				$o_globalPlayer = new player('');
				$o_globalPlayer->save();
				$_SESSION['playerId'] = $o_globalPlayer->getId();
				$i_playerId = $o_globalPlayer->getId();
			}
		}

		// now load the player from the playerId
		$o_globalPlayer = self::loadById($i_playerId);

		return $o_globalPlayer;
	}
}
?>
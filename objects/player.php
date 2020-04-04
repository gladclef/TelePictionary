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
	private static $a_staticPlayers;

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
	public function getNeighbor($b_isLeft = false) {
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
		// TODO
		return null;
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
	public function toJsonObj()
	{
		return array(
			'name' => $this->s_name,
			'id' => $this->i_id,
			'roomCode' => $this->s_roomCode,
			'leftNeighbor' => $this->i_leftNeighbor,
			'rightNeighbor' => $this->i_rightNeighbor,
			'storyId' => $this->i_storyId,
			'storyName' => $this->s_storyName
		);
	}

	public function joinGame($o_game)
	{
		$o_game->addPlayer($this->i_id);
		$this->s_roomCode = $o_game->s_roomCode;
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
			"gameIds" => implodeIds($this->a_gameIds)
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
				$this->i_id = $a_players2[0]['id'];
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
		
		// check if already loaded
		if (isset($a_staticPlayers[$i_playerId]))
		{
			return $a_staticPlayers[$i_playerId];
		}
		
		// load the player
		$o_player = null;
		$a_players = db_query("SELECT * FROM `{$maindb}`.`players` WHERE `id`='[id]'", array("id"=>$i_playerId));
		if (is_array($a_players) && count($a_players) > 0) {
			$o_player = new player($a_players[0]['name']);
			$o_player->i_id = $a_players[0]['id'];
			$o_player->s_roomCode = $a_players[0]['roomCode'];
			$o_player->i_storyId = $a_players[0]['storyId'];
			$o_player->a_gameIds = explodeIds($a_players[0]['gameIds']);

			$o_player->i_leftNeighbor = -1;
			$o_player->i_rightNeighbor = -1;
			$o_player->o_leftNeighbor = null;
			$o_player->o_rightNeighbor = null;
			$o_game = game::loadByRoomCode($o_player->s_roomCode);
			if ($o_game != null)
			{
				$a_playerOrder = explodeIds($o_game->getPlayerOrder());
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

			$a_staticPlayers[$i_playerId] = $o_player;
		}
		return $o_player;
	}

	public static function getGlobalPlayer() {
		global $o_globalPlayer;

		if (isset($o_globalPlayer) && $o_globalPlayer !== undefined && $o_globalPlayer !== null)
		{
			return $o_globalPlayer;
		}

		my_session_start();
		if (!isset($_SESSION['globalPlayer']))
		{
			$o_globalPlayer = new player('');
			$_SESSION['globalPlayer'] = $o_globalPlayer;
		}
		else
		{
			$o_globalPlayer = $_SESSION['globalPlayer'];
		}

		return $o_globalPlayer;
	}
}
?>
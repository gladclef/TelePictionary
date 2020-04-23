<?php

require_once(dirname(__FILE__)."/../resources/db_query.php");
require_once(dirname(__FILE__)."/../resources/globals.php");

class game
{
	public $s_name = '';
	public $i_id = 0;
	public $s_roomCode = '';
	public $a_playerIds = array();
	public $a_players = array();
	public $a_playerOrder = array();
	public $d_startTime = null;
	public $i_cardStartType = 0;
	public $i_player1Id = 0;
	public $i_drawTimerLen = 60;
	public $i_textTimerLen = 30;
	public $d_turnStart = null;//new DateTime('now');
	public $i_currentTurn = -1;

	function __construct($s_name, $i_player1Id) {
		$this->d_startTime = new DateTime('now');
		$this->d_turnStart = $this->d_startTime;
		$this->s_name = $s_name;
		$this->i_player1Id = $i_player1Id;
		$this->s_roomCode = self::getUnusedRoomCode();
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
	public function getPlayerCount() {
		return count($this->a_playerIds);
	}
	public function getPlayerIds() {
		return $this->a_playerIds;
	}
	public function getPlayers() {
		$this->a_players = array();
		for ($i = 0; $i < count($this->a_playerIds); $i++)
		{
			$this->a_players[$i] = player::loadById($this->a_playerIds[$i]);
		}
		return $this->a_players;
	}
	public function getPlayerOrder() {
		return $this->a_playerOrder;
	}
	public function getStartTime() {
		return $this->d_startTime;
	}
	public function getCardStartType() {
		return $this->i_cardStartType;
	}
	public function getPlayer1() {
		return player::loadById($this->i_player1Id);
	}
	public function getPlayer1Id() {
		return $this->i_player1Id;
	}
	public function getDrawTimerLen() {
		return $this->i_drawTimerLen;
	}
	public function getTextTimerLen() {
		return $this->i_textTimerLen;
	}
	public function getTurnStart() {
		return $this->d_turnStart;
	}
	public function getTurnRemaining() {
		$d_now = DateTime('now');
		return $d_now->getTimestamp() - $this->d_turnStart->getTimestamp();
	}
	public function getCurrentTurn() {
		return $this->i_currentTurn;
	}
	public function getPlayerRevOrder($i_currentPlayer, $i_turnsPrevious) {
		$a_playerOrder = $this->getPlayerOrder();
		$i_foundCurrentPlayer = 0;
		
		// double the length of the array so that we can wrap around without having to do as much housekeeping
		$i_count = count($a_playerOrder);
		for ($i = 0; $i < $i_count; $i++)
		{
			array_push($a_playerOrder, $a_playerOrder[$i]);
		}

		// find the current player twice, then return the player id from turnsPrevious ago
		for ($i = 0; $i < count($a_playerOrder) * 2; $i++)
		{
			// go through until we find the current player
			if ($a_playerOrder[$i] == $i_currentPlayer) {
				$i_foundCurrentPlayer++;
			}

			// if we've seen the current player twice, then return the player from turnsPrevious ago
			if ($i_foundCurrentPlayer == 2)
			{
				return player::loadById($a_playerOrder[$i - $i_turnsPrevious]);
			}
		}
		return null;
	}
	public function getPlayerIdInOrder($i_startingPlayerId, $i_turnsLater) {
		$a_playerOrder = $this->getPlayerOrder();
		$b_foundStartingPlayer = false;
		
		// double the length of the array so that we can wrap around without having to do as much housekeeping
		$i_count = count($a_playerOrder);
		for ($i = 0; $i < $i_count; $i++)
		{
			array_push($a_playerOrder, $a_playerOrder[$i]);
		}

		// find the player $i_turnsLater after the starting player in player order
		for ($i = 0; $i < count($a_playerOrder) * 2; $i++)
		{
			// go through until we find the starting player
			if ($a_playerOrder[$i] == $i_startingPlayerId) {
				$b_foundStartingPlayer = true;
			}

			// if we've met $i_turnsLater, then return this player
			if ($b_foundStartingPlayer)
			{
				if ($i_turnsLater == 0)
				{
					return $a_playerOrder[$i];
				}
				$i_turnsLater--;
			}
		}
		return -1;
	}
	public function getPlayerInOrder($i_startingPlayerId, $i_turnsLater) {
		$i_playerId = getPlayerIdInOrder($i_startingPlayerId, $i_turnsLater);
	}
	public function getGameState() {
		if ($this->i_currentTurn == -1)
		{
			return array(1, 'Waiting to start game');
		}

		if ($this->i_currentTurn < count($this->a_playerIds))
		{
			return array(2, 'Game in progress');
		}

		if ($this->i_currentTurn < count($this->a_playerIds)*2)
		{
			return array(3, 'Revealing cards');
		}

		if ($this->i_currentTurn == count($this->a_playerIds)*2)
		{
			return array(4, 'Done');
		}

		return array(-1, 'Error: unknown game state');
	}
	public function toJsonObj()
	{
		return array(
			"roomCode" => $this->s_roomCode,
			"name" => $this->s_name,
			"playerIds" => $this->a_playerIds,
			"playerOrder" => $this->a_playerOrder,
			"startTime" => self::getStringFromDateTime($this->d_startTime),
			"cardStartType" => $this->i_cardStartType,
			"player1Id" => $this->i_player1Id,
			"drawTimerLen" => $this->i_drawTimerLen,
			"textTimerLen" => $this->i_textTimerLen,
			"turnStart" => self::getStringFromDateTime($this->d_turnStart),
			"currentTurn" => $this->i_currentTurn
		);
	}

	public function addPlayer($i_playerId) {
		if (in_array($i_playerId, $this->a_playerIds))
		{
			return array(TRUE, "Player already in game");
		}

		$a_canAddPlayer = player::readyToJoinGame($i_playerId);
		if ($a_canAddPlayer[0] == FALSE)
		{
			return $a_canAddPlayer;
		}

		array_push($this->a_playerIds, $i_playerId);
		array_push($this->a_playerOrder, $i_playerId);
		return array(TRUE, "Player added");
	}
	public function removePlayer($i_playerId) {
		// remove the player
		if (!in_array($i_playerId, $this->a_playerIds))
		{
			return array(TRUE, "Player already removed");
		}
		array_splice($this->a_playerIds,   array_search($i_playerId, $this->a_playerIds),   1);
		array_splice($this->a_playerOrder, array_search($i_playerId, $this->a_playerOrder), 1);

		// update player1, as necessary
		if ($this->i_player1Id === $i_playerId && count($this->a_playerIds) > 0)
		{
			$this->i_player1Id = $this->a_playerIds[0];
		}
		return array(TRUE, "Player removed");
	}
	public function setCurrentTurn($i_currentTurn)
	{
		if ($i_currentTurn === $this->i_currentTurn)
			return FALSE;
		$this->i_currentTurn = $i_currentTurn;
		$this->d_turnStart = new DateTime('now');
		return TRUE;
	}
	public function save()
	{
		global $maindb;

		$a_createVals = array(
			"database" => $maindb,
			"table" => "games"
		);
		$a_whereVals = array(
			"roomCode" => $this->s_roomCode
		);
		$s_whereClause = array_to_where_clause($a_whereVals);
		$a_updateVals = array(
			"name" => $this->s_name,
			"playerIds" => implodeIds($this->a_playerIds),
			"playerOrder" => implodeIds($this->a_playerOrder),
			"startTime" => self::getStringFromDateTime($this->d_startTime),
			"cardStartType" => $this->i_cardStartType,
			"player1Id" => $this->i_player1Id,
			"drawTimerLen" => $this->i_drawTimerLen,
			"textTimerLen" => $this->i_textTimerLen,
			"turnStart" => self::getStringFromDateTime($this->d_turnStart),
			"currentTurn" => $this->i_currentTurn
		);
		$s_updateClause = array_to_update_clause($a_updateVals);

		create_row_if_not_existing(array_merge($a_whereVals, $a_createVals));
		db_query("UPDATE {$maindb}.games SET {$s_updateClause} WHERE {$s_whereClause}", array_merge($a_updateVals, $a_whereVals));
	}

	/*******************************************************
	 *           S T A T I C   F U N C T I O N S           *
	 ******************************************************/
	
	/**
	 * @param $s_datetime format 'YYYY-MM-DD hh:mm:ss'
	 */
	public static function getDateTimeFromString($s_datetime)
	{
		return DateTime::createFromFormat('Y-m-d H:i:s', $s_datetime);
	}

	public static function getStringFromDateTime($d_datetime)
	{
		return date_format($d_datetime, 'Y-m-d H:i:s');
	}

	public static function createGame($s_name, $i_player1Id, $i_cardStartType, $i_drawTimerLen, $i_textTimerLen)
	{
		$a_canAddPlayer = player::readyToJoinGame($i_playerId);
		if ($a_canAddPlayer[0] == FALSE)
		{
			return array(FALSE, "Error with player: \"{$a_canAddPlayer[1]}\"");
		}
		$s_roomCode = self::getUnusedRoomCode();
		if ($s_roomCode == null)
		{
			return array(FALSE, "Failed to find unique room code. Please try again.");
		}

		$o_game = new game($s_name, $i_player1Id);
		$o_game->s_roomCode = $s_roomCode;
		$o_game->i_cardStartType = $i_cardStartType;
		$o_game->i_drawTimerLen = $i_drawTimerLen;
		$o_game->i_textTimerLen = $i_textTimerLen;
	}

	public static function getUnusedRoomCode()
	{
		global $maindb;

		for ($attempt = 0; $attempt < 1000; $attempt++)
		{
			// generate a new room code
			$ret = "";
			for ($i = 0; $i < 4; $i++)
			{
				$ret .= chr(ord('A') + rand(0, 25));
			}

			// check to see if it exists already
			$a_games = db_query("SELECT * FROM `{$maindb}`.`games` WHERE `roomCode`='[roomCode]'", array("roomCode"=>$ret));
			if (is_array($a_games) && count($a_games) > 0) {
				// pass
			} else {
				return $ret;
			}
		}

		return null;
	}

	/**
	 * @return             object  either a game object or NULL
	 */
	public static function loadByRoomCode($s_roomCode) {
		global $maindb;
		global $game_staticGames;
		
		// check if already loaded
		if (!isset($game_staticGames))
			$game_staticGames = [];
		if (isset($game_staticGames[$s_roomCode]))
		{
			return $game_staticGames[$s_roomCode];
		}
		
		// load the game
		$o_game = null;
		$a_games = db_query("SELECT * FROM `{$maindb}`.`games` WHERE `roomCode`='[roomCode]'", array("roomCode"=>$s_roomCode));
		if (is_array($a_games) && count($a_games) > 0) {
			$o_game = new game($a_games[0]['name'], intval($a_games[0]['player1Id']));
			$o_game->i_id = intval($a_games[0]['id']);
			$o_game->s_roomCode = $a_games[0]['roomCode'];
			$o_game->a_playerIds = explodeIds($a_games[0]['playerIds'], 'intval');
			$o_game->a_playerOrder = explodeIds($a_games[0]['playerOrder'], 'intval');
			$o_game->d_startTime = self::getDateTimeFromString($a_games[0]['startTime']);
			$o_game->i_player1Id = intval($a_games[0]['player1Id']);
			$o_game->i_cardStartType = intval($a_games[0]['cardStartType']);
			$o_game->i_drawTimerLen = intval($a_games[0]['drawTimerLen']);
			$o_game->i_textTimerLen = intval($a_games[0]['textTimerLen']);
			$o_game->d_turnStart = self::getDateTimeFromString($a_games[0]['turnStart']);
			$o_game->i_currentTurn = intval($a_games[0]['currentTurn']);

			$game_staticGames[$s_roomCode] = $o_game;
		}
		return $o_game;
	}
}
?>
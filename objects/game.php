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
	private static $a_staticGames;

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
	public function getPlayerIds() {
		return $this->a_playerIds;
	}
	public function getPlayers() {
		$this->a_players = array();
		for ($i = 0; $i < count($this->a_playerIds); $i++)
		{
			$this->a_players[$i] = player::loadFromId($this->a_playerIds[$i]);
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
		return player::loadFromId($this->i_player1Id);
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

		$this->a_playerIds[count($this->a_playerIds)-1] = $i_playerId;
		$this->a_playerOrder[count($this->a_playerOrder)-1] = $i_playerId;
		return array(TRUE, "Player added");
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

		create_row_if_not_existing(array_merge($a_whereVals, $a_createVals), TRUE);
		db_query("UPDATE {$maindb}.games SET {$s_updateClause} WHERE {$s_whereClause}", array_merge($a_updateVals, $a_whereVals), TRUE);
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
		
		// check if already loaded
		if (isset($a_staticGames[$s_roomCode]))
		{
			return $a_staticGames[$s_roomCode];
		}
		
		// load the game
		$o_game = null;
		$a_games = db_query("SELECT * FROM `{$maindb}`.`games` WHERE `roomCode`='[roomCode]'", array("roomCode"=>$s_roomCode));
		if (is_array($a_games) && count($a_games) > 0) {
			$o_game = new game($a_games[0]['name'], $a_games[0]['player1Id']);
			$o_game->i_id = $a_games[0]['id'];
			$o_game->s_roomCode = $a_games[0]['roomCode'];
			$o_game->a_playerIds = explodeIds($a_games[0]['playerIds']);
			$o_game->a_playerOrder = explodeIds($a_games[0]['playerOrder']);
			$o_game->d_startTime = self::getDateTimeFromString($a_games[0]['startTime']);
			$o_game->i_cardStartType = $a_games[0]['cardStartType'];
			$o_game->i_drawTimerLen = $a_games[0]['drawTimerLen'];
			$o_game->i_textTimerLen = $a_games[0]['textTimerLen'];
			$o_game->d_turnStart = self::getDateTimeFromString($a_games[0]['turnStart']);
			$o_game->i_currentTurn = $a_games[0]['currentTurn'];

			$a_staticGames[$i_playerId] = $o_game;
		}
		return $o_game;
	}
}
?>
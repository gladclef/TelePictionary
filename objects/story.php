<?php

require_once(dirname(__FILE__)."/../resources/db_query.php");
require_once(dirname(__FILE__)."/../resources/globals.php");

class player
{
	public $i_id = 0;
	public $i_playerId = 0;
	public $s_name = '';
	public $s_roomCode = '';
	public $a_cards = null;
	public $a_cardIds = array();
	public $i_revealCount = 0;
	private static $a_staticStories = array();

	function __construct($s_roomCode, $i_playerId) {
		$this->s_roomCode = $s_roomCode;
		$this->i_playerId = $i_playerId;
	}

	/*********************************************************************
	 *                     P U B L I C   F U N C T I O N S               *
	 *********************************************************************/
	public function getId() {
		return $this->i_id;
	}
	public function getStartingPlayer() {
		return player::loadById($this->i_playerId);
	}
	public function getName() {
		reutrn $this->s_name;
	}
	public function getRoomCode() {
		return $this->s_roomCode;
	}
	public function getGame() {
		if ($this->roomCode == null || $this->roomCode == '')
		{
			return null;
		}
		return game::loadByRoomCode($this->roomCode);
	}
	public function getCardIds() {
		return $this->a_cardIds;
	}
	public function getCards() {
		$this->a_cards = array();
		for (var $i = 0; $i < count($a_cardIds); $i++)
		{
			$this->a_cards[$i] = card::loadById($a_cardIds[$i]);
		}
		return $this->a_cards;
	}
	public function getRevealCount() {
		$this->getCards();
		$i_ret = 0;
		for (var $i = 0; $i < count($a_cards); $i++)
		{
			$a_revealStatus = $a_cards[$i]->getRevealStatus();
			if ($a_revealStatus[0] == 1)
			{
				$i_ret++;
			}
		}
	}
	public function getRevealStatus() {
		$o_game = $this->getGame();
		if ($o_game == null)
		{
			return array(-1, 'Error: can\'t find game');
		}
		if (count($a_cards) < count($o_game->getPlayers()))
		{
			return array(0, 'Waiting for more submissions');
		}

		$i_revealCount = $this->getRevealCount();
		if ($i_revealCount == 0) {
			return array(1, 'Ready to reveal');
		}

		if ($i_revealCount < count($o_game->getPlayers()))
		{
			return array(2, 'Revealing');
		}

		if ($i_revealCount == count($o_game->getPlayers()))
		{
			return array(3, 'Revealed');
		}

		return array(-1, 'Error: unknown story reveal state');
	}
	public function toJsonObj() {
		return array(
			"roomCode" => $this->s_roomCode,
			"name" => $this->s_name,
			"playerId" => $this->i_playerId,
			"cardIds" => implodeIds($this->a_cardIds),
		);
	}

	/*******************************************************
	 *           S T A T I C   F U N C T I O N S           *
	 ******************************************************/
	
	/**
	 * @return             object  either a story object or NULL
	 */
	public static function loadById($i_storyId) {
		global $maindb;
		
		// check if already loaded
		if (isset($a_staticStories[$i_storyId]))
		{
			return $a_staticStories[$i_storyId];
		}
		
		// load the story
		$o_story = null;
		$a_stories = db_query("SELECT * FROM `{$maindb}`.`stories` WHERE `id`='[id]'", array("id"=>$i_storyId));
		if (is_array($a_stories) && count($a_stories) > 0) {
			$o_story = new story($a_stories[0]['roomCode'], $a_stories[0]['playerId']);
			$o_story->i_id = intval($a_stories[0]['id']);
			$o_story->s_name = $a_stories[0]['name'];
			$o_story->a_cardIds = explodeIds($a_stories[0]['cardIds']);

			$a_staticStories[$i_storyId] = $o_story;
		}
		return $o_story;
	}
}
?>
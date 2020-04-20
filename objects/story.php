<?php

require_once(dirname(__FILE__)."/../resources/db_query.php");
require_once(dirname(__FILE__)."/../resources/globals.php");

class story
{
	public $i_id = 0;
	public $i_playerId = 0;
	public $s_name = '';
	public $s_roomCode = '';
	public $a_cardIds = array();
	public $i_revealCount = 0;

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
		return $this->s_name;
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
		$a_cards = array();
		for ($i = 0; $i < count($this->a_cardIds); $i++)
		{
			$a_cards[$i] = card::loadById($this->a_cardIds[$i]);
		}
		return $a_cards;
	}
	public function getCard($i_currentTurn) {
		$a_cards = $this->getCards();
		$o_card = isset($a_cards[$i_currentTurn]) ? $a_cards[$i_currentTurn] : null;
		if ($o_card === null)
		{
			$o_player = $this->getStartingPlayer();
			$o_game = $o_player->getGame();
			$o_playerInOrder = $o_game->getPlayerInOrder($this->i_playerId, $i_currentTurn);
			$i_cardStartType = $o_game->getCardStartType();
			$o_card = new card($this->s_roomCode, $this->i_id, $o_playerInOrder->getId());
			$o_card->setType(($i_cardStartType + $i_currentTurn) % 2);
			$o_card->save();
			$this->a_cardIds[$i_currentTurn] = $o_card->getId();
			$this->save();
		}
		return $o_card;
	}
	public function getRevealCount() {
		$a_cards = $this->getCards();
		$i_ret = 0;
		for ($i = 0; $i < count($a_cards); $i++)
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
		if (count($this->a_cardIds) < count($o_game->getPlayers()))
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
			"startingPlayerName" => $this->getStartingPlayer()->getName()
		);
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
			"playerId" => $this->i_playerId,
			"cardIds" => implodeIds($this->a_cardIds)
		);
		$s_updateClause = array_to_update_clause($a_updateVals);

		// insert this story as a new row if necessary
		$a_stories = db_query("SELECT * FROM `{$maindb}`.`stories` WHERE `id`='[id]'", array("id"=>$this->i_id));
		if (!is_array($a_stories) || count($a_stories) == 0) {
			// insert a new row for this story
			$s_setClause = array_to_set_clause($a_updateVals);
			db_query("INSERT INTO `{$maindb}`.`stories` {$s_setClause}", $a_updateVals);

			// update the story id with the mysql id
			$s_whereClause2 = array_to_where_clause($a_updateVals);
			$a_stories2 = db_query("SELECT `id` FROM `{$maindb}`.`stories` WHERE {$s_whereClause2} ORDER BY `id` DESC", $a_updateVals);
			if (is_array($a_stories2) && count($a_stories2) > 0) {
				$this->i_id = intval($a_stories2[0]['id']);
				$a_whereVals['id'] = $this->i_id;
				$s_whereClause = array_to_where_clause($a_whereVals);
			}

			$b_isNew = TRUE;
		}

		// update the story
		db_query("UPDATE `{$maindb}`.`stories` SET {$s_updateClause} WHERE {$s_whereClause}", array_merge($a_updateVals, $a_whereVals));

		return $b_isNew;
	}

	/*******************************************************
	 *           S T A T I C   F U N C T I O N S           *
	 ******************************************************/
	
	/**
	 * @return             object  either a story object or NULL
	 */
	public static function loadById($i_storyId) {
		global $maindb;
		global $story_staticStories;
		
		// check if already loaded
		if (!isset($story_staticStories))
			$story_staticStories = [];
		if (isset($story_staticStories[$i_storyId]))
		{
			return $story_staticStories[$i_storyId];
		}
		
		// load the story
		$o_story = null;
		$a_stories = db_query("SELECT * FROM `{$maindb}`.`stories` WHERE `id`='[id]'", array("id"=>$i_storyId));
		if (is_array($a_stories) && count($a_stories) > 0) {
			$o_story = new story($a_stories[0]['roomCode'], intval($a_stories[0]['playerId']));
			$o_story->i_id = intval($a_stories[0]['id']);
			$o_story->s_name = $a_stories[0]['name'];
			$o_story->a_cardIds = explodeIds($a_stories[0]['cardIds'], 'intval');

			$story_staticStories[$i_storyId] = $o_story;
		}
		return $o_story;
	}
}
?>
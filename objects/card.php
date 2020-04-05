<?php

require_once(dirname(__FILE__)."/../resources/db_query.php");
require_once(dirname(__FILE__)."/../resources/globals.php");

class card
{
	public $i_id = 0;
	public $s_roomCode = '';
	public $o_story = null;
	public $i_storyId = 0;
	public $i_authorId = 0;
	public $i_type = 0;
	public $s_text = '';
	public $i_imageId = 0;
	public $s_imageAlias = '';
	public $i_isRevealed = 0;
	private static $a_staticCards = array();

	function __construct($s_roomCode, $i_storyId, $i_authorId) {
		$this->s_roomCode = $s_roomCode;
		$this->i_storyId = $i_storyId;
		$this->i_authorId = $i_authorId;
	}

	/*********************************************************************
	 *                     P U B L I C   F U N C T I O N S               *
	 *********************************************************************/
	public function getId() {
		return $this->i_id;
	}
	public function getAuthor() {
		return player::loadById($this->i_authorId);
	}
	public function getStory() {
		return story::loadById($this->i_storyId);
	}
	public function getStoryId() {
		reutrn $this->i_storyId;
	}
	public function getRoomCode() {
		return $this->s_roomCode;
	}
	public function getType() {
		switch ($this->i_type)
		{
			case 0:
				return array(0, 'Sentence');
			case 1:
				return array(1, 'Image');
		}
		return array(-1, 'Error: uknown card type');
	}
	public function isSentence() {
		return $this->getType()[0] == 'Sentence';
	}
	public function isImage() {
		return $this->getType()[0] == 'Image';
	}
	public function getSentence() {
		return $this->s_text;
	}
	public function getImageId() {
		return $this->i_imageId;
	}
	public function getImageAlias() {
		if ($this->s_imageAlias == null || $this->s_imageAlias == '')
		{
			$a_images = db_query("SELECT * FROM `{$maindb}`.`images` WHERE `id`='[id]'", array("id"=>$this->i_imageId));
			if (is_array($a_images) && count($a_images) > 0)
			{
				$this->s_imageAlias = strval($a_images[0]['alias']) . "." . $a_images[0]['extension'];
			}
			else
			{
				return null;
			}
		}
		return $this->s_imageAlias;
	}
	public function getRevealStatus() {
		if ($this->i_isRevealed == 0)
		{
			return array(0, 'Card is not yet revealed');
		}
		if ($this->i_isRevealed == 1)
		{
			return array(1, 'Card is already revealed');
		}
		return array(-1, 'Error: uknown card reveal state');
	}
	public function toJsonObj() {
		return array(
			"roomCode" => $this->s_roomCode,
			"storyId" => $this->i_storyId,
			"authorId" => $this->i_authorId,
			"type" => $this->i_type,
			"text" => $this->s_text,
			"imageId" => $this->i_imageId,
			"isRevealed" => $this->i_isRevealed
		);
	}

	/*******************************************************
	 *           S T A T I C   F U N C T I O N S           *
	 ******************************************************/
	
	/**
	 * @return             object  either a card object or NULL
	 */
	public static function loadById($i_cardId) {
		global $maindb;
		
		// check if already loaded
		if (isset($a_staticCards[$i_cardId]))
		{
			return $a_staticCards[$i_cardId];
		}
		
		// load the card
		$o_card = null;
		$a_cards = db_query("SELECT * FROM `{$maindb}`.`cards` WHERE `id`='[id]'", array("id"=>$i_cardId));
		if (is_array($a_cards) && count($a_cards) > 0) {
			$o_card = new user($a_cards[0]['roomCode'], $a_cards[0]['storyId'], $a_cards[0]['authorId']);
			$o_card->i_id = intval($a_cards[0]['id']);
			$o_card->i_type = intval($a_cards[0]['type']);
			$o_card->s_text = $a_cards[0]['text'];
			$o_card->i_imageId = intval($a_cards[0]['imageId']);
			$o_card->i_isRevealed = intval($a_cards[0]['isRevealed']);

			$a_staticCards[$i_cardId] = $o_card;
		}
		return $o_card;
	}
}
?>
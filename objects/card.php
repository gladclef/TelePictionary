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
	public $b_isRevealed = false;

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
		return $this->i_storyId;
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
	public function getImage() {
		return image::loadById($this->i_imageId);
	}
	public function getImageURL() {
		$o_image = $this->getImage();
		if ($o_image === null)
			return "";
		return $o_image->getURL();
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
		if ($this->b_isRevealed)
		{
			return array(1, 'Card is already revealed');
		}
		else
		{
			return array(0, 'Card is not yet revealed');
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
			'imageURL' => $this->getImageURL(),
			"isRevealed" => $this->b_isRevealed
		);
	}

	public function setType($i_type)
	{
		$this->i_type = $i_type;
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
        $o_image->storyId = $this->i_storyId;
        $o_image->i_playerId = $this->i_authorId;
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
			"roomCode" => $this->s_roomCode,
			"storyId" => $this->i_storyId,
			"authorId" => $this->i_authorId,
			"type" => $this->i_type,
			"text" => $this->s_text,
			"imageId" => $this->i_imageId,
			"isRevealed" => ($this->b_isRevealed ? 1 : 0)
		);
		$s_updateClause = array_to_update_clause($a_updateVals);

		// insert this card as a new row if necessary
		$a_cards = db_query("SELECT * FROM `{$maindb}`.`cards` WHERE `id`='[id]'", array("id"=>$this->i_id));
		if (!is_array($a_cards) || count($a_cards) == 0) {
			// insert a new row for this card
			$s_setClause = array_to_set_clause($a_updateVals);
			db_query("INSERT INTO `{$maindb}`.`cards` {$s_setClause}", $a_updateVals);

			// update the card id with the mysql id
			$s_whereClause2 = array_to_where_clause($a_updateVals);
			$a_cards2 = db_query("SELECT `id` FROM `{$maindb}`.`cards` WHERE {$s_whereClause2} ORDER BY `id` DESC", $a_updateVals);
			if (is_array($a_cards2) && count($a_cards2) > 0) {
				$this->i_id = intval($a_cards2[0]['id']);
				$a_whereVals['id'] = $this->i_id;
				$s_whereClause = array_to_where_clause($a_whereVals);
			}

			$b_isNew = TRUE;
		}

		// update the card
		db_query("UPDATE `{$maindb}`.`cards` SET {$s_updateClause} WHERE {$s_whereClause}", array_merge($a_updateVals, $a_whereVals));

		return $b_isNew;
	}

	/*******************************************************
	 *           S T A T I C   F U N C T I O N S           *
	 ******************************************************/
	
	/**
	 * @return             object  either a card object or NULL
	 */
	public static function loadById($i_cardId) {
		global $maindb;
		global $card_staticCards;
		
		// check if already loaded
		if (!isset($card_staticCards))
			$card_staticCards = [];
		if (isset($card_staticCards[$i_cardId]))
		{
			return $card_staticCards[$i_cardId];
		}
		
		// load the card
		$o_card = null;
		$a_cards = db_query("SELECT * FROM `{$maindb}`.`cards` WHERE `id`='[id]'", array("id"=>$i_cardId));
		if (is_array($a_cards) && count($a_cards) > 0) {
			$o_card = new card($a_cards[0]['roomCode'], intval($a_cards[0]['storyId']), intval($a_cards[0]['authorId']));
			$o_card->i_id = intval($a_cards[0]['id']);
			$o_card->i_type = intval($a_cards[0]['type']);
			$o_card->s_text = $a_cards[0]['text'];
			$o_card->i_imageId = intval($a_cards[0]['imageId']);
			$o_card->b_isRevealed = (intval($a_cards[0]['isRevealed']) == 0) ? false : true;

			$card_staticCards[$i_cardId] = $o_card;
		}
		return $o_card;
	}
}
?>
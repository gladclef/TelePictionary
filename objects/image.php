<?php

require_once(dirname(__FILE__)."/../resources/db_query.php");
require_once(dirname(__FILE__)."/../resources/globals.php");

class image
{
	public $i_id = 0;
	public $i_alias = -1;
	public $s_extension = '';
	public $s_roomCode = '';
	public $i_storyId = -1;
	public $i_playerId = -1;

	function __construct($b_isPlayerImage, $i_relevantId) {
		if ($b_isPlayerImage) {
			$this->i_playerId = $i_relevantId;
		} else {
			$this->i_storyId = $i_relevantId;
		}
	}

	/*********************************************************************
	 *                     P U B L I C   F U N C T I O N S               *
	 *********************************************************************/
	public function getId() {
		return $this->i_id;
	}
	public function getAlias() {
		return $this->i_alias;
	}
	public function getExtension() {
		return $this->s_extension;
	}
	public function getRoomCode() {
		return $this->s_roomCode;
	}
	public function getStory() {
		return story::loadById($this->i_storyId);
	}
	public function getStoryId() {
		return $this->i_storyId;
	}
	public function getPlayer() {
		return player::loadById($this->i_playerId);
	}
	public function getPlayerId() {
		return $this->i_playerId;
	}
	public function getURL() {
		if ($this->i_alias < 0) {
			return "";
		}
		return "images/{$this->i_alias}.{$this->s_extension}";
	}
	public function getGame() {
		$o_story = $this->getPlayer();
		if ($o_story === null)
			return null;
		return game::loadByRoomCode($o_story->getRoomCode());
	}

	public function setImage($i_alias, $s_extension)
	{
        $this->i_alias = $i_alias;
        $this->s_extension = $s_extension;
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
			"alias" => $this->i_alias,
			"extension" => $this->s_extension,
			"roomCode" => $this->s_roomCode,
			"storyId" => $this->i_storyId,
			"playerId" => $this->i_playerId
		);
		$s_updateClause = array_to_update_clause($a_updateVals);

		// insert this image as a new row if necessary
		$a_images = db_query("SELECT * FROM `{$maindb}`.`images` WHERE `id`='[id]'", array("id"=>$this->i_id));
		if (!is_array($a_images) || count($a_images) == 0) {
			// insert a new row for this image
			$s_setClause = array_to_set_clause($a_updateVals);
			db_query("INSERT INTO `{$maindb}`.`images` {$s_setClause}", $a_updateVals);

			// update the image id with the mysql id
			$s_whereClause2 = array_to_where_clause($a_updateVals);
			$a_images2 = db_query("SELECT `id` FROM `{$maindb}`.`images` WHERE {$s_whereClause2} ORDER BY `id` DESC", $a_updateVals);
			if (is_array($a_images2) && count($a_images2) > 0) {
				$this->i_id = intval($a_images2[0]['id']);
				$a_whereVals['id'] = $this->i_id;
				$s_whereClause = array_to_where_clause($a_whereVals);
			}

			$b_isNew = TRUE;
		}

		// update the image
		db_query("UPDATE `{$maindb}`.`images` SET {$s_updateClause} WHERE {$s_whereClause}", array_merge($a_updateVals, $a_whereVals));

		return $b_isNew;
	}

	/*******************************************************
	 *           S T A T I C   F U N C T I O N S           *
	 ******************************************************/
	
	/**
	 * @return             object  either an image object or NULL
	 */
	public static function loadById($i_imageId) {
		global $maindb;
		global $image_staticImages;
		
		// check if already loaded
		if (!isset($image_staticImages))
			$image_staticImages = [];
		if (isset($image_staticImages[$i_imageId]))
		{
			return $image_staticImages[$i_imageId];
		}
		
		// load the image
		$o_image = null;
		$a_images = db_query("SELECT * FROM `{$maindb}`.`images` WHERE `id`='[id]'", array("id"=>$i_imageId));
		if (is_array($a_images) && count($a_images) > 0) {
			$i_storyId = intval($a_images[0]['storyId']);
			$i_playerId = intval($a_images[0]['playerId']);
			$b_isPlayerImage = ($i_storyId > 0) ? FALSE : TRUE;

			$o_image = new image($b_isPlayerImage, $b_isPlayerImage ? $i_playerId : $i_storyId);
			$o_image->i_id = intval($a_images[0]['id']);
			$o_image->i_alias = intval($a_images[0]['alias']);
			$o_image->s_extension = $a_images[0]['extension'];
			$o_image->s_roomCode = $a_images[0]['roomCode'];
			$o_image->i_storyId = intval($a_images[0]['storyId']);
			$o_image->i_playerId = intval($a_images[0]['playerId']);

			$image_staticImages[$i_imageId] = $o_image;
		}
		return $o_image;
	}
}
?>
<?php

// Copyright (c) 2006-2007 Alex King. All rights reserved.
// http://alexking.org/projects/php-tag-engine
//
// Released under the LGPL license
// http://www.opensource.org/licenses/lgpl-license.php
//
// **********************************************************************
// This program is distributed in the hope that it will be useful, but
// WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. 
// **********************************************************************

/**
 * This file contains the PHP Tag Engine class definition.
 * 
 * @package phptagengine
 */

if (__FILE__ == basename($_SERVER['SCRIPT_NAME'])) { die(); }

/**
 * The phptagengine class, all the good stuff happens here.
 * 
 * @package phptagengine
 */
class phptagengine {
	/**
	 * set to global $ADOdb instance
	 * @var mixed
	 */
	var $db;
	/**
	 * Column name escape string for the database being used. Checks for: mysql, postgres7, mssql
	 *
	 * @var string
	 */
	var $db_col_escape_char;
	/**
	 * Name of the tags table in the database
	 *
	 * @var string
	 */
	var $table_tags;
	/**
	 * Name of the tag names table in the database
	 *
	 * @var string
	 */
	var $table_tag_names;
	/**
	 * Name of the users table in the database
	 *
	 * @var string
	 */
	var $table_users;
	/**
	 * The display name column (normally name or username) in the users table in the database
	 *
	 * @var string
	 */
	var $table_users_name;
	/**
	 * URL to the PHP Tag Engine folder, include the trailing slash
	 *
	 * @var string
	 */
	var $base_url;
	/**
	 * URL of the file handling AJAX requests
	 *
	 * @var string
	 */
	var $ajax_handler;
	/**
	 * Name of language file to include - languages/(language).inc.php
	 *
	 * @var string
	 */
	var $language;
	/**
	 * Character set
	 *
	 * @var string
	 */
	var $charset;
	/**
	 * Array of strings for localization
	 *
	 * @var array
	 */
	var $strings;
	/**
	 * Value to be used if no type is passed in, useful if you only have one 
	 * type of object you are tagging and just need a default value
	 *
	 * @var string
	 */
	var $default_type;
	/**
	 * Value to be used if no user is passed in, useful if you are not tracking 
	 * tags by user and just need a default value
	 *
	 * @var string
	 */
	var $default_user;
	/**
	 * URL for browsing items by tag, includes token replacement for tag, type
	 * 
	 * example: http://example.com/index.php?view=tag&tag=<tag>&type=<type>
	 *
	 * @var string
	 */
	var $tag_browse_url;
	/**
	 * Show an X next to each tag to allow removal of that tag 
	 *
	 * @var boolean
	 */
	var $show_remove_links;
	/**
	 * Show text or image?
	 * 
	 * example: 'text' - uses @link phptagengine::$strings
	 * example: 'image'
	 *
	 * @var string
	 */
	var $edit_button_display;
	/**
	 * URL of the image for the edit button
	 *
	 * @var string
	 */
	var $edit_button_image_url;
	/**
	 * Show text or image?
	 * 
	 * example: 'text' - uses @link phptagengine::$strings
	 * example: 'image'
	 *
	 * @var string
	 */
	var $delete_button_display;
	/**
	 * URL of the image for the delete button
	 *
	 * @var string
	 */
	var $delete_button_image_url;
	/**
	 * Stores arrays of tags for items, use as a cache to reduce queries 
	 *
	 * @var array
	 */
	var $item_tags_cache;
	/**
	 * Enable Yahoo! Auto-Complete
	 *
	 * @var boolean
	 */
	var $yac;
	/**
	 * List of Yahoo! UI Pattern files to be included, in case Yahoo! UI Patterns are already in use elsewhere and we don't want to duplicate their inclusion. The array should contain filenames in the 'yui' directory.
	 * 
	 * Note: order matters in this list.
	 *
	 * @var array
	 */
	var $yac_files;
	/**
	 * PHP Tag Engine Version
	 *
	 * @var string
	 */
	var $version;
	/**
	 * Show or hide error messages
	 *
	 * @var boolean
	 */
	var $debug;
	
	/**
	 * Initializes the class
	 *
	 * @return phptagengine
	 */
	function phptagengine() {
		$this->base_url = 'http://example.com/';
		$this->ajax_handler = 'http://example.com/ajax.php';
		$this->language = 'english';
		$this->charset = 'UTF-8';
		$this->db_type = 'mysql';
		$this->strings = array();
		$this->tag_browse_url = 'http://example.com/index.php?view=tags&tag=<tag>&type=<type>';
		$this->default_type = '';
		$this->default_user = 1;
		$this->edit_button_display = 'text';
		$this->delete_button_display = 'text';
		$this->yac = true;
		$this->yac_files = array(
			'yahoo-dom-event.js'
			,'autocomplete.js'
		);
		$this->version = '1.01';
		$this->debug = false;
	}
	
	/**
	 * Sets the character used for escaping column names for the database type in use
	 */
	function set_db_col_escape_char() {
		switch ($this->db->databaseType) {
			case 'mysql':
				$this->db_col_escape_char = '`';
				break;
			case 'postgres7':
			case 'mssql':
				$this->db_col_escape_char = '"';
				break;
		}
	}

	/**
	 * Sets the type or user property to the default value if the value is 
	 * null, could be extended in the future
	 * @param string $prop expected 'type' or 'user'
	 * @param mixed $value if this is null, we set the default
	 */
	function default_value($prop, $value) {
		if ($value == null && in_array($prop, array('type', 'user'))) {
			eval('$value = $this->default_'.$prop.';');
		}
		return $value;
	}
	
	/**
	 * Set a tag to lowercase and remove spaces, could be extended in the future
	 * @param mixed $value the value to be normalized
	 * @param string $type the type of value, used for the switch statement
	 * @return mixed
	 */
	function normalize($value, $type = 'tag') {
		switch ($type) {
			case 'tag':
				$value = preg_replace('|[^a-z0-9_.\-@#$%*!&]|i', '', strtolower($value));
				break;
		}
		return $value;
	}

	/**
	 * Does a tag already exist
	 *
	 * @uses phptagengine::get_tag_id()
	 * 
	 * @param string $tag
	 * @return boolean
	 */
	function tag_exists($tag) {
		if ($this->get_tag_id($tag) != false) {
			return true;
		}
		else {
			return false;
		}
	}

	/**
	 * Creates a new tag (noramlized) in the database, returns the ID of the 
	 * created tag or false
	 *
	 * @uses phptagengine::normalize() to normalize the $tag
	 * @uses phptagengine::tag_exists() to see if the tag needs creating
	 * 
	 * @param string $tag
	 * @return mixed
	 */
	function create_tag($tag = null) {
		if ($tag == null) {
			return false;
		}
		$tag = $this->normalize($tag);
		if (strlen($tag) < 1) {
			return false;
		}
		$test = $this->get_tag_id($tag);
		if ($test != false) {
			return $test;
		}
		$result = $this->db->Execute("
			INSERT
			INTO $this->table_tag_names
			( ".$this->db_col_escape_char."name".$this->db_col_escape_char."
			)
			VALUES 
			( ".$this->db->qstr($tag)."			
			)
		") or die(throw_error($this->db->ErrorMsg(), __FILE__, __LINE__));
		
		if ($result) {
			$id = false;
			if (strstr($this->db->databaseType, 'postgres')) {
				$id = $this->db->GetOne("
					SELECT CURRVAL('".$this->table_tag_names."_id_seq') as id
				") or die(throw_error($this->db->ErrorMsg(), __FILE__, __LINE__));
			}
			else {
				$id = $this->db->Insert_ID();
			}
			return $id;
		}
		
		return false;
	}

	/**
	 * Adds a tag to an item, creates the tag if needed
	 *
	 * @uses phptagengine::default_value() to set a default value for $type and $user if needed
	 * @uses phptagengine::create_tag() to create a new tag or get the id of the existing tag
	 * 
	 * @param string $user user creating the tag
	 * @param string $item item being tagged
	 * @param string $tag tag being used
	 * @param string $type type of item being tagged
	 * @return boolean
	 */
	function add_tag($user = null, $item = null, $tag = null, $type = null) {
		if ($item == null || $tag == null) {
			return false;
		}
		$type = $this->default_value('type', $type);
		$user = $this->default_value('user', $user);
		$tag_id = $this->create_tag($tag);
		if ($tag_id == false) {
			return false;
		}
		if ($this->item_tag_exists($user, $item, $tag, $type)) {
			return true;
		}
		else {
			$this->set_db_col_escape_char();
			$result = $this->db->Execute("
				INSERT
				INTO $this->table_tags
				( ".$this->db_col_escape_char."user".$this->db_col_escape_char."
				, ".$this->db_col_escape_char."item".$this->db_col_escape_char."
				, ".$this->db_col_escape_char."type".$this->db_col_escape_char."
				, ".$this->db_col_escape_char."tag".$this->db_col_escape_char."
				, ".$this->db_col_escape_char."date".$this->db_col_escape_char."
				)
				VALUES
				( ".$this->db->qstr($user)."
				, ".$this->db->qstr($item)."
				, ".$this->db->qstr($type)."
				, ".$this->db->qstr($tag_id)."
				, ".$this->db->DBDate(date("Y-m-d H:i:s"))."
				)
			") or die(throw_error($this->db->ErrorMsg(), __FILE__, __LINE__));
			if ($result) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Save item tags
	 *
	 * @param string $item
	 * @param string $tags
	 * @param string $type
	 * @param string $user
	 * 
	 * @return array key success is 'y/n', key tags is an arra of the item's tags
	 */
	function save_tags($item, $tags, $type = null, $user = null) {
		$type = $this->default_value('type', $type);
		$user = $this->default_value('user', $user);
		$old_tags = $this->get_tags($item, $type, $user);
		$new_tags = array();
		$success = 'y';
		if ($tags != null && $tags != '') {
			$tags = explode(' ', $tags);
			$tags = array_unique($tags);
			natcasesort($tags);
			reset($tags);
			if (count($tags) > 0) {
				foreach ($tags as $tag) {
					if ($tag != '') {
						if ($this->add_tag($user, $item, $tag, $type)) {
							$new_tags[] = $this->normalize($tag);
						}
						else {
							$success = 'n';
						}
					}
				}
				if (count($new_tags) > 0) {
					$tags = implode(' ', $new_tags);
				}
			}
		}
		if (count($old_tags) > 0) {
			foreach ($old_tags as $id => $tag) {
				if (!in_array($tag, $new_tags)) {
					if (!$this->remove_tag_by_id(str_replace('id_', '', $id))) {
						$success = 'n';
					}
				}
			}
		}
		$result = array(
			'success' => $success
			, 'tags' => $new_tags
		);
		return $result;
	}
	

	/**
	 * Removes a tag
	 * 
	 * @uses phptagengine::get_item_tag_id()
	 * @uses phptagengine::remove_tag_by_id()
	 *
	 * @param string $item
	 * @param string $tag
	 * @param string $user
	 * @param string $type
	 * @return boolean
	 */
	function remove_tag($item = null, $tag = null, $user = null, $type = null) {
		$id = $this->get_item_tag_id($user, $item, $tag, $type);
		return $this->remove_tag_by_id($id);
	}

	/**
	 * Removes a tag by id
	 * 
	 * @param integer $id
	 * @return boolean
	 */
	function remove_tag_by_id($id) {
		if ($id == null) {
			return false;
		}
		$result = $this->db->Execute("
			DELETE
			FROM $this->table_tags
			WHERE id = ".$this->db->qstr($id)."
		") or die(throw_error($this->db->ErrorMsg(), __FILE__, __LINE__));
		if ($result) {
			return true;
		}
		return false;
	}
	
	/**
	 * Gets the tags for an item
	 *
	 * @param string $item
	 * @param string $type
	 * @param string $user
	 * @param boolean $use_cache
	 * @return array
	 */
	function get_tags($item = null, $type = null, $user = null, $use_cache = false) {
		if ($item == null) {
			return false;
		}
		$tags = array();
		if ($use_cache) {
			if (isset($this->item_tags_cache['item_'.$item])) {
				$tags = $this->item_tags_cache['item_'.$item];
			}
			return $tags;
		}
		$where = '';
		if ($user != null) {
			$where .= ' AND t.user = '.$this->db->qstr($user);
		}
		if ($item != null) {
			$where .= ' AND t.item = '.$this->db->qstr($item);
		}
		if ($type != null) {
			$where .= ' AND t.type = '.$this->db->qstr($type);
		}
		if ($where == '') {
			return false;
		}
		$result = $this->db->Execute("
			SELECT t.id AS ID
			, tn.name AS NAME
			FROM $this->table_tags t
			JOIN $this->table_tag_names tn
			ON t.tag = tn.id
			WHERE 1 = 1
			$where
			ORDER BY tn.name
		") or die(throw_error($this->db->ErrorMsg(), __FILE__, __LINE__));
		if ($result && $result->RowCount() > 0) {
			while ($data = $result->FetchNextObject()) {
				$tags['id_'.$data->ID] = $data->NAME;
			}
		}
		return $tags;
	}
	
	/**
	 * Completely removes a tag from the system (from @link phptagengine::$table_tags and @link phptagengine::$table_tag_names)
	 *
	 * @param string $tag
	 * @return boolean
	 */
	function delete_tag($tag = null) {
		if (is_null($tag) || strstr($tag, ' ')) {
			return false;
		}
		$tag_id = $this->get_tag_id($tag);
		if ($tag_id == false) {
			return false;
		}
		$result = $this->db->Execute("
			DELETE
			FROM $this->table_tags
			WHERE tag = '$tag_id'
		") or die(throw_error($this->db->ErrorMsg(), __FILE__, __LINE__));
		if (!$result) {
			return false;
		}
		$result = $this->db->Execute("
			DELETE
			FROM $this->table_tag_names
			WHERE id = '$tag_id'
		") or die(throw_error($this->db->ErrorMsg(), __FILE__, __LINE__));
		if (!$result) {
			return false;
		}
		return true;
	}
	
	/**
	 * Changes the name of a tag, or consolidates two existing tags
	 *
	 * @param string $old_tag
	 * @param string $new_tag
	 * @return boolean
	 */
	function edit_tag($old_tag = null, $new_tag = null) {
		if (is_null($old_tag) || is_null($new_tag) || strstr($new_tag, ' ')) {
			return false;
		}
		if ($old_tag == $new_tag) {
			return true;
		}
		if ($this->tag_exists($new_tag)) {
// move all tags to existing tag
			$old_tag_id = $this->get_tag_id($old_tag);
			$new_tag_id = $this->get_tag_id($new_tag);
			$result = $this->db->Execute("
				UPDATE $this->table_tags
				SET tag = '$new_tag_id'
				WHERE tag = '$old_tag_id'
			") or die(throw_error($this->db->ErrorMsg(), __FILE__, __LINE__));
			if (!$result) {
				return false;
			}
// check for dupes
			$result = $this->db->Execute("
				SELECT *
				FROM $this->table_tags
				WHERE tag = '$new_tag_id'
			") or die(throw_error($this->db->ErrorMsg(), __FILE__, __LINE__));
			if (!$result) {
				return false;
			}
			else if ($result->RowCount() > 0) {
				$tags = array();
				$ids_to_delete = array();
				while ($data = $result->FetchNextObject()) {
					$key = $data->TAG.$data->ITEM.$data->TYPE.$data->USER;
					if (!in_array($key, $tags)) {
						$tags[] = $key;
					}
					else {
						$ids_to_delete[] = $data->ID;
					}
				}
// remove dupes
				if (count($ids_to_delete) > 0) {
					$result = $this->db->Execute("
						DELETE
						FROM $this->table_tags
						WHERE id IN (".implode(',', $ids_to_delete).")
					") or die(throw_error($this->db->ErrorMsg(), __FILE__, __LINE__));
					if (!$result) {
						return false;
					}
				}
			}
		}
		else {
			$result = $this->db->Execute("
				UPDATE $this->table_tag_names
				SET name = ".$this->db->qstr($new_tag)."
				WHERE name = ".$this->db->qstr($old_tag)."
			") or die(throw_error($this->db->ErrorMsg(), __FILE__, __LINE__));
			if (!$result) {
				return false;
			}
		}
		return true;
	}
	
	/**
	 * Populate cache with items and tags to reduce queries
	 *
	 * @param array $items
	 * @param mixed $user
	 * @param mixed $type
	 */
	function populate_cache($items = array(), $user = null, $type = null) {
		if (count($items) == 0) {
			return;
		}
		$where = '';
		if ($user != null) {
			$where .= ' AND t.user = '.$this->db->qstr($user);
		}
		if ($type != null) {
			$where .= ' AND t.type = '.$this->db->qstr($type);
		}
		$where .= ' AND ( ';
		$i = 0;
		foreach ($items as $item) {
			if ($i > 0) {
				$where .= ' OR ';
			}
			$where .= ' t.item = '.$this->db->qstr($item);
			$i++;
		}
		$where .= ' )';
		$result = $this->db->Execute("
			SELECT t.item AS ITEM
			, t.id AS ID
			, tn.name AS NAME
			FROM $this->table_tags t
			JOIN $this->table_tag_names tn
			ON t.tag = tn.id
			WHERE 1 = 1
			$where
			ORDER BY t.item, tn.name
		") or die(throw_error($this->db->ErrorMsg(), __FILE__, __LINE__));
		if ($result) {
			while ($data = $result->FetchNextObject()) {
				if (!isset($this->item_tags_cache['item_'.$data->ITEM])) {
					$this->item_tags_cache['item_'.$data->ITEM] = array();
				}
				$this->item_tags_cache['item_'.$data->ITEM]['id_'.$data->ID] = $data->NAME;
			}
		}
	}
	
	/**
	 * Gets the ID of a tag, returns false if the tag does not exist
	 *
	 * @param string $name
	 * @return mixed
	 */
	function get_tag_id($name = null) {
		if ($name == null) {
			return false;
		}
		$name = $this->normalize($name);
		$result = $this->db->Execute("
			SELECT id AS ID
			FROM $this->table_tag_names
			WHERE name = ".$this->db->qstr($name)."
		") or die(throw_error($this->db->ErrorMsg(), __FILE__, __LINE__));
		if ($result && $result->RowCount() == 1) {
			while ($data = $result->FetchNextObject()) {
				return $data->ID;
			}
		}
		return false;
	}

	/**
	 * Checks if an item already has a certain tag
	 * 
	 * @uses phptagengine::get_item_tag_id()
	 *
	 * @param mixed $user
	 * @param mixed $item
	 * @param mixed $tag
	 * @param mixed $type
	 * @return boolean
	 */
	function item_tag_exists($user = null, $item = null, $tag = null, $type = null) {
		if (!$this->get_item_tag_id($user, $item, $tag, $type)) {
			return false;
		}
		else {
			return true;
		}
	}

	/**
	 * Gets the id of an item tag if it exists
	 *
	 * @param mixed $user
	 * @param mixed $item
	 * @param mixed $tag
	 * @param mixed $type
	 * @return mixed id of item or false
	 */
	function get_item_tag_id($user = null, $item = null, $tag = null, $type = null) {
		if ($item == null || $tag == null) {
			return false;
		}
		$tag = $this->normalize($tag);
		$type = $this->default_value('type', $type);
		$user = $this->default_value('user', $user);
		$tags = $this->get_tags($item, $type, $user);
		if (count($tags) == 0) {
			return false;
		}
		else {
			foreach ($tags as $id => $existing_tag) {
				if ($tag == $existing_tag) {
					return str_replace('id_', '', $id);
				}
			}
		}
	}

	/**
	 * Sends an XML response - used for AJAX
	 *
	 * @param string $string
	 */
	function xml_out($string) {
		header("Content-type: text/xml");
		die('<?xml version="1.0"?>'."\n".'<response>'.$string.'</response>');
	}
	
	/**
	 * Prints the CSS and JS links needed in the HTML output
	 *
	 */
	function html_head() {
		print('
<!-- PHP Tag Engine CSS/JS - begin -->

<style type="text/css"> @import url('.$this->base_url.'phptagengine.css?version='.$this->version.'); </style>
<script type="text/javascript" src="'.$this->base_url.'phptagengine.js.php?version='.$this->version.'"></script>
		'."\n");
		if ($this->yac) {
			$tags = $this->get_all_tags();
			if (count($tags) > 0) {
				$tags = implode("','", $tags);
			}
			else {
				$tags = '';
			}
			print("
<script type=\"text/javascript\">
var tags = ['".$tags."'];
var yac_tags = new YAHOO.widget.DS_JSArray(tags);
</script>
				\n");
		}
		print('<!-- PHP Tag Engine CSS/JS - end -->'."\n");
	}
	
	/**
	 * Wrapper for htmlspecialchars, in case we need any special processing
	 *
	 * @param string $string
	 * @return string
	 */
	function html($string) {
		return htmlspecialchars($string);
	}
	
	/**
	 * Get a URL (to be used in a link) for browsing by tag
	 * 
	 * @uses phptagengine::$tag_browse_url with string replacement
	 * @uses phptagengine::token_replace()
	 *
	 * @param string $tag the tag to browse
	 * @param mixed $type the type of item, if needed
	 * @return string
	 */
	function tag_browse_url($tag = '', $type = '') {
		$type = $this->default_value('type', $type);
		return $this->token_replace($this->tag_browse_url, urlencode($tag), urlencode($type));
	}
	
	/**
	 * Returns a list of all tags
	 *
	 * @param mixed $type
	 * @return array
	 */
	function get_all_tags($type = null) {
		$type = $this->default_value('type', $type);
		$tags = array();
		$result = $this->db->Execute("
			SELECT tn.id AS ID
			, tn.name AS NAME
			FROM $this->table_tag_names tn
			JOIN $this->table_tags t
			ON tn.id = t.tag
			ORDER BY tn.name
		") or die(throw_error($this->db->ErrorMsg(), __FILE__, __LINE__));
		if ($result) {
			while ($data = $result->FetchNextObject()) {
				if (!isset($tags['id_'.$data->ID])) {
					$tags['id_'.$data->ID] = $data->NAME;
				}
			}
		}
		return $tags;
	}
	
	/**
	 * Prints an HTML list of all tags
	 *
	 * @uses phptagengine::get_all_tags()
	 * 
	 * @param string $url
	 * @param mixed $type
	 * @param mixed $onclick
	 */
	function html_tags_list($url = null, $type = null, $onclick = null, $edit_button = false, $delete_button = false) {
		if (is_null($url)) {
			$url = $this->tag_browse_url;
		}
		$tags = $this->get_all_tags($type);
		print('
			<ul class="pte_tags_list_all">
		');
		if (count($tags) == 0) {
			print('
				<li>'.$this->strings['data_none'].'</li>
			');
		}
		else {
			foreach ($tags as $id => $tag) {
				if (is_null($onclick)) {
					$onclick_str = '';
				}
				else {
					$onclick_str = ' onclick="'.$this->token_replace($onclick, $tag, $type).'"';
				}

				/**
				 * @todo in some future release, add support for editing and deleting tags
				 * /
/*
				if (!$edit_button) {
					$edit_button_str = '';
				}
				else {
					$edit_button_str = '<input type="image" src="'.$this->edit_button_image_url.'" onclick="" alt="'.$this->strings['action_edit'].'" class="pte_image_button" />';
				}
				if (!$delete_button) {
					$delete_button_str = '';
				}
				else {
					$delete_button_str = '<input type="image" src="'.$this->delete_button_image_url.'" onclick="" alt="'.$this->strings['action_delete'].'" class="pte_image_button" />';
				}
				print('
				<li id="pte_tag_'.$tag.'">'.$delete_button_str.$edit_button_str.'<a href="'.$this->token_replace($url, $tag, $type).'"'.$onclick_str.'>'.$this->html($tag).'</a></li>
				');
*/
				print('
				<li id="pte_tag_'.$tag.'"><a href="'.$this->token_replace($url, $tag, $type).'"'.$onclick_str.'>'.$this->html($tag).'</a></li>
				');
			}
		}
		print('
			</ul>
		');
	}

	/**
	 * Returns a string, replacing tokens for tag, type
	 *
	 * @param string $url
	 * @param string $tag
	 * @param string $type
	 * @return string
	 */
	function token_replace($string = null, $tag = null, $type = null) {
		if (is_null($string) || is_null($tag)) {
			return '';
		}
		$type = $this->default_value('type', $type);
		return str_replace('<tag>', $tag, str_replace('<type>', $type, $string));
	}
	
	/**
	 * Outputs the HTML display of tags for an item
	 *
	 * @param string $item
	 * @param mixed $type
	 */
	function html_item_tags($item, $type = null, $user = null, $use_cache = false, $read_only = false) {
		$user = $this->default_value('user', $user);
		$type = $this->default_value('type', $type);
		$tags = $this->get_tags($item, $type, $user, $use_cache);
		if (count($tags) > 0) {
			$tags_class = ' pte_has_tags';
		}
		else {
			$tags_class = '';
		}
		print('
			<!-- PHP Tag Engine html_item_tags for '.$item.' - begin -->
			<div id="pte_tag_form_'.$item.'" class="pte_tags_form'.$tags_class.'">
				<form id="pte_tag_edit_form_'.$item.'" action="'.$this->ajax_handler.'" onsubmit="pte.save_tags(\''.$user.'\', \''.$item.'\', this.tags.value, this.type.value); return false;">
					<label for="pte_tags_'.$item.'">'.$this->strings['label_tags'].'</label>
					<ul id="pte_tags_list_'.$item.'" class="pte_tags_list">
		');
		if (count($tags) > 0) {
			foreach ($tags as $id => $tag) {
				print('
						<li id="pte_tag_'.$item.'_'.$tag.'"><a href="'.$this->tag_browse_url($tag, $type).'">'.$this->html($tag).'</a>
				');
				if ($this->show_remove_links && !$read_only) {
					print('
							<a href="javascript:void(pte.remove_tag(\''.$item.'\', \''.$tag.'\', \''.$type.'\'));" title="'.$this->strings['action_delete'].'">'.$this->button_display('delete').'</a>
					');
				}
				print('
						</li>
				');
			}
			$edit_value = implode(' ', $tags).' ';
		}
		else {
			print('
						<li>'.$this->strings['data_none'].'</li>
			');
			$edit_value = '';
		}
		if ($read_only) {
			print('
					</ul>
				</form>
			');
		}
		else {
			print('
						<li class="pte_edit"><a href="javascript:void(pte.item_tag_view(\''.$item.'\', \'edit\'));">'.$this->button_display('edit').'</a></li>
					</ul>
					<fieldset id="pte_tags_edit_'.$item.'" class="pte_tags_edit">
						<div class="pte_edit_wrapper">
							<input type="text" id="pte_tags_edit_field_'.$item.'" class="pte_tags_edit_field" name="tags" value="'.$edit_value.'" />
			');
			if ($this->yac) {
				print('
							<div id="yac_container_'.$item.'" class="yac_list"></div>
				');
			}
			print('
						</div>
						<input type="submit" name="submit_button" value="'.$this->strings['action_save'].'" />
						<input type="button" name="cancel_button" value="'.$this->strings['action_cancel'].'" onclick="pte.item_tag_view(\''.$item.'\', \'view\')" />
						<input type="hidden" id="pte_tags_edit_type_'.$item.'" name="type" value="'.$type.'" />
					</fieldset>
					<span id="pte_tags_saving_'.$item.'" class="pte_tags_saving">'.$this->strings['action_saving'].'</span>
				</form>
			</div>
			');
			if ($this->yac) {
				print('
				<script type="text/javascript"><!--
				yac_'.$item.' = new YAHOO.widget.AutoComplete("pte_tags_edit_field_'.$item.'","yac_container_'.$item.'", yac_tags);
				yac_'.$item.'.delimChar = " ";
				yac_'.$item.'.maxResultsDisplayed = 20;
				yac_'.$item.'.queryDelay = 0;
				// --></script>
				');
			}
		}
		print('<!-- PHP Tag Engine html_item_tags for '.$item.' - end -->'."\n");
	}

	/**
	 * Returns a string with the specified character escaped with a backslash, used for JS strings, etc.
	 *
 	 * Note: There is a JS equivilent of this funciton that should be updated to mirror it.
 	 * 
	 * @param string $string the string needing escaping
	 * @param string $needle the character to be slashed
	 * 
	 * @return string
	 */
	function slash($string, $needle = '"') {
		return str_replace($needle, "\$needle", $string);
	}
	
	/**
	 * Excodes a string as XML
	 *
	 * @todo use a smarter function than just htmlentities()
	 * 
	 * @param string $string
	 * @return string
	 */
	function xml_encode($string) {
		return htmlentities($string);
	}
	
	/**
	 * Returns the proper string to be used for an action link
	 * 
	 * Note: There is a JS equivilent of this funciton that should be updated to mirror it.
	 *
	 * @param string $type
	 * @return string
	 */
	function button_display($type) {
		$display = '';
		switch ($type) {
			case 'edit':
				$case = $this->edit_button_display;
				$url = $this->edit_button_image_url;
				break;
			case 'delete':
				$case = $this->delete_button_display;
				$url = $this->delete_button_image_url;
				break;
			default:
				return $display;
		}
		switch ($case) {
			case 'text':
				if (isset($this->strings['action_'.$type.'_text_icon'])) {
					$display = $this->strings['action_'.$type.'_text_icon'];
				}
				else {
					$display = '['.$this->strings['action_'.$type].']';
				}
				break;
			case 'image':
				$display = '<img src="'.$url.'" alt="'.$this->slash($this->strings['action_'.$type]).'" class="pte_button_'.$type.'" />';
				break;
		}
		return $display;
	}
	
	/**
	 * This handles the PHP Tag Engine AJAX requests
	 *
	 */
	function ajax_engine() {
		if (!isset($_GET['pte_action'])) {
			return;
		}
		$output = null;
		$vars = array(
			'user'
			,'item'
			,'tag'
			,'tags'
			,'type'
			,'old_tag'
			,'new_tag'
		);
		foreach ($vars as $var) {
			if (!empty($_REQUEST[$var])) {
				$$var = stripslashes($_REQUEST[$var]);
			}
			else {
				$$var = null;
			}
		}
		switch ($_GET['pte_action']) {
			case 'save_tags':
				$result = $this->save_tags($item, $tags, $type, $user);
				$output = '<result action="'.$_GET['pte_action'].'" success="'.$result['success'].'" user="'
					.$this->xml_encode($user).'" item="'.$this->xml_encode($item).'" type="'.$this->xml_encode($type).'">'
					.'<tags><![CDATA['.implode(' ', $result['tags']).']]></tags>'
					.'</result>';
				break;
			case 'add_tag':
// TODO - specifically adding a tag goes here
				break;
			case 'remove_tag':
				if ($this->remove_tag($item, $tag, $user, $type)) {
					$success = 'y';
				}
				else {
					$success = 'n';
				}
				$output = '<result action="'.$_GET['pte_action'].'" success="'.$success.'" user="'
					.$this->xml_encode($user).'" item="'.$this->xml_encode($item).'">'
					.'<tag><![CDATA['.$tag.']]></tag>'
					.'</result>';
				break;
			case 'edit_tag':
				if ($this->edit_tag($old_tag, $new_tag)) {
					$success = 'y';
				}
				else {
					$success = 'n';
				}
				$output = '<result action="'.$_GET['pte_action'].'" success="'.$success.'">'
					.'<old_tag><![CDATA['.$old_tag.']]></old_tag>'
					.'<new_tag><![CDATA['.$new_tag.']]></new_tag>'
					.'</result>';
				break;
			case 'delete_tag':
				if ($this->delete_tag($tag)) {
					$success = 'y';
				}
				else {
					$success = 'n';
				}
				$output = '<result action="'.$_GET['pte_action'].'" success="'.$success.'">'
					.'<tag><![CDATA['.$tag.']]></tag>'
					.'</result>';
				break;
		}
		if ($output != null) {
			$this->xml_out($output);
		}
		else {
			$this->xml_out('<result action="'.$this->xml_encode(stripslashes($_GET['pte_action'])).'" success="n" />');
		}
	}
	
	/**
	 * Create the PHP Tag Engine tables
	 *
	 * @return boolean
	 */
	function install() {
		require_once('phptagengine.config.inc.php');
		
		$tables = array();
		$indexes = array();
		
// TAGS
		
		$tables[$this->table_tags] = '
			  `id` I4 AUTO PRIMARY NOTNULL
			, `item` C(255) NOTNULL
			, `tag` I4 NOTNULL
			, `type` C(255) NOTNULL
			, `user` C(255) NOTNULL
			, `date` T DEFTIMESTAMP NOTNULL
		';
		
		$indexes[$this->table_tags] = array(
			  'id'
			 ,'item'
			 ,'tag'
			 ,'type'
			 ,'user'
			 ,'date'
		);
		
// TAG NAMES
		
		$tables[$this->table_tag_names] = '
			  `id` I4 AUTO PRIMARY NOTNULL
			, `name` C(255) NOTNULL
		';
		
		$indexes[$this->table_tag_names] = array(
			 'id'
			,'name'
		);
		
		$dict = NewDataDictionary($this->db);
		
		$error = 0;
		
		foreach ($tables as $table => $data) {
			$result = $dict->ExecuteSQLArray(
				$dict->CreateTableSQL(
					$table
					,$data
				)
			) or die(throw_error($this->db->ErrorMsg(), __FILE__, __LINE__));
			
			if ($result) {
				if (isset($indexes[$table]) && count($indexes[$table]) > 0) {
					foreach ($indexes[$table] as $index) {
						$extra = false;
						if (substr($index, 0, 10) == '__unique__') {
							$index = str_replace('__unique__', '', $index);
							$extra = array('UNIQUE');
						}
						$result = $dict->ExecuteSQLArray(
							$dict->CreateIndexSQL(
								$index
								,$table
								,'`'.$index.'`'
								,$extra
							)
						) or die(throw_error($this->db->ErrorMsg(), __FILE__, __LINE__));
						if (!$result) {
							$error++;
						}
					}
				}
			}
			else {
				$error++;
			}
		}
		if ($error == 0) {
			return true;
		}
		else {
			return false;
		}
	}
	
	/**
	 * Print error, or not, depending on debug setting.
	 *
	 * @return string
	 */
	function throw_error($msg, $file, $line) {
		if ($this->debug) {
			return $msg.' in '.$file.' on line: '.$line;
		}
	}
}


?>
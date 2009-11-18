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
 * The PHP Tag Engine configuration settings
 * 
 * @package phptagengine
 */

if (__FILE__ == basename($_SERVER['SCRIPT_NAME'])) { die(); }

// -- instantiate PHP Tag Engine

$pte = new phptagengine;

if (!isset($pte_js) || !$pte_js) { // no database needed for JS

// -- database info
	$pte->db = $db; // where $db is your ADOdb instance
	$pte->table_tags = $table_prefix.'tags';
	$pte->table_tag_names = $table_prefix.'tag_names';
	$pte->table_users = $table_prefix.'users';
	$pte->table_users_name = 'name';

}

// -- misc

$pte->base_url = 'phptagengine/';
$pte->ajax_handler = 'u_rail.php';
$pte->tag_browse_url = 'index.php?screen=tags&tag=<tag>&type=<type>';

// -- default values (optional)

$pte->default_type = '';
$pte->default_user = '';

// -- language file

require('languages/english.inc.php');

// -- buttons

$pte->edit_button_display = 'text';
$pte->edit_button_image_url = 'images/icon_edit_tag_small.gif';

$pte->show_remove_links = false;
$pte->delete_button_display = 'text';
$pte->delete_button_image_url = 'images/icon_delete_tag.gif';

// -- Yahoo! Auto-Complete

$pte->yac = true;

?>
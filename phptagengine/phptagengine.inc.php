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
 * The main include for PHP Tag Engine - this includes the other files
 * 
 * It should be include()ed in the file specified as the 
 *  @link phptagengine::$ajax_handler
 * 
 * @package phptagengine
 */

if (__FILE__ == basename($_SERVER['SCRIPT_NAME'])) { die(); }

require_once('phptagengine.class.inc.php');
require_once('phptagengine.config.inc.php');

// handle AJAX Requests
$pte->ajax_engine();

?>
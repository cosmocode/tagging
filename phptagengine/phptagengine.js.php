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

ini_set('display_errors', '0');
ini_set('error_reporting', E_PARSE);

/**
 * The JavaScript for PHP Tag Engine
 * 
 * Includes the AJAX code and sets the strings for localization
 * 
 * @package phptagengine
 */

$pte_js = true;

require_once('phptagengine.class.inc.php');
require_once('phptagengine.config.inc.php');

if (isset($_SERVER['HTTP_IF_MODIFIED_SINCE'])) {
	$if_modified_since = preg_replace('/;.*$/', '', $_SERVER['HTTP_IF_MODIFIED_SINCE']);
	
	$config = filemtime('phptagengine.config.inc.php');
	$language = filemtime('languages/'.$pte->language.'.inc.php');
	if ($config > $language) {
		$mtime = $config;
	}
	else {
		$mtime = $language;
	}
	$gmdate_mod = gmdate('D, d M Y H:i:s', $mtime) . ' GMT';
	
	if ($if_modified_since == $gmdate_mod) {
		header("HTTP/1.0 304 Not Modified");
		exit;
	}
	header("Last-Modified: $gmdate_mod");
}

@ob_start('ob_gzhandler');

header("Content-type: text/javascript");
header('Expires: '.gmdate('D, d M Y H:i:s', time()+24*60*60) . ' GMT');

if ($pte->yac) {
	foreach ($pte->yac_files as $file) {
		readfile('yui/'.$file);
	}
}

?>

// Copyright (c) 2006 Alex King. All rights reserved.
// http://www.alexking.org/software/phptagengine/
//
// Released under the LGPL license
// http://www.opensource.org/licenses/lgpl-license.php
//
// **********************************************************************
// This program is distributed in the hope that it will be useful, but
// WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. 
// **********************************************************************

var pte = {
	req : false
	, ajax_handler : '<?php print($pte->ajax_handler); ?>'
	, tag_browse_url : '<?php print($pte->tag_browse_url); ?>'
	, strings : {}
	, show_remove_links : <?php if ($pte->show_remove_links) { print('true'); } else { print('false'); } ?>
	, edit_button_display : '<?php print($pte->edit_button_display); ?>'
	, edit_button_image_url : '<?php print($pte->edit_button_image_url); ?>'
	, delete_button_display : '<?php print($pte->delete_button_display); ?>'
	, delete_button_image_url : '<?php print($pte->delete_button_image_url); ?>'
}

<?php
// put language strings into the JS scope
foreach ($pte->strings as $k => $v) {
	print('pte.strings["'.$k.'"] = "'.$pte->slash($v).'";'."\n");
}
readfile('phptagengine.js');
?>

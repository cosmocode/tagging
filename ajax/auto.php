<?php
$hlp = plugin_load('helper', 'tagging');

$search = $_REQUEST['search'];
$tags = $hlp->getTags(array('tag' => '%' . $search . '%'), 'tag');
arsort($tags);
$tags = array_keys($tags);
$AJAX_JSON = array_combine($tags, $tags);

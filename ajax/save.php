<?php
$hlp = plugin_load('helper', 'tagging');

$data = $_REQUEST['tagging'];

$id = $data['id'];

$hlp->replaceTags($id, $_SERVER['REMOTE_USER'],
                  preg_split('/\s*,\s*/', $data['tags'], -1,
                             PREG_SPLIT_NO_EMPTY));

$tags = $hlp->getTags(array('pid' => $id), 'tag');
$hlp->html_cloud($tags, 'tag', array($hlp, 'linkToSearch'), false);

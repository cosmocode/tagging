<?php
// must be run within Dokuwiki
if (!defined('DOKU_INC')) die();

class admin_plugin_tagging extends DokuWiki_Admin_Plugin {

    private $hlp;
    private $message;

    function forAdminOnly() { return false; }

    function handle() {
        $this->hlp = plugin_load('helper', 'tagging');

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (checkSecurityToken()) {
                global $INPUT;
                $input = $INPUT->post->arr('action');
                if (isset($input['rename'])) {
                    $this->message = $this->hlp->renameTag($input['formerTagName'], $input['newTagName']);
                }
            }
        }
    }

    function html() {
        global $ID;
        $tags = $this->hlp->getAllTags();
        include dirname(__FILE__) . '/admin_tpl.php';
    }

}

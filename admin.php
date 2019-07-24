<?php
// must be run within Dokuwiki
if (!defined('DOKU_INC')) {
    die();
}

class admin_plugin_tagging extends DokuWiki_Admin_Plugin {

    /** @var helper_plugin_tagging */
    private $hlp;

    public function __construct() {
        $this->hlp = plugin_load('helper', 'tagging');
    }

    /**
     * We allow use by managers
     *
     * @return bool always false
     */
    function forAdminOnly() {
        return false;
    }

    /**
     * Handle tag actions
     *
     * FIXME remove obsolete actions
     */
    function handle() {
        global $ID, $INPUT;

        //by default sort by tag name
        if (!$INPUT->has('sort')) {
            $INPUT->set('sort', 'tid');
        }

        //now starts functions handle
        if (!$INPUT->has('fn')) {
            return false;
        }
        if (!checkSecurityToken()) {
            return false;
        }

        // extract the command and any specific parameters
        // submit button name is of the form - fn[cmd][param(s)]
        $fn = $INPUT->param('fn');

        if (is_array($fn)) {
            $cmd = key($fn);
            $param = is_array($fn[$cmd]) ? key($fn[$cmd]) : null;
        } else {
            $cmd = $fn;
            $param = null;
        }

        switch ($cmd) {
            case 'rename':
                $this->hlp->renameTag($INPUT->str('old'), $INPUT->str('new'));
                break;
            case 'delete':
                $this->hlp->deleteTags(array_keys($INPUT->arr('tags')), $INPUT->str('filter'));
                break;
            case 'sort':
                $INPUT->set('sort', $param);
                break;

        }
    }

    /**
     * Draw the interface
     */
    public function html() {
        echo $this->locale_xhtml('intro');
        echo $this->hlp->html_table();
    }
}

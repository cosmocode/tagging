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
     */
    function handle() {

        if (!empty($_REQUEST['cmd']['clean'])) {
            checkSecurityToken() && $this->hlp->deleteInvalidTaggings();
        }

        if (!$this->hlp->getParams()) {
            $this->hlp->setDefaultSort();
            return false;
        }

        $this->hlp->setDefaultSort();

        if (!checkSecurityToken()) {
            return false;
        }
    }

    /**
     * Draw the interface
     */
    public function html() {
        echo $this->locale_xhtml('intro');
        echo $this->hlp->html_table();
        echo $this->locale_xhtml('clean');
        echo $this->hlp->html_clean();
    }
}

<?php

use dokuwiki\Extension\AdminPlugin;

class admin_plugin_tagging extends AdminPlugin
{
    /** @var helper_plugin_tagging */
    private $hlp;

    public function __construct()
    {
        $this->hlp = plugin_load('helper', 'tagging');
    }

    /**
     * We allow use by managers
     *
     * @return bool always false
     */
    public function forAdminOnly()
    {
        return false;
    }

    /**
     * Handle tag actions
     */
    public function handle()
    {

        if (!empty($_REQUEST['cmd']['clean'])) {
            if (checkSecurityToken()) {
                $this->hlp->deleteInvalidTaggings();
            }
        }

        if (!$this->hlp->getParams()) {
            $this->hlp->setDefaultSort();
            return;
        }

        $this->hlp->setDefaultSort();
    }

    /**
     * Draw the interface
     */
    public function html()
    {
        echo $this->locale_xhtml('intro');
        echo $this->hlp->html_table();
        echo $this->locale_xhtml('clean');
        echo $this->hlp->html_clean();
    }
}

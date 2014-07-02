<?php
// must be run within Dokuwiki
if(!defined('DOKU_INC')) die();

class admin_plugin_tagging extends DokuWiki_Admin_Plugin {

    /** @var helper_plugin_tagging */
    private $hlp;
    /** @var  string message to show */
    private $message;

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
     * Handle tag renames
     */
    function handle() {
        global $INPUT;
        if($INPUT->post->has('old') && $INPUT->post->has('new') && checkSecurityToken()) {
            $this->hlp->renameTag($INPUT->post->str('old'), $INPUT->post->str('new'));
        }
    }

    /**
     * Draw the interface
     */
    function html() {
        echo $this->locale_xhtml('intro');
        $this->html_form();
        echo '<br />';
        $this->html_table();
    }

    /**
     * Show form for renaming tags
     */
    protected function html_form() {
        global $ID;

        $form = new Doku_Form(array('action' => script(), 'method' => 'post', 'class' => 'plugin_tagging'));
        $form->addHidden('do', 'admin');
        $form->addHidden('page', 'tagging');
        $form->addHidden('id', $ID);

        $form->startFieldset($this->getLang('admin rename tag'));
        $form->addElement(form_makeTextField('old', '', $this->getLang('admin find tag'), '', 'block'));
        $form->addElement(form_makeTextField('new', '', $this->getLang('admin new name'), '', 'block'));
        $form->addElement(form_makeButton('submit', 'admin', $this->getLang('admin save')));

        $form->printForm();
    }

    /**
     * Display ALL the tags!
     */
    protected function html_table() {
        $tags = $this->hlp->getAllTags();

        echo '<table class="inline plugin_tagging">';
        echo '<tr>';
        echo '<th>' . $this->getLang('admin tag') . '</th>';
        echo '<th>' . $this->getLang('admin occurrence') . '</th>';
        echo '<th>' . $this->getLang('admin taggers') . '</th>';
        echo '</tr>';

        foreach($tags as $tagname => $taginfo) {
            $taggers = array_unique($taginfo['tagger']);
            sort($taggers);
            $taggers = join(', ', $taggers);

            echo '<tr>';
            echo '<td><a class="tagslist" href="?do=search&amp;id=' . rawurlencode($tagname) . '">' . hsc($tagname) . '</a></td>';
            echo '<td>' . $taginfo['count'] . '</td>';
            echo '<td>' . hsc($taggers) . '</td>';
            echo '</tr>';
        }

        echo '</table>';
    }
}

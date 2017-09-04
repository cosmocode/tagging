<?php
// must be run within Dokuwiki
if (!defined('DOKU_INC')) {
    die();
}

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
     * Handle tag actions
     */
    function handle() {
        global $INPUT;
        
        if (!$INPUT->has('fn')) return false;
        if (!checkSecurityToken()) return false;
        
        // extract the command and any specific parameters
        // submit button name is of the form - fn[cmd][param(s)]
        $fn   = $INPUT->param('fn');

        if (is_array($fn)) {
            $cmd = key($fn);
            $param = is_array($fn[$cmd]) ? key($fn[$cmd]) : null;
        } else {
            $cmd = $fn;
            $param = null;
        }

        switch ($cmd) {
            case 'rename'    : $this->_renameTag(); break;
            case 'delete'    : $this->_deleteTags(); break;
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
        $form->addHidden('fn', 'rename');

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
        global $ID;
        
        $tags = $this->hlp->getAllTags();
        
        echo '<form action="'.wl().'" method="post" accept-charset="utf-8">';
        formSecurityToken();
        echo '<input type="hidden" name="do"   value="admin" />';
        echo '<input type="hidden" name="page" value="tagging" />';
        echo '<input type="hidden" name="id"   value="'.$ID.'" />';
        
        echo '<table class="inline plugin_tagging">';
        echo '<tr>';
        echo '<th>&#160;</th>';
        echo '<th>' . $this->getLang('admin tag') . '</th>';
        echo '<th>' . $this->getLang('admin occurrence') . '</th>';
        echo '<th>' . $this->getLang('admin writtenas') . '</th>';
        echo '<th>' . $this->getLang('admin taggers') . '</th>';
        echo '</tr>';

        foreach ($tags as $tagname => $taginfo) {
            $taggers = array_unique($taginfo['tagger']);
            sort($taggers);
            $written = array_unique($taginfo['orig']);
            $taggers = join(', ', $taggers);
            $written = join(', ', $written);

            echo '<tr>';
            echo '<td class="centeralign"><input type="checkbox" name="tags['.hsc($tagname).']" /></td>';
            echo '<td><a class="tagslist" href="' . $this->hlp->getTagSearchURL($tagname) . '">' . hsc($tagname) . '</a></td>';
            echo '<td>' . $taginfo['count'] . '</td>';
            echo '<td>' . hsc($written) . '</td>';
            echo '<td>' . hsc($taggers) . '</td>';
            echo '</tr>';
        }
        echo '<tr>';
        echo '<td colspan="5" class="centeralign">';
        echo '<span class="medialeft">';
        echo '<button type="submit" name="fn[delete]" id="tagging__del">'.$this->getLang('admin delete_selected').'</button>';
        echo '</tr>';

        echo '</table>';
        echo '</form>';
    }
    
    /**
     * Rename a tag
     *
     */
    protected function _renameTag() {
        global $INPUT;
        
        if ($INPUT->post->has('old') && $INPUT->post->has('new')) {
            $this->hlp->renameTag($INPUT->post->str('old'), $INPUT->post->str('new'));
        }
    }
    
    /**
     * Delete tags
     *
     */
    protected function _deleteTags() {
        global $INPUT;
        
        if ($INPUT->post->has('tags')) {
            $this->hlp->deleteTags(array_keys($INPUT->post->arr('tags')));
        }
    }
}

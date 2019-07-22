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

        //by default use current page namespace
        if (!$INPUT->has('filter')) {
            $INPUT->set('filter', getNS($ID));
        }


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
    function html() {
        echo $this->locale_xhtml('intro');
        $this->html_table();
    }

    /**
     * Display ALL the tags!
     */
    protected function html_table() {
        global $ID, $INPUT;

        $headers = array(
            array('value' => $this->getLang('admin tag'), 'sort_by' => 'tid'),
            array('value' => $this->getLang('admin occurrence'), 'sort_by' => 'count'),
            array('value' => $this->getLang('admin writtenas'), 'sort_by' => 'orig'),
            array('value' => $this->getLang('admin taggers'), 'sort_by' => 'taggers'),
            array('value' => $this->getLang('admin actions'), 'sort_by' => false),
        );

        $sort = explode(',', $INPUT->str('sort'));
        $order_by = $sort[0];
        $desc = false;
        if (isset($sort[1]) && $sort[1] === 'desc') {
            $desc = true;
        }
        $tags = $this->hlp->getAllTags($INPUT->str('filter'), $order_by, $desc);

        $form = new dokuwiki\Form\Form();
        $form->setHiddenField('do', 'admin');
        $form->setHiddenField('page', 'tagging');
        $form->setHiddenField('id', $ID);
        $form->setHiddenField('sort', $INPUT->str('sort'));

        // actions dialog
        $form->addTagOpen('div')->id('tagging__action-dialog')->attr('style', "display:none;");
        $form->addTagClose('div');

        $form->addTagOpen('table')->addClass('inline plugin_tagging');


        /**
         * Table headers
         */
        $form->addTagOpen('tr');
        foreach ($headers as $header) {
            $form->addTagOpen('th');
            if ($header['sort_by'] !== false) {
                $param = $header['sort_by'];
                $icon = 'arrow-both';
                $title = $this->getLang('admin sort ascending');
                if ($header['sort_by'] === $order_by) {
                    if ($desc === false) {
                        $icon = 'arrow-up';
                        $title = $this->getLang('admin sort descending');
                        $param .= ',desc';
                    } else {
                        $icon = 'arrow-down';
                    }
                }
                $form->addButtonHTML("fn[sort][$param]", $header['value'] . ' ' . inlineSVG(dirname(__FILE__) . "/images/$icon.svg"))
                    ->addClass('plugin_tagging sort_button')
                    ->attr('title', $title);
            } else {
                $form->addHTML($header['value']);
            }
            $form->addTagClose('th');
        }
        $form->addTagClose('tr');

        foreach ($tags as $taginfo) {
            $tagname = $taginfo['tid'];
            $taggers = $taginfo['taggers'];
            $written = $taginfo['orig'];

            $form->addTagOpen('tr');
            $form->addHTML('<td><a class="tagslist" href="' .
                $this->hlp->getTagSearchURL($tagname) . '">' . hsc($tagname) . '</a></td>');
            $form->addHTML('<td>' . $taginfo['count'] . '</td>');
            $form->addHTML('<td>' . hsc($written) . '</td>');
            $form->addHTML('<td>' . hsc($taggers) . '</td>');
            // action buttons
            $form->addHTML('<td>');
            $form->addButtonHTML('fn[actions][rename][' . $taginfo['tid'] . ']', inlineSVG(dirname(__FILE__) . '/images/edit.svg'))
                ->addClass('plugin_tagging action_button')->attr('data-action', 'rename')->attr('data-tid', $taginfo['tid']);
            $form->addButtonHTML('fn[actions][delete][' . $taginfo['tid'] . ']', inlineSVG(dirname(__FILE__) . '/images/delete.svg'))
                ->addClass('plugin_tagging action_button')->attr('data-action', 'delete')->attr('data-tid', $taginfo['tid']);
            $form->addHTML('</td>');
            $form->addTagClose('tr');
        }

        $form->addTagClose('table');
        echo $form->toHTML();
    }
}

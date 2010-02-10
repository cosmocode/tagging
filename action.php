<?php
/**
 * Allow users to tag a page
 *
 * @author Adrian Lang <lang@cosmocode.de>
 */

if(!defined('DOKU_INC')) die();
if(!defined('DOKU_PLUGIN')) define('DOKU_PLUGIN',DOKU_INC.'lib/plugins/');
require_once DOKU_PLUGIN.'action.php';
require_once DOKU_PLUGIN.'tagging/common.php';

class action_plugin_tagging extends DokuWiki_Action_Plugin {

    /**
     * Register handlers
     */
    function register(&$controller) {
        $controller->register_hook('TPL_METAHEADER_OUTPUT', 'BEFORE', $this,
                                   'echo_head');
        $controller->register_hook('TOOLBAR_DEFINE', 'AFTER', $this,
                                   'echo_pte');
        $controller->register_hook('TPL_ACT_RENDER', 'AFTER', $this,
                                   'echo_searchresults');
        $controller->register_hook('TPL_ACT_RENDER', 'BEFORE', $this,
                                   'init_pte_on_show');
        $controller->register_hook('AJAX_CALL_UNKNOWN', 'BEFORE', $this,
                                   'handle_ajax');
    }

    /**
     * Include phptagengine’s css and javascript
     */
    function echo_head(&$event, $param) {
        $pte = tagging_get_pte($this);
        if (is_null($pte)) {
            throw new Exception();
        }
        $pte->html_head();
    }

    /**
     * Include phptagengine’s css and javascript
     */
    function echo_pte(&$event, $param) {
        global $ACT;
        try {
            $pte = tagging_get_pte($this);
            if (is_null($pte)) {
                throw new Exception();
            }
            $pte->html_pte();
        } catch (Exception $e) {
            // Assure that pte is defined to avoid JavaScript errors.
            echo 'var pte = {};';
        }
    }

    /**
     * Echo own tags and all tags (when showing a page) or pages with selected
     * tag (when searching)
     */
    function echo_searchresults(&$event, $param) {
        global $ACT;
        if ($ACT !== 'search') {
            return;
        }
        global $QUERY;
        $pte = tagging_get_pte($this);
        if (is_null($pte)) {
            return;
        }
        $result = $pte->browse_tag($QUERY);
        if ($result === false) {
            return;
        }
        $sec = '===== ' . $this->getLang('search_section_title') . " =====\n";
        while ($data = $result->FetchNextObject()) {
            $sec .= '  * [[' . $data->ITEM . ']] ' .
                    sprintf($this->getLang('search_nr_users'), $data->N) .
                    DOKU_LF;
        }
        echo p_render('xhtml', p_get_instructions($sec), $info);
    }

    function init_pte_on_show(&$event, $param) {
        global $ACT;
        if ($ACT !== 'show') {
            return;
        }
        tagging_get_pte($this);
    }

    /**
     * Handle AJAX request
     */
    function handle_ajax($event) {
        if ($event->data !== 'tagging') {
            return;
        }
        if (isset($_SERVER['REMOTE_USER'])) {
            $pte = tagging_get_pte($this);
            if (is_null($pte)) {
                return;
            }
            $pte->ajax_engine();
        }
        $event->stopPropagation();
        $event->preventDefault();
    }

    function tpl_tagcloud() {
        global $ID;
        $pte = tagging_get_pte($this);
        if (is_null($pte)) {
            return;
        }
        list($min, $max, $data_arr) = $pte->tagcloud($ID, 10);

        cloud_weight($data_arr, $min, $max, 10);

        echo '<ul class="tagcloud" id="tagging_tagcloud">';
        if (count($data_arr) === 0) {
            // Produce valid XHTML (ul needs a child)
            $this->setupLocale();
            echo '<li>' . $this->lang['js']['notags'] . '</li>';
        }
        foreach ($data_arr as $tag => $size) {
            echo '<li class="t' .
                 $size . '">' .
                 '<a href="' . $pte->tag_browse_url($tag) . '">' .
                 $tag . '</a>' . '</li> ';
        }
        echo '</ul>';
    }

    function tpl_tagedit() {
        global $ID;
        $pte = tagging_get_pte($this);
        if (is_null($pte)) {
            return;
        }
        if (isset($_SERVER['REMOTE_USER'])) {
            $pte->html_item_tags($ID);
        }
    }
}

<?php

if(!defined('DOKU_INC')) die();
class action_plugin_tagging extends DokuWiki_Action_Plugin {
    /**
     * Register handlers
     */
    function register(&$controller) {
        $controller->register_hook('TPL_ACT_RENDER', 'AFTER', $this,
                                   'echo_searchresults');
    }

    function echo_searchresults(&$event, $param) {
        global $ACT;
        if ($ACT !== 'search') {
            return;
        }
        global $QUERY;

        $hlp = plugin_load('helper', 'tagging');

        // FIXME own tags
        $tags = $hlp->getTags(array('tag' => $QUERY), 'pid');

        $R = p_get_renderer('xhtml');
        $R->header($this->getLang('search_section_title'), 2, 1);
        $R->section_open(2);
        echo $R->doc;
        $R->doc = '';
        $hlp->html_cloud($tags, 'page', 'html_wikilink');
        $R->section_close();
        echo $R->doc;
    }
}

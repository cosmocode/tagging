<?php

if(!defined('DOKU_INC')) die();
class action_plugin_tagging extends DokuWiki_Action_Plugin {
    /**
     * Register handlers
     */
    function register(&$controller) {
        $controller->register_hook('TPL_ACT_RENDER', 'AFTER', $this,
                                   'echo_searchresults');
        $controller->register_hook('AJAX_CALL_UNKNOWN', 'BEFORE', $this, 'handle_ajax');
        $controller->register_hook('AJAX_CALL_UNKNOWN', 'BEFORE', $this, 'handle_autocompletion');
    }

    function handle_autocompletion($event) {
        if ($event->data !== 'tagging_auto') {
            return;
        }

        $search = $_REQUEST['search'];

        $hlp = plugin_load('helper', 'tagging');
        $tags = $hlp->getTags(array('tag' => '%' . $search . '%'), 'tag');
        arsort($tags);
        $tags = array_keys($tags);

        require_once DOKU_INC . 'inc/JSON.php';
        $json = new JSON();
        echo '(' . $json->encode($tags) . ')';

        $event->preventDefault();
    }

    /**
     * Handle AJAX request
     */
    function handle_ajax($event) {
        $ajax = $this->loadHelper('ajaxloader', true);
        if (!$ajax || !$ajax->isLoader('tagging', $event->data)) {
            return;
        }

        $data = $ajax->handleLoad();
        $hlp = plugin_load('helper', 'tagging');

        $id = $data['id'];

        $hlp->replaceTags($id, $_SERVER['REMOTE_USER'],
                          preg_split('/\s*,\s*/', $data['tags'], -1,
                                     PREG_SPLIT_NO_EMPTY));

        $tags = $hlp->getTags(array('pid' => $id), 'tag');
        $hlp->html_cloud($tags, 'tag', array($hlp, 'linkToSearch'), false);

        $event->preventDefault();
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

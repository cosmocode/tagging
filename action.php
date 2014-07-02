<?php

if(!defined('DOKU_INC')) die();
class action_plugin_tagging extends DokuWiki_Action_Plugin {
    /**
     * Register handlers
     */
    function register(&$controller) {
        $controller->register_hook(
            'TPL_CONTENT_DISPLAY', 'BEFORE', $this,
            'echo_searchresults'
        );

        $controller->register_hook(
            'AJAX_CALL_UNKNOWN', 'BEFORE', $this,
            'handle_ajax_call_unknown');
    }

    /**
     * Handle our AJAX requests
     *
     * @param Doku_Event $event
     * @param $param
     */
    function handle_ajax_call_unknown(Doku_Event &$event, $param) {
        $handled = true;

        if($event->data == 'plugin_tagging_save') {
            $this->save();
        } elseif($event->data == 'plugin_tagging_autocomplete') {
            $this->autocomplete();
        } else {
            $handled = false;
        }
        if(!$handled) return;

        $event->preventDefault();
        $event->stopPropagation();
    }

    /**
     * Save new/changed tags
     */
    function save() {
        global $INPUT;

        /** @var helper_plugin_tagging $hlp */
        $hlp = plugin_load('helper', 'tagging');

        $data = $INPUT->arr('tagging');
        $id = $data['id'];

        $hlp->replaceTags(
            $id, $_SERVER['REMOTE_USER'],
            preg_split(
                '/\s*,\s*/', $data['tags'], -1,
                PREG_SPLIT_NO_EMPTY
            )
        );

        $tags = $hlp->getTags(array('pid' => $id), 'tag');
        $hlp->html_cloud($tags, 'tag', array($hlp, 'linkToSearch'), false);
    }

    /**
     * Return autocompletion data
     */
    function autocomplete() {
        global $INPUT;

        /** @var helper_plugin_tagging $hlp */
        $hlp = plugin_load('helper', 'tagging');

        $search = $INPUT->str('term');
        $tags = $hlp->getTags(array('tag' => '%' . $hlp->getDB()->escape_string($search) . '%'), 'tag');
        arsort($tags);
        $tags = array_keys($tags);

        header('Content-Type: application/json');

        $json = new JSON();
        echo $json->encode(array_combine($tags, $tags));
    }

    /**
     * Show tagged pages on searches
     *
     * @param $event
     * @param $param
     */
    function echo_searchresults(Doku_Event &$event, $param) {
        global $ACT;
        if($ACT !== 'search') {
            return;
        }
        global $QUERY;

        /** @var helper_plugin_tagging $hlp */
        $hlp = plugin_load('helper', 'tagging');

        // FIXME own tags
        $tags = $hlp->getTags(array('tag' => $QUERY), 'pid');



        ob_start();
        $R = p_get_renderer('xhtml');
        $R->header($this->getLang('search_section_title'), 2, 1);
        $R->section_open(2);
        echo $R->doc;
        $R->doc = '';
        $hlp->html_cloud($tags, 'page', 'html_wikilink');
        $R->section_close();
        echo $R->doc;
        $results = ob_get_contents();
        ob_end_clean();

        $event->data = preg_replace('/(<h2.*?>)/', $results."\n\\1", $event->data, 1);
    }
}

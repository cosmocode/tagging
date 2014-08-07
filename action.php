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
            'handle_ajax_call_unknown'
        );
    }

    /**
     * Handle our AJAX requests
     *
     * @param Doku_Event $event
     * @param            $param
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
        $id   = $data['id'];

        $hlp->replaceTags(
            $id, $_SERVER['REMOTE_USER'],
            preg_split(
                '/\s*,\s*/', $data['tags'], -1,
                PREG_SPLIT_NO_EMPTY
            )
        );

        $tags = $hlp->findItems(array('pid' => $id), 'tag');
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
        $tags   = $hlp->findItems(array('tag' => '%'.$hlp->getDB()->escape_string($search).'%'), 'tag');
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
        global $QUERY;

        if($ACT !== 'search') return;

        // parse the search query and use the first found word as term
        $terms = ft_queryParser(idx_get_indexer(), $QUERY);

        $tag = '';
        if(isset($terms['phrases'][0])) {
            $tag = $terms['phrases'][0];
        } else if(isset($terms['and'][0])) {
            $tag = $terms['and'][0];
        }
        if(!$tag) return;

        // create filter from term and namespace
        $filter = array('tag' => $tag);
        if(isset($terms['ns'][0])) {
            $filter['pid'] = $terms['ns'][0].':%';
        }

        /** @var helper_plugin_tagging $hlp */
        $hlp   = plugin_load('helper', 'tagging');
        $pages = $hlp->findItems($filter, 'pid');
        if(!count($pages)) return;

        // create output HTML
        $results = '<h3>'.$this->getLang('search_section_title').'</h3>';
        $results .= '<div class="search_quickresults">';
        $results .= '<ul class="search_quickhits">';
        foreach($pages as $page => $cnt) {
            $results .= '<li><div class="li">';
            $results .= html_wikilink($page);
            $results .= '</div></li>';
        }
        $results .= '</ul>';
        $results .= '</div>';

        // insert it right after second level headline
        $event->data = preg_replace('/(<\/h2>)/', "\\1\n".$results, $event->data, 1);
    }
}

<?php

if (!defined('DOKU_INC')) {
    die();
}

class action_plugin_tagging extends DokuWiki_Action_Plugin {

    /**
     * @var array
     */
    protected $tagFilter = [];

    /**
     * @var array
     */
    protected $allTagsByPage = [];

    /**
     * @var string
     */
    protected $originalQuery = '';

    /**
     * Register handlers
     */
    function register(Doku_Event_Handler $controller) {
        $controller->register_hook(
            'TPL_CONTENT_DISPLAY', 'BEFORE', $this,
            'echo_searchresults'
        );

        $controller->register_hook(
            'TPL_ACT_RENDER', 'BEFORE', $this,
            'setupTagSearch'
        );

        $controller->register_hook(
            'SEARCH_QUERY_FULLPAGE', 'AFTER', $this,
            'filterSearchResults'
        );

        $controller->register_hook(
            'SEARCH_RESULT_FULLPAGE', 'AFTER', $this,
            'tagResults'
        );

        $controller->register_hook(
            'FORM_SEARCH_OUTPUT', 'BEFORE', $this,
            'addSwitchToSearchForm'
        );

        $controller->register_hook(
            'AJAX_CALL_UNKNOWN', 'BEFORE', $this,
            'handle_ajax_call_unknown'
        );

        $controller->register_hook(
            'ACTION_ACT_PREPROCESS', 'BEFORE', $this,
            'handle_jump'
        );

        $controller->register_hook(
            'DOKUWIKI_STARTED', 'AFTER', $this,
            'js_add_security_token'
        );

        $controller->register_hook(
            'PLUGIN_MOVE_PAGE_RENAME', 'AFTER', $this,
            'update_moved_page'
        );
    }

    /**
     * Add sectok to JavaScript to secure ajax requests
     *
     * @param Doku_Event $event
     * @param            $param
     */
    function js_add_security_token(Doku_Event $event, $param) {
        global $JSINFO;
        $JSINFO['sectok'] = getSecurityToken();
    }

    /**
     * Handle our AJAX requests
     *
     * @param Doku_Event $event
     * @param            $param
     */
    function handle_ajax_call_unknown(Doku_Event &$event, $param) {
        $handled = true;

        if ($event->data == 'plugin_tagging_save') {
            $this->save();
        } elseif ($event->data == 'plugin_tagging_autocomplete') {
            $this->autocomplete();
        } elseif ($event->data === 'plugin_tagging_admin_change') {
            $this->admin_change();
        } elseif ($event->data === 'plugin_tagging_html_pages') {
            $this->getPagesHtml();
        } elseif ($event->data === 'plugin_tagging_delete') {
            $this->deleteTag();
        } elseif ($event->data === 'plugin_tagging_rename') {
            $this->renameTag();
        } else {
            $handled = false;
        }
        if (!$handled) {
            return;
        }

        $event->preventDefault();
        $event->stopPropagation();
    }

    /**
     * Jump to a tag
     *
     * @param Doku_Event $event
     * @param            $param
     */
    function handle_jump(Doku_Event &$event, $param) {
        if (act_clean($event->data) != 'tagjmp') {
            return;
        }

        $event->preventDefault();
        $event->stopPropagation();
        $event->data = 'show';

        global $INPUT;
        $tags = $INPUT->arr('tag', (array)$INPUT->str('tag'));
        $lang = $INPUT->str('lang');

        /** @var helper_plugin_tagging $hlp */
        $hlp = plugin_load('helper', 'tagging');

        foreach ($tags as $tag) {
            $filter = array('tag' => $tag);
            if ($lang) {
                $filter['lang'] = $lang;
            }
            $pages = $hlp->findItems($filter, 'pid', 1);
            if (!count($pages)) {
                continue;
            }

            $id = array_pop(array_keys($pages));
            send_redirect(wl($id, '', true, '&'));
        }

        $tags = array_map('hsc', $tags);
        msg(sprintf($this->getLang('tagjmp_error'), join(', ', $tags)), -1);
    }

    /**
     * Save new/changed tags
     */
    function save() {
        global $INPUT;
        global $INFO;


        /** @var helper_plugin_tagging $hlp */
        $hlp = plugin_load('helper', 'tagging');

        $data = $INPUT->arr('tagging');
        $id = $data['id'];
        $INFO['writable'] = auth_quickaclcheck($id) >= AUTH_EDIT; // we also need this in findItems

        if ($INFO['writable'] && $hlp->getUser()) {
            $hlp->replaceTags(
                $id, $hlp->getUser(),
                preg_split(
                    '/(\s*,\s*)|(\s*,?\s*\n\s*)/', $data['tags'], -1,
                    PREG_SPLIT_NO_EMPTY
                )
            );
        }

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
        $tags = $hlp->findItems(array('tag' => '*' . $hlp->getDB()->escape_string($search) . '*'), 'tag');
        arsort($tags);
        $tags = array_keys($tags);

        header('Content-Type: application/json');

        $json = new JSON();
        echo $json->encode(array_combine($tags, $tags));
    }

    /**
     * Allow admins to change all tags (not only their own)
     * We change the tag for every user
     */
    function admin_change() {
        global $INPUT;

        /** @var helper_plugin_tagging $hlp */
        $hlp = plugin_load('helper', 'tagging');

        header('Content-Type: application/json');
        $json = new JSON();

        if (!auth_isadmin()) {
            echo $json->encode(array('status' => 'error', 'msg' => $this->getLang('no_admin')));

            return;
        }

        if (!checkSecurityToken()) {
            echo $json->encode(array('status' => 'error', 'msg' => 'Security Token did not match. Possible CSRF attack.'));

            return;
        }

        if (!$INPUT->has('id')) {
            echo $json->encode(array('status' => 'error', 'msg' => 'No page id given.'));

            return;
        }
        $pid = $INPUT->str('id');

        if (!$INPUT->has('oldValue') || !$INPUT->has('newValue')) {
            echo $json->encode(array('status' => 'error', 'msg' => 'No proper input. Give "oldValue" and "newValue"'));

            return;
        }


        list($err, $msg) = $hlp->modifyPageTag($pid, $INPUT->str('oldValue'), $INPUT->str('newValue'));
        if ($err) {
            echo $json->encode(array('status' => 'error', 'msg' => $msg));

            return;
        }

        $tags = $hlp->findItems(array('pid' => $pid), 'tag');
        $userTags = $hlp->findItems(array('pid' => $pid, 'tagger' => $hlp->getUser()), 'tag');
        echo $json->encode(array(
            'status'          => 'ok',
            'tags_edit_value' => implode(', ', array_keys($userTags)),
            'html_cloud'      => $hlp->html_cloud($tags, 'tag', array($hlp, 'linkToSearch'), false, true),
        ));
    }

    /**
     * Management: delete all occurrences of a tag
     */
    public function deleteTag()
    {
        global $INPUT;
        $data = $INPUT->arr('tagging');

        /** @var helper_plugin_tagging $hlp */
        $hlp = plugin_load('helper', 'tagging');
        $hlp->deleteTags($data['tid']);
    }

    /**
     * Management: rename all occurrences of a tag
     */
    public function renameTag()
    {
        global $INPUT;
        $data = $INPUT->arr('tagging');

        /** @var helper_plugin_tagging $hlp */
        $hlp = plugin_load('helper', 'tagging');
        $hlp->renameTag($data['oldValue'], $data['newValue']);
    }

    /**
     * Tag dialog HTML: links to all pages with a given tag
     *
     * @return string
     */
    public function getPagesHtml()
    {
        global $INPUT;
        $data = $INPUT->arr('tagging');

        /** @var helper_plugin_tagging $hlp */
        $hlp = plugin_load('helper', 'tagging');
        echo $hlp->getPagesHtml($data['tid']);
    }

    /**
     * Add AND/OR switch to advanced search tools
     *
     * @param Doku_Event $event
     * @param            $param
     */
    public function addSwitchToSearchForm(Doku_Event $event, $param)
    {
        global $INPUT;

        /* @var \dokuwiki\Form\Form $searchForm */
        $searchForm = $event->data;
        $currElemPos = $searchForm->findPositionByAttribute('class', 'advancedOptions');

        // the actual filter is built in Javascript
        $searchForm->addTagOpen('div', ++$currElemPos)
            ->addClass('toggle')
            ->attr('aria-haspopup', 'true')
            ->id('plugin__tagging-tags');
        // this element needs to be rendered by the backend so that all JS events properly attach
        $searchForm->addTagOpen('div', ++$currElemPos)
            ->addClass('current');
        $searchForm->addHTML($this->getLang('search_filter_label'), ++$currElemPos);
        $searchForm->addTagClose('div', ++$currElemPos);
        $searchForm->addTagClose('div', ++$currElemPos);

        // set active setting
        $active = '';
        if ($INPUT->has('tagging-logic')) {
            $active = $INPUT->str('tagging-logic');
        }
        $searchForm->setHiddenField('tagging-logic', $active);

        $searchForm->addTagOpen('div', ++$currElemPos)
            ->addClass('toggle')
            ->attr('aria-haspopup', 'true')
            ->id('plugin__tagging-logic');

        // popup toggler
        $toggler = $searchForm->addTagOpen('div', ++$currElemPos)->addClass('current');

        // current item
        if ($active && $active === 'and') {
            $currentLabel = $this->getLang('search_all_tags');
            $toggler->addClass('changed');
        } else {
            $currentLabel = $this->getLang('search_any_tag');
        }

        $searchForm->addHTML($currentLabel, ++$currElemPos);
        $searchForm->addTagClose('div', ++$currElemPos);

        // options
        $options = [
            'or' => $this->getLang('search_any_tag'),
            'and' => $this->getLang('search_all_tags'),
            ];
        $searchForm->addTagOpen('ul', ++$currElemPos)->attr('aria-expanded', 'false');
        foreach ($options as $key => $label) {
            $listItem = $searchForm->addTagOpen('li', ++$currElemPos);
            if ($active && $key === $active) {
                $listItem->addClass('active');
            }
            $link = $this->getSettingsLink($label, 'tagging-logic', $key);
            $searchForm->addHTML($link, ++$currElemPos);
            $searchForm->addTagClose('li', ++$currElemPos);
        }
        $searchForm->addTagClose('ul', ++$currElemPos);

        $searchForm->addTagClose('div', ++$currElemPos);

        // restore query with tags in the search form
        if ($this->tagFilter) {
            /** @var \dokuwiki\Form\InputElement $q */
            $q = $searchForm->getElementAt($searchForm->findPositionByAttribute('name', 'q'));
            $q->val($this->originalQuery);
        }
    }

    /**
     * Extracts tags from query and temporarily removes them
     * to prevent running fulltext search on tags as simple terms.
     *
     * @param Doku_Event $event
     * @param $param
     */
    public function setupTagSearch(Doku_Event $event, $param)
    {
        if ($event->data !== 'search') {
            return;
        }

        // allTagsByPage will be accessed by individual search results in SEARCH_RESULT_FULLPAGE event
        // and when displaying tag suggestions
        /** @var helper_plugin_tagging $hlp */
        $hlp = plugin_load('helper', 'tagging');
        $this->allTagsByPage = $hlp->getAllTagsByPage();

        global $QUERY;
        if (strpos($QUERY, '#') === false) {
            return;
        }

        $this->originalQuery = $QUERY;

        // get (hash)tags from query
        preg_match_all('/(?:#)(\w+)/u', $QUERY, $matches);
        if (isset($matches[1])) {
            $this->tagFilter += array_map([$hlp, 'cleanTag'], $matches[1]);
        }

        // remove tags from query before search is executed
        $this->removeTagsFromQuery($QUERY);
    }

    /**
     * If tags are found in query, the results are filtered,
     * or, with an empty query, tag search results are returned.
     *
     * @param Doku_Event $event
     * @param $param
     */
    public function filterSearchResults(Doku_Event $event, $param)
    {
        if (!$this->tagFilter) {
            return;
        }

        /** @var helper_plugin_tagging $hlp */
        $hlp = plugin_load('helper', 'tagging');

        // search for tagged pages
        $pages = $hlp->searchPages($this->tagFilter);
        if (!$pages) {
            $event->result = [];
            return;
        }

        // tag search only, without additional terms
        if (!trim($event->data['query'])) {
            $event->result = $pages;
        }

        // apply filter
        $tagged = array_keys($pages);
        foreach ($event->result as $id => $count) {
            if (!in_array($id, $tagged)) {
                unset($event->result[$id]);
            }
        }
    }

    /**
     * Add tag links to all search results
     *
     * @param Doku_Event $event
     * @param $param
     */
    public function tagResults(Doku_Event $event, $param)
    {
        $page = $event->data['page'];
        $tags = $this->allTagsByPage[$page] ?: null;
        if ($tags) {
            foreach ($tags as $tag) {
                $event->data['resultHeader'][] = $this->getSettingsLink('#' . $tag, 'q', '#' . $tag);
            }
        }
    }

    /**
     * Show tags that are similar to the terms used in search
     *
     * @param Doku_Event $event
     * @param $param
     */
    public function echo_searchresults(Doku_Event $event, $param) {
        global $ACT;
        global $QUERY;

        if ($ACT !== 'search') {
            return;
        }

        /** @var helper_plugin_tagging $hlp */
        $hlp = plugin_load('helper', 'tagging');

        $terms = $hlp->extractFromQuery(ft_queryParser(idx_get_indexer(), $QUERY));
        if (!$terms) {
            return;
        }

        $allTags = [];
        foreach ($this->allTagsByPage as $page => $tags) {
            $allTags = array_merge($allTags, $tags);
        }
        $allTags = array_unique($allTags);

        $suggestedTags = [];
        foreach ($terms as $term) {
            $term = str_replace('*', '', $term);
            $suggestedTags = array_merge($suggestedTags, preg_grep("/$term/i", $allTags));
        }
        sort($suggestedTags);

        if (!$suggestedTags) {
            $this->originalQuery && $this->restoreSearchQuery();
            return;
        }

        // create output HTML: tag search links
        $results = '<div class="search_quickresult">';
        $results .= '<h2>' . $this->getLang('search_suggestions')  .'</h2>';
        $results .= '<ul class="search_quickhits">';

        foreach ($suggestedTags as $tag) {
            $results .= '<li><div class="li">';
            $results .= $this->getSettingsLink('#' . $tag, 'q', '#' . $tag);
            $results .= '</div></li>';
        }
        $results .= '</ul>';
        $results .= '<div class="clearer"></div>';
        $results .= '</div>';

        if (preg_match('/<div class="nothing">.*?<\/div>/', $event->data)) {
            // there are no other hits, replace the nothing found
            $event->data = preg_replace('/<div class="nothing">.*?<\/div>/', $results, $event->data, 1);
        } elseif (preg_match('/(<\/h2>)/', $event->data)) {
            // insert it right before second level headline
            $event->data = preg_replace('/(<h2>)/', $results . "\\1\n", $event->data, 1);
        } else {
            // unclear what happened, let's just append
            $event->data .= $results;
        }

        // all done, finally restore the original query
        $this->restoreSearchQuery();
    }

    /**
     * Updates tagging database after a page has been moved/renamed by the move plugin
     *
     * @param Doku_Event $event
     * @param $param
     */
    public function update_moved_page(Doku_Event $event, $param)
    {
        $src = $event->data['src_id'];
        $dst = $event->data['dst_id'];

        /** @var helper_plugin_tagging $hlp */
        $hlp = plugin_load('helper', 'tagging');
        $hlp->renamePage($src, $dst);
    }

    /**
     * Returns a link that includes all parameters set by inbuilt search tools
     * and an optional additional parameter.
     * If the passed parameter is q, its value will be REPLACED.
     *
     * @param string $label
     * @param string $param
     * @param string $value
     * @return string
     */
    protected function getSettingsLink($label, $param = '', $value = '')
    {
        global $QUERY;

        $Indexer = idx_get_indexer();
        $parsedQuery = ft_queryParser($Indexer, $QUERY);
        $searchState = new \dokuwiki\Ui\SearchState($parsedQuery);
        $linkTag =  $searchState->getSearchLink($label);

        // manipulate the link string because there is yet no way for inbuilt search to allow plugins
        // to extend search queries
        if ($param === '') {
            return $linkTag;
        } elseif ($param === 'q') {
            return preg_replace('/q=[^&\'" ]*/', 'q=' . urlencode($value), $linkTag);
        }
        // FIXME current links have a strange format where href is set in single quotes and followed by a space so preg_replace would make more sense
        return str_replace("' >", '&' .$param . '=' . $value ."'> ", $linkTag);
    }

    /**
     * Remove tags from query
     *
     * @param string $q
     * @return string|string[]|null
     */
    protected function removeTagsFromQuery(&$q)
    {
        $q = preg_replace('/#\w+/u', '', $q);
    }

    /**
     * Restore original query on exit
     */
    protected function restoreSearchQuery()
    {
        global $QUERY;
        $QUERY = $this->originalQuery;
    }
}

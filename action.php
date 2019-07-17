<?php

if (!defined('DOKU_INC')) {
    die();
}

class action_plugin_tagging extends DokuWiki_Action_Plugin {
    /**
     * Register handlers
     */
    function register(Doku_Event_Handler $controller) {
        $controller->register_hook(
            'TPL_CONTENT_DISPLAY', 'BEFORE', $this,
            'echo_searchresults'
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

        // set active setting
        $active = '';
        if ($INPUT->has('taggings')) {
            $active = $INPUT->str('taggings');
        }
        $searchForm->setHiddenField('taggings', $active);

        $searchForm->addTagOpen('div', ++$currElemPos)->addClass('toggle')->attr('aria-haspopup', 'true');

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
            $link = $this->getSettingsLink($label, 'taggings', $key);
            $searchForm->addHTML($link, ++$currElemPos);
            $searchForm->addTagClose('li', ++$currElemPos);
        }
        $searchForm->addTagClose('ul', ++$currElemPos);

        $searchForm->addTagClose('div', ++$currElemPos);
    }
    /**
     * Show tagged pages on searches
     *
     * @param $event
     * @param $param
     */
    public function echo_searchresults(Doku_Event $event, $param) {
        global $ACT;
        global $QUERY;
        global $INPUT;

        if ($ACT !== 'search') {
            return;
        }

        /** @var helper_plugin_tagging $hlp */
        $hlp = plugin_load('helper', 'tagging');

        // parse the search query and use the first found word as term
        $terms = ft_queryParser(idx_get_indexer(), $QUERY);

        $tag = $hlp->getTags($terms);
        if (!$tag) {
            return;
        }

        // use dummy filter instead of parsing the query into an intermediate format;
        // the query builder will do the parsing itself
        $pages = $hlp->findItems([],'pid');
        if (!count($pages)) {
            return;
        }

        // create output HTML
        // format tag search terms
        $operator = ($INPUT->has('taggings') && $INPUT->str('taggings') === 'and') ? $this->getLang('search_all_label') : $this->getLang('search_any_label');
        $tagInfo = implode(' ' . $operator . ' ', $tag);

        $results = '<div class="search_quickresult">';
        $results .= '<h2>' . $this->getLang('search_section_title') . ' ' . hsc($tagInfo) . '' . '</h2>';
        $results .= '<ul class="search_quickhits">';
        global $ID;
        $oldID = $ID;
        foreach ($pages as $page => $cnt) {
            $ID = $page;
            // skip nonexistent pages
            if (!page_exists($ID)) continue;
            $results .= '<li><div class="li">';
            $results .= html_wikilink($page);
            $results .= '</div></li>';
        }
        $ID = $oldID;
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
     * Returns filter array constructed from search query terms
     *
     * @param array $terms
     * @param array $tag
     * @return array
     */
    public function getSearchFilter($terms, $tag)
    {
        global $INPUT;
        $searchType = ($INPUT->has('taggings') && $INPUT->str('taggings') === 'and') ? 'andtag' : 'ortag';
        $filter = [$searchType => $tag];
        if (isset($terms['ns'][0])) {
            $filter['pid'] = $terms['ns'][0];
            if (substr($filter['pid'], -1) !== ':') {
                $filter['pid'] .= ':';
            }
            $filter['pid'] .= '*';
        }
        if (isset($terms['notns'][0])) {
            foreach ($terms['notns'] as &$notns) {
                if (substr($notns, -1) !== ':') {
                    $notns .= ':';
                }
                $notns .= '*';
            }
            $filter['notns'] = $terms['notns'];
        }
        return $filter;
    }

    /**
     * Returns a link that includes all parameters set by inbuilt search tools
     *
     * @param string $label
     * @param string $param
     * @param string $value
     * @return string
     */
    protected function getSettingsLink($label, $param, $value)
    {
        global $QUERY;
        $Indexer = idx_get_indexer();
        $parsedQuery = ft_queryParser($Indexer, $QUERY);
        $searchState = new \dokuwiki\Ui\SearchState($parsedQuery);
        $linkTag =  $searchState->getSearchLink($label);

        // manipulate the link string because there is yet no way for inbuilt search to accept plugins extending search queries
        // FIXME current links have a strange format where href is set in single quotes and followed by a space so preg_replace would make more sense
        return str_replace("' >", '&' .$param . '=' . $value ."'> ", $linkTag);
    }
}

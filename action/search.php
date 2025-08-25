<?php

use dokuwiki\Extension\ActionPlugin;
use dokuwiki\Extension\EventHandler;
use dokuwiki\Extension\Event;
use dokuwiki\Ui\SearchState;
use dokuwiki\Form\InputElement;

/**
 * Class action_plugin_tagging_search
 */
class action_plugin_tagging_search extends ActionPlugin
{
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
     *
     * @param EventHandler $controller
     */
    public function register(EventHandler $controller)
    {
        $controller->register_hook(
            'TPL_CONTENT_DISPLAY',
            'BEFORE',
            $this,
            'echo_searchresults'
        );

        $controller->register_hook(
            'TPL_ACT_RENDER',
            'BEFORE',
            $this,
            'setupTagSearchDoku'
        );

        $controller->register_hook(
            'SEARCH_QUERY_FULLPAGE',
            'AFTER',
            $this,
            'filterSearchResults'
        );

        $controller->register_hook(
            'SEARCH_RESULT_FULLPAGE',
            'AFTER',
            $this,
            'tagResults'
        );

        $controller->register_hook(
            'FORM_SEARCH_OUTPUT',
            'BEFORE',
            $this,
            'addSwitchToSearchForm'
        );
    }

    /**
     * Add AND/OR switch to advanced search tools
     *
     * @param Event $event
     * @param            $param
     */
    public function addSwitchToSearchForm(Event $event, $param)
    {
        global $INPUT;

        /* @var dokuwiki\Form\Form $searchForm */
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
        if ($active === 'and') {
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
            /** @var InputElement $q */
            $q = $searchForm->getElementAt($searchForm->findPositionByAttribute('name', 'q'));
            $q->val($this->originalQuery);
        }
    }

    /**
     * Extracts tags from query and temporarily removes them
     * to prevent running fulltext search on tags as simple terms.
     *
     * @param Event $event
     * @param $param
     */
    public function setupTagSearchDoku(Event $event, $param)
    {
        if ($event->data !== 'search' || !plugin_isdisabled('elasticsearch')) {
            return;
        }

        // allTagsByPage will be accessed by individual search results in SEARCH_RESULT_FULLPAGE event
        // and when displaying tag suggestions
        /** @var helper_plugin_tagging $hlp */
        $hlp = plugin_load('helper', 'tagging');
        $this->allTagsByPage = $hlp->getAllTagsByPage();

        global $QUERY;
        if (!str_contains($QUERY, '#')) {
            return;
        }

        $this->originalQuery = $QUERY;

        // get (hash)tags from query
        preg_match_all('/(?:#)(\w+)/u', $QUERY, $matches);
        if (isset($matches[1])) {
            $this->tagFilter += array_map([$hlp, 'cleanTag'], $matches[1]);
        }

        // remove tags from query before search is executed
        self::removeTagsFromQuery($QUERY);
    }

    /**
     * If tags are found in query, the results are filtered,
     * or, with an empty query, tag search results are returned.
     *
     * @param Event $event
     * @param $param
     */
    public function filterSearchResults(Event $event, $param)
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
     * @param Event $event
     * @param $param
     */
    public function tagResults(Event $event, $param)
    {
        $page = $event->data['page'];
        $tags = $this->allTagsByPage[$page] ?? null;
        if ($tags) {
            foreach ($tags as $tag) {
                $event->data['resultHeader'][] = $this->getSettingsLink('#' . $tag, 'q', '#' . $tag);
            }
        }
    }

    /**
     * Show tags that are similar to the terms used in search
     *
     * @param Event $event
     * @param $param
     */
    public function echo_searchresults(Event $event, $param)
    {
        global $ACT;
        global $QUERY;

        if ($ACT !== 'search') {
            return;
        }

        if (!plugin_isdisabled('elasticsearch')) return;

        /** @var helper_plugin_tagging $hlp */
        $hlp = plugin_load('helper', 'tagging');

        $terms = $hlp->extractFromQuery(ft_queryParser(idx_get_indexer(), $QUERY));
        if (!$terms) {
            return;
        }

        $allTags = [];
        foreach ($this->allTagsByPage as $tags) {
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
        $results .= '<h2>' . $this->getLang('search_suggestions')  . '</h2>';
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
     * Remove tags from query
     *
     * @param string $q
     */
    public static function removeTagsFromQuery(&$q)
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
        $searchState = new SearchState($parsedQuery);
        $linkTag =  $searchState->getSearchLink($label);

        // manipulate the link string because there is yet no way for inbuilt search to allow plugins
        // to extend search queries
        if ($param === '') {
            return $linkTag;
        } elseif ($param === 'q') {
            return preg_replace('/q=[^&\'" ]*/', 'q=' . urlencode($value), $linkTag);
        }
        // FIXME current links have a strange format where href is set in single quotes
        // and followed by a space so preg_replace would make more sense
        return str_replace("' >", '&' . $param . '=' . $value . "'> ", $linkTag);
    }
}

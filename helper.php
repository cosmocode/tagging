<?php

use dokuwiki\Form\Form;

/**
 * Tagging Plugin (hlper component)
 *
 * @license GPL 2
 */
class helper_plugin_tagging extends DokuWiki_Plugin {

    /**
     * Gives access to the database
     *
     * Initializes the SQLite helper and register the CLEANTAG function
     *
     * @return helper_plugin_sqlite|bool false if initialization fails
     */
    public function getDB() {
        static $db = null;
        if ($db !== null) {
            return $db;
        }

        /** @var helper_plugin_sqlite $db */
        $db = plugin_load('helper', 'sqlite');
        if ($db === null) {
            msg('The tagging plugin needs the sqlite plugin', -1);

            return false;
        }
        $db->init('tagging', __DIR__ . '/db/');
        $db->create_function('CLEANTAG', array($this, 'cleanTag'), 1);
        $db->create_function('GROUP_SORT',
            function ($group, $newDelimiter) {
                $ex = array_filter(explode(',', $group));
                sort($ex);

                return implode($newDelimiter, $ex);
            }, 2);
        $db->create_function('GET_NS', 'getNS', 1);

        return $db;
    }

    /**
     * Return the user to use for accessing tags
     *
     * Handles the singleuser mode by returning 'auto' as user. Returnes false when no user is logged in.
     *
     * @return bool|string
     */
    public function getUser() {
        if (!isset($_SERVER['REMOTE_USER'])) {
            return false;
        }
        if ($this->getConf('singleusermode')) {
            return 'auto';
        }

        return $_SERVER['REMOTE_USER'];
    }

    /**
     * Canonicalizes the tag to its lower case nospace form
     *
     * @param $tag
     *
     * @return string
     */
    public function cleanTag($tag) {
        $tag = str_replace(array(' ', '-', '_', '#'), '', $tag);
        $tag = utf8_strtolower($tag);

        return $tag;
    }

    /**
     * Canonicalizes the namespace, remove the first colon and add glob
     *
     * @param $namespace
     *
     * @return string
     */
    public function globNamespace($namespace) {
        return cleanId($namespace) . '*';
    }

    /**
     * Create or Update tags of a page
     *
     * Uses the translation plugin to store the language of a page (if available)
     *
     * @param string $id The page ID
     * @param string $user
     * @param array  $tags
     *
     * @return bool|SQLiteResult
     */
    public function replaceTags($id, $user, $tags) {
        global $conf;
        /** @var helper_plugin_translation $trans */
        $trans = plugin_load('helper', 'translation');
        if ($trans) {
            $lang = $trans->realLC($trans->getLangPart($id));
        } else {
            $lang = $conf['lang'];
        }

        $db = $this->getDB();
        $db->query('BEGIN TRANSACTION');
        $queries = array(array('DELETE FROM taggings WHERE pid = ? AND tagger = ?', $id, $user));
        foreach ($tags as $tag) {
            $queries[] = array('INSERT INTO taggings (pid, tagger, tag, lang) VALUES(?, ?, ?, ?)', $id, $user, $tag, $lang);
        }

        foreach ($queries as $query) {
            if (!call_user_func_array(array($db, 'query'), $query)) {
                $db->query('ROLLBACK TRANSACTION');

                return false;
            }
        }

        return $db->query('COMMIT TRANSACTION');
    }

    /**
     * Get a list of Tags or Pages matching search criteria
     *
     * @param array  $filter What to search for array('field' => 'searchterm')
     * @param string $type   What field to return 'tag'|'pid'
     * @param int    $limit  Limit to this many results, 0 for all
     *
     * @return array associative array in form of value => count
     */
    public function findItems($filter, $type, $limit = 0) {

        global $INPUT;

        /** @var helper_plugin_tagging_querybuilder $queryBuilder */
        $queryBuilder = new \helper_plugin_tagging_querybuilder();

        $queryBuilder->setField($type);
        $queryBuilder->setLimit($limit);
        $queryBuilder->setTags($this->extractFromQuery($filter));
        if (isset($filter['ns'])) $queryBuilder->includeNS($filter['ns']);
        if (isset($filter['notns'])) $queryBuilder->excludeNS($filter['notns']);
        if (isset($filter['tagger'])) $queryBuilder->setTagger($filter['tagger']);
        if (isset($filter['pid'])) $queryBuilder->setPid($filter['pid']);

        return $this->queryDb($queryBuilder->getQuery());

    }

    /**
     * Constructs the URL to search for a tag
     *
     * @param string $tag
     * @param string $ns
     *
     * @return string
     */
    public function getTagSearchURL($tag, $ns = '') {
        $ret = '?do=search&sf=1&q=' . rawurlencode('#' . $this->cleanTag($tag));
        if ($ns) {
            $ret .= rawurlencode(' @' . $ns);
        }

        return $ret;
    }

    /**
     * Calculates the size levels for the given list of clouds
     *
     * Automatically determines sensible tresholds
     *
     * @param array $tags list of tags => count
     * @param int   $levels
     *
     * @return mixed
     */
    public function cloudData($tags, $levels = 10) {
        $min = min($tags);
        $max = max($tags);

        // calculate tresholds
        $tresholds = array();
        for ($i = 0; $i <= $levels; $i++) {
            $tresholds[$i] = pow($max - $min + 1, $i / $levels) + $min - 1;
        }

        // assign weights
        foreach ($tags as $tag => $cnt) {
            foreach ($tresholds as $tresh => $val) {
                if ($cnt <= $val) {
                    $tags[$tag] = $tresh;
                    break;
                }
                $tags[$tag] = $levels;
            }
        }

        return $tags;
    }

    /**
     * Display a tag cloud
     *
     * @param array    $tags   list of tags => count
     * @param string   $type   'tag'
     * @param Callable $func   The function to print the link (gets tag and ns)
     * @param bool     $wrap   wrap cloud in UL tags?
     * @param bool     $return returnn HTML instead of printing?
     * @param string   $ns     Add this namespace to search links
     *
     * @return string
     */
    public function html_cloud($tags, $type, $func, $wrap = true, $return = false, $ns = '') {
        global $INFO;

        $hidden_str = $this->getConf('hiddenprefix');
        $hidden_len = strlen($hidden_str);

        $ret = '';
        if ($wrap) {
            $ret .= '<ul class="tagging_cloud clearfix">';
        }
        if (count($tags) === 0) {
            // Produce valid XHTML (ul needs a child)
            $this->setupLocale();
            $ret .= '<li><div class="li">' . $this->lang['js']['no' . $type . 's'] . '</div></li>';
        } else {
            $tags = $this->cloudData($tags);
            foreach ($tags as $val => $size) {
                // skip hidden tags for users that can't edit
                if ($type === 'tag' and
                    $hidden_len and
                    substr($val, 0, $hidden_len) == $hidden_str and
                    !($this->getUser() && $INFO['writable'])
                ) {
                    continue;
                }

                $ret .= '<li class="t' . $size . '"><div class="li">';
                $ret .= call_user_func($func, $val, $ns);
                $ret .= '</div></li>';
            }
        }
        if ($wrap) {
            $ret .= '</ul>';
        }
        if ($return) {
            return $ret;
        }
        echo $ret;

        return '';
    }

    /**
     * Display a List of Page Links
     *
     * @param array    $pids   list of pids => count
     * @return string
     */
    public function html_page_list($pids) {
        $ret = '<div class="search_quickresult">';
        $ret .= '<ul class="search_quickhits">';

        if (count($pids) === 0) {
            // Produce valid XHTML (ul needs a child)
            $ret .= '<li><div class="li">' . $this->lang['js']['nopages'] . '</div></li>';
        } else {
            foreach (array_keys($pids) as $val) {
                $ret .= '<li><div class="li">';
                $ret .= html_wikilink(":$val");
                $ret .= '</div></li>';
            }
        }

        $ret .= '</ul>';
        $ret .= '</div>';
        $ret .= '<div class="clearer"></div>';

        return $ret;
    }

    /**
     * Get the link to a search for the given tag
     *
     * @param string $tag search for this tag
     * @param string $ns  limit search to this namespace
     *
     * @return string
     */
    protected function linkToSearch($tag, $ns = '') {
        return '<a href="' . hsc($this->getTagSearchURL($tag, $ns)) . '">' . $tag . '</a>';
    }

    /**
     * Display the Tags for the current page and prepare the tag editing form
     *
     * @param bool $print Should the HTML be printed or returned?
     *
     * @return string
     */
    public function tpl_tags($print = true) {
        global $INFO;
        global $lang;

        $filter = array('pid' => $INFO['id']);
        if ($this->getConf('singleusermode')) {
            $filter['tagger'] = 'auto';
        }

        $tags = $this->findItems($filter, 'tag');

        $ret = '';

        $ret .= '<div class="plugin_tagging_edit">';
        $ret .= $this->html_cloud($tags, 'tag', array($this, 'linkToSearch'), true, true);

        if ($this->getUser() && $INFO['writable']) {
            $lang['btn_tagging_edit'] = $lang['btn_secedit'];
            $ret .= '<div id="tagging__edit_buttons_group">';
            $ret .= html_btn('tagging_edit', $INFO['id'], '', array());
            if (auth_isadmin()) {
                $ret .= '<label>'
                    . $this->getLang('toggle admin mode')
                    . '<input type="checkbox" id="tagging__edit_toggle_admin" /></label>';
            }
            $ret .= '</div>';
            $form = new dokuwiki\Form\Form();
            $form->id('tagging__edit');
            $form->setHiddenField('tagging[id]', $INFO['id']);
            $form->setHiddenField('call', 'plugin_tagging_save');
            $tags = $this->findItems(array(
                'pid'    => $INFO['id'],
                'tagger' => $this->getUser(),
            ), 'tag');
            $form->addTextarea('tagging[tags]')
                ->val(implode(', ', array_keys($tags)))
                ->addClass('edit')
                ->attr('rows', 4);
            $form->addButton('', $lang['btn_save'])->id('tagging__edit_save');
            $form->addButton('', $lang['btn_cancel'])->id('tagging__edit_cancel');
            $ret .= $form->toHTML();
        }
        $ret .= '</div>';

        if ($print) {
            echo $ret;
        }

        return $ret;
    }

    /**
     * @param string $namespace empty for entire wiki
     *
     * @param string $order_by
     * @param bool $desc
     * @param array $filters
     * @return array
     */
    public function getAllTags($namespace = '', $order_by = 'tid', $desc = false, $filters = []) {
        $order_fields = array('pid', 'tid', 'taggers', 'ns', 'count');
        if (!in_array($order_by, $order_fields)) {
            msg('cannot sort by ' . $order_by . ' field does not exists', -1);
            $order_by = 'tag';
        }

        list($having, $params) = $this->getFilterSql($filters);

        $db = $this->getDB();

        $query = 'SELECT    "pid",
                            CLEANTAG("tag") AS "tid",
                            GROUP_SORT(GROUP_CONCAT("tagger"), \', \') AS "taggers",
                            GROUP_SORT(GROUP_CONCAT(GET_NS("pid")), \', \') AS "ns",
                            GROUP_SORT(GROUP_CONCAT("pid"), \', \') AS "pids",
                            COUNT(*) AS "count"
                        FROM "taggings"
                        WHERE "pid" GLOB ? AND GETACCESSLEVEL(pid) >= ' . AUTH_READ
                        . ' GROUP BY "tid"';
        $query .= $having;
        $query .=      'ORDER BY ' . $order_by;
        if ($desc) {
            $query .= ' DESC';
        }

        array_unshift($params, $this->globNamespace($namespace));
        $res = $db->query($query, $params);

        return $db->res2arr($res);
    }

    /**
     * Get all pages with tags and their tags
     *
     * @return array ['pid' => ['tag1','tag2','tag3']]
     */
    public function getAllTagsByPage() {
        $query = '
        SELECT pid, GROUP_CONCAT(tag) AS tags
        FROM taggings
        GROUP BY pid
        ';
        $db = $this->getDb();
        $res = $db->query($query);
        return array_map(
            function ($i) {
                return explode(',', $i);
            },
            array_column($db->res2arr($res), 'tags', 'pid')
        );
    }

    /**
     * Renames a tag
     *
     * @param string $formerTagName
     * @param string $newTagNames
     */
    public function renameTag($formerTagName, $newTagNames) {

        if (empty($formerTagName) || empty($newTagNames)) {
            msg($this->getLang("admin enter tag names"), -1);
            return;
        }

        $keepFormerTag = false;

        // enable splitting tags on rename
        $newTagNames = array_map(function ($tag) {
            return $this->cleanTag($tag);
        }, explode(',', $newTagNames));

        $db = $this->getDB();

        // non-admins can rename only their own tags
        if (!auth_isadmin()) {
            $queryTagger =' AND tagger = ?';
            $tagger = $this->getUser();
        } else {
            $queryTagger = '';
            $tagger = '';
        }

        $insertQuery = 'INSERT INTO taggings ';
        $insertQuery .= 'SELECT pid, ?, tagger, lang FROM taggings';
        $where = ' WHERE CLEANTAG(tag) = ?';
        $where .= ' AND GETACCESSLEVEL(pid) >= ' . AUTH_EDIT;
        $where .= $queryTagger;

        $db->query('BEGIN TRANSACTION');

        // insert new tags first
        foreach ($newTagNames as $newTag) {
            if ($newTag === $this->cleanTag($formerTagName)) {
                $keepFormerTag = true;
                continue;
            }
            $params = [$newTag, $this->cleanTag($formerTagName)];
            if ($tagger) array_push($params, $tagger);
            $res = $db->query($insertQuery . $where, $params);
            if ($res === false) {
                $db->query('ROLLBACK TRANSACTION');
                return;
            }
            $db->res_close($res);
        }

        // finally delete the renamed tags
        if (!$keepFormerTag) {
            $deleteQuery = 'DELETE FROM taggings';
            $params = [$this->cleanTag($formerTagName)];
            if ($tagger) array_push($params, $tagger);
            if ($db->query($deleteQuery . $where, $params) === false) {
                $db->query('ROLLBACK TRANSACTION');
                return;
            }
        }

        $db->query('COMMIT TRANSACTION');

        msg($this->getLang("admin renamed"), 1);

        return;
    }

    /**
     * Rename or delete a tag for all users
     *
     * @param string $pid
     * @param string $formerTagName
     * @param string $newTagName
     *
     * @return array
     */
    public function modifyPageTag($pid, $formerTagName, $newTagName) {

        $db = $this->getDb();

        $res = $db->query(
            'SELECT pid FROM taggings WHERE CLEANTAG(tag) = ? AND pid = ?',
            $this->cleanTag($formerTagName),
            $pid
        );
        $check = $db->res2arr($res);

        if (empty($check)) {
            return array(true, $this->getLang('admin tag does not exists'));
        }

        if (empty($newTagName)) {
            $res = $db->query(
                'DELETE FROM taggings WHERE pid = ? AND CLEANTAG(tag) = ?',
                $pid,
                $this->cleanTag($formerTagName)
            );
        } else {
            $res = $db->query(
                'UPDATE taggings SET tag = ? WHERE pid = ? AND CLEANTAG(tag) = ?',
                $newTagName,
                $pid,
                $this->cleanTag($formerTagName)
            );
        }
        $db->res2arr($res);

        return array(false, $this->getLang('admin renamed'));
    }

    /**
     * Deletes a tag
     *
     * @param array  $tags
     * @param string $namespace current namespace context as in getAllTags()
     */
    public function deleteTags($tags, $namespace = '') {
        if (empty($tags)) {
            return;
        }

        $namespace = cleanId($namespace);

        $db = $this->getDB();

        $queryBody = 'FROM taggings WHERE pid GLOB ? AND (' .
            implode(' OR ', array_fill(0, count($tags), 'CLEANTAG(tag) = ?')) . ')';
        $args = array_map(array($this, 'cleanTag'), $tags);
        array_unshift($args, $this->globNamespace($namespace));

        // non-admins can delete only their own tags
        if (!auth_isadmin()) {
            $queryBody .= ' AND tagger = ?';
            array_push($args, $this->getUser());
        }

        $affectedPagesQuery= 'SELECT DISTINCT pid ' . $queryBody;
        $resAffectedPages = $db->query($affectedPagesQuery, $args);
        $numAffectedPages = count($resAffectedPages->fetchAll());

        $deleteQuery = 'DELETE ' . $queryBody;
        $db->query($deleteQuery, $args);

        msg(sprintf($this->getLang("admin deleted"), count($tags), $numAffectedPages), 1);
    }

    /**
     * Delete taggings of nonexistent pages
     */
    public function deleteInvalidTaggings()
    {
        $db = $this->getDB();
        $query = 'DELETE    FROM "taggings"
                            WHERE NOT PAGEEXISTS(pid)
                 ';
        $res = $db->query($query);
        $db->res_close($res);
    }

    /**
     * Updates tags with a new page name
     *
     * @param string $oldName
     * @param string $newName
     */
    public function renamePage($oldName, $newName) {
        $db = $this->getDB();
        $db->query('UPDATE taggings SET pid = ? WHERE pid = ?', $newName, $oldName);
    }

    /**
     * Extracts tags from search query
     *
     * @param array $parsedQuery
     * @return array
     */
    public function extractFromQuery($parsedQuery)
    {
        $tags = [];
        if (isset($parsedQuery['phrases'][0])) {
            $tags = $parsedQuery['phrases'];
        } elseif (isset($parsedQuery['and'][0])) {
            $tags = $parsedQuery['and'];
        } elseif (isset($parsedQuery['tag'])) {
            // handle autocomplete call
            $tags[] = $parsedQuery['tag'];
        }
        return $tags;
    }

    /**
     * Search for tagged pages
     *
     * @param array $tagFiler
     * @return array
     */
    public function searchPages($tagFiler)
    {
        global $INPUT;
        global $QUERY;
        $parsedQuery = ft_queryParser(new Doku_Indexer(), $QUERY);

        /** @var helper_plugin_tagging_querybuilder $queryBuilder */
        $queryBuilder = new \helper_plugin_tagging_querybuilder();

        $queryBuilder->setField('pid');
        $queryBuilder->setTags($tagFiler);
        $queryBuilder->setLogicalAnd($INPUT->str('tagging-logic') === 'and');
        if (isset($parsedQuery['ns'])) $queryBuilder->includeNS($parsedQuery['ns']);
        if (isset($parsedQuery['notns'])) $queryBuilder->excludeNS($parsedQuery['notns']);
        if (isset($parsedQuery['tagger'])) $queryBuilder->setTagger($parsedQuery['tagger']);
        if (isset($parsedQuery['pid'])) $queryBuilder->setPid($parsedQuery['pid']);

        return $this->queryDb($queryBuilder->getPages());
    }

    /**
     * Syntax to allow users to manage tags on regular pages, respects ACLs
     * @param string $ns
     * @return string
     */
    public function manageTags($ns)
    {
        global $INPUT;

        $this->setDefaultSort();

        // initially set namespace filter to what is defined in syntax
        if ($ns && !$INPUT->has('tagging__filters')) {
            $INPUT->set('tagging__filters', ['ns' => $ns]);
        }

        return $this->html_table();
    }

    /**
     * HTML list of tagged pages
     *
     * @param string $tid
     * @return string
     */
    public function getPagesHtml($tid)
    {
        $html = '';

        $db = $this->getDB();
        $sql = 'SELECT pid from taggings where CLEANTAG(tag) = CLEANTAG(?)';
        $res =  $db->query($sql, $tid);
        $pages = $db->res2arr($res);

        if ($pages) {
            $html .= '<ul>';
            foreach ($pages as $page) {
                $pid = $page['pid'];
                $html .= '<li><a href="' . wl($pid) . '" target="_blank">' . $pid . '</li>';
            }
            $html .= '</ul>';
        }

        return $html;
    }

    /**
     * Display tag management table
     */
    public function html_table() {
        global $ID, $INPUT;

        $headers = array(
            array('value' => $this->getLang('admin tag'), 'sort_by' => 'tid'),
            array('value' => $this->getLang('admin occurrence'), 'sort_by' => 'count')
        );

        if (!$this->conf['hidens']) {
            array_push(
                $headers,
                ['value' => $this->getLang('admin namespaces'), 'sort_by' => 'ns']
            );
        }

        array_push($headers,
            array('value' => $this->getLang('admin taggers'), 'sort_by' => 'taggers'),
            array('value' => $this->getLang('admin actions'), 'sort_by' => false)
        );

        $sort = explode(',', $this->getParam('sort'));
        $order_by = $sort[0];
        $desc = false;
        if (isset($sort[1]) && $sort[1] === 'desc') {
            $desc = true;
        }
        $filters = $INPUT->arr('tagging__filters');

        $tags = $this->getAllTags($INPUT->str('filter'), $order_by, $desc, $filters);

        $form = new \dokuwiki\Form\Form();
        // required in admin mode
        $form->setHiddenField('page', 'tagging');
        $form->setHiddenField('id', $ID);
        $form->setHiddenField('[tagging]sort', $this->getParam('sort'));

        /**
         * Actions dialog
         */
        $form->addTagOpen('div')->id('tagging__action-dialog')->attr('style', "display:none;");
        $form->addTagClose('div');

        /**
         * Tag pages dialog
         */
        $form->addTagOpen('div')->id('tagging__taggedpages-dialog')->attr('style', "display:none;");
        $form->addTagClose('div');

        /**
         * Tag management table
         */
        $form->addTagOpen('table')->addClass('inline plugin_tagging');

        $nscol = $this->conf['hidens'] ? '' : '<col class="wide-col"></col>';
        $form->addHTML(
            '<colgroup>
                <col></col>
                <col class="narrow-col"></col>'
                . $nscol .
                '<col></col>
                <col class="narrow-col"></col>
            </colgroup>'
        );

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
                $form->addButtonHTML(
                    "tagging[sort]",
                    $header['value'] . ' ' . inlineSVG(__DIR__ . "/images/$icon.svg"))
                    ->addClass('plugin_tagging sort_button')
                    ->attr('title', $title)
                    ->val($param);
            } else {
                $form->addHTML($header['value']);
            }
            $form->addTagClose('th');
        }
        $form->addTagClose('tr');

        /**
         * Table filters for all sortable columns
         */
        $form->addTagOpen('tr');
        foreach ($headers as $header) {
            $form->addTagOpen('th');
            if ($header['sort_by'] !== false) {
                $field = $header['sort_by'];
                $input = $form->addTextInput("tagging__filters[$field]");
                $input->addClass('full-col');
            }
            $form->addTagClose('th');
        }
        $form->addTagClose('tr');


        foreach ($tags as $taginfo) {
            $tagname = $taginfo['tid'];
            $taggers = $taginfo['taggers'];
            $ns = $taginfo['ns'];
            $pids = explode(',',$taginfo['pids']);

            $form->addTagOpen('tr');
            $form->addHTML('<td>');
            $form->addHTML('<a class="tagslist" href="#" data-tid="' . $taginfo['tid'] . '">');
            $form->addHTML( hsc($tagname) . '</a>');
            $form->addHTML('</td>');
            $form->addHTML('<td>' . $taginfo['count'] . '</td>');
            if (!$this->conf['hidens']) {
                $form->addHTML('<td>' . hsc($ns) . '</td>');
            }
            $form->addHTML('<td>' . hsc($taggers) . '</td>');

            /**
             * action buttons
             */
            $form->addHTML('<td>');

            // check ACLs
            $userEdit = false;
            /** @var \helper_plugin_sqlite $sqliteHelper */
            $sqliteHelper = plugin_load('helper', 'sqlite');
            foreach ($pids as $pid) {
                if ($sqliteHelper->_getAccessLevel($pid) >= AUTH_EDIT) {
                    $userEdit = true;
                    continue;
                }
            }

            if ($userEdit) {
                $form->addButtonHTML(
                    'tagging[actions][rename][' . $taginfo['tid'] . ']',
                    inlineSVG(__DIR__ . '/images/edit.svg'))
                    ->addClass('plugin_tagging action_button')
                    ->attr('data-action', 'rename')
                    ->attr('data-tid', $taginfo['tid']);
                $form->addButtonHTML(
                    'tagging[actions][delete][' . $taginfo['tid'] . ']',
                    inlineSVG(__DIR__ . '/images/delete.svg'))
                    ->addClass('plugin_tagging action_button')
                    ->attr('data-action', 'delete')
                    ->attr('data-tid', $taginfo['tid']);
            }

            $form->addHTML('</td>');
            $form->addTagClose('tr');
        }

        $form->addTagClose('table');
        return '<div class="table">' . $form->toHTML() . '</div>';
    }

    /**
     * Display tag cleaner
     *
     * @return string
     */
    public function html_clean()
    {
        $invalid = $this->getInvalidTaggings();

        if (!$invalid) {
            return '<p><strong>' . $this->getLang('admin no invalid') . '</strong></p>';
        }

        $form = new Form();
        $form->setHiddenField('do', 'admin');
        $form->setHiddenField('page', $this->getPluginName());
        $form->addButton('cmd[clean]', $this->getLang('admin clean'));

        $html = $form->toHTML();

        $html .= '<div class="table"><table class="inline plugin_tagging">';
        $html .= '<thead><tr><th>' .
            $this->getLang('admin nonexistent page') .
            '</th><th>' .
            $this->getLang('admin tags') .
            '</th></tr></thead><tbody>';

        foreach ($invalid as $row) {
            $html .= '<tr><td>' . $row['pid'] . '</td><td>' . $row['tags'] . '</td></tr>';
        }

        $html .= '</tbody></table></div>';

        return $html;
    }

    /**
     * Returns all tagging parameters from the query string
     *
     * @return mixed
     */
    public function getParams()
    {
        global $INPUT;
        return $INPUT->param('tagging', []);
    }

    /**
     * Get a tagging parameter, empty string if not set
     *
     * @param string $name
     * @return mixed
     */
    public function getParam($name)
    {
        $params = $this->getParams();
        if ($params) {
            return $params[$name] ?: '';
        }
    }

    /**
     * Sets a tagging parameter
     *
     * @param string $name
     * @param string|array $value
     */
    public function setParam($name, $value)
    {
        global $INPUT;
        $params = $this->getParams();
        $params = array_merge($params, [$name => $value]);
        $INPUT->set('tagging', $params);
    }

    /**
     * Default sorting by tag id
     */
    public function setDefaultSort()
    {
        if (!$this->getParam('sort')) {
            $this->setParam('sort', 'tid');
        }
    }

    /**
     * Executes the query and returns the results as array
     *
     * @param array $query
     * @return array
     */
    protected function queryDb($query)
    {
        $db = $this->getDB();
        if (!$db) {
            return [];
        }

        $res = $db->query($query[0], $query[1]);
        $res = $db->res2arr($res);

        $ret = [];
        foreach ($res as $row) {
            $ret[$row['item']] = $row['cnt'];
        }
        return $ret;
    }

    /**
     * Construct the HAVING part of the search query
     *
     * @param array $filters
     * @return array
     */
    protected function getFilterSql($filters)
    {
        $having = '';
        $parts = [];
        $params = [];
        $filters = array_filter($filters);
        if (!empty($filters)) {
            $having = ' HAVING ';
            foreach ($filters as $filter => $value) {
                $parts[] = " $filter LIKE ? ";
                $params[] = "%$value%";
            }
            $having .= implode(' AND ', $parts);
        }
        return [$having, $params];
    }

    /**
     * Returns taggings of nonexistent pages
     *
     * @return array
     */
    protected function getInvalidTaggings()
    {
        $db = $this->getDB();
        $query = 'SELECT    "pid",
                            GROUP_CONCAT(CLEANTAG("tag")) AS "tags"
                            FROM "taggings"
                            WHERE NOT PAGEEXISTS(pid)
                            GROUP BY pid
                 ';
        $res = $db->query($query);
        return $db->res2arr($res);
    }
}

<?php
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
                $ex = explode(',', $group);
                sort($ex);

                return implode($newDelimiter, $ex);
            }, 2);

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
        $tag = str_replace(array(' ', '-', '_'), '', $tag);
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
        return cleanId($namespace) . '%';
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
        $db = $this->getDB();
        if (!$db) {
            return array();
        }

        // create WHERE clause
        $where = '1=1';
        foreach ($filter as $field => $value) {
            // compare clean tags only
            if ($field === 'tag') {
                $field = 'CLEANTAG(tag)';
                $q = 'CLEANTAG(?)';
            } else {
                $q = '?';
            }

            if (substr($field, 0, 6) === 'notpid') {
                $field = 'pid';

                // detect LIKE filters
                if ($this->useLike($value)) {
                    $where .= " AND $field NOT LIKE $q";
                } else {
                    $where .= " AND $field != $q";
                }
            } else {
                // detect LIKE filters
                if ($this->useLike($value)) {
                    $where .= " AND $field LIKE $q";
                } else {
                    $where .= " AND $field = $q";
                }
            }
        }
        $where .= ' AND GETACCESSLEVEL(pid) >= ' . AUTH_READ;

        // group and order
        if ($type === 'tag') {
            $groupby = 'CLEANTAG(tag)';
            $orderby = 'CLEANTAG(tag)';
        } else {
            $groupby = $type;
            $orderby = "cnt DESC, $type";
        }

        // limit results
        if ($limit) {
            $limit = " LIMIT $limit";
        } else {
            $limit = '';
        }

        // create SQL
        $sql = "SELECT $type AS item, COUNT(*) AS cnt
                  FROM taggings
                 WHERE $where
              GROUP BY $groupby
              ORDER BY $orderby
                $limit
              ";

        // run query and turn into associative array
        $res = $db->query($sql, array_values($filter));
        $res = $db->res2arr($res);

        $ret = array();
        foreach ($res as $row) {
            $ret[$row['item']] = $row['cnt'];
        }

        return $ret;
    }

    /**
     * Check if the given string is a LIKE statement
     *
     * @param string $value
     *
     * @return bool
     */
    private function useLike($value) {
        return strpos($value, '%') === 0 || strrpos($value, '%') === strlen($value) - 1;
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
        // wrap tag in quotes if non clean
        $ctag = utf8_stripspecials($this->cleanTag($tag));
        if ($ctag != utf8_strtolower($tag)) {
            $tag = '"' . $tag . '"';
        }

        $ret = '?do=search&id=' . rawurlencode($tag);
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
                $ret .= '<label>' . $this->getLang('toggle admin mode') . '<input type="checkbox" id="tagging__edit_toggle_admin" /></label>';
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
            $form->addTextarea('tagging[tags]')->val(implode(', ', array_keys($tags)))->addClass('edit');
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
     * @return array
     */
    public function getAllTags($namespace = '', $order_by = 'tag', $desc = false) {
        $order_fields = array('pid', 'tid', 'orig', 'taggers', 'count');
        if (!in_array($order_by, $order_fields)) {
            msg('cannot sort by ' . $order_by . ' field does not exists', -1);
            $order_by = 'tag';
        }

        $db = $this->getDb();

        $query = 'SELECT    "pid",
                            CLEANTAG("tag") AS "tid",
                            GROUP_SORT(GROUP_CONCAT("tag"), \', \') AS "orig",
                            GROUP_SORT(GROUP_CONCAT("tagger"), \', \') AS "taggers",
                            COUNT(*) AS "count"
                        FROM "taggings"
                        WHERE "pid" LIKE ?
                        GROUP BY "tid"
                        ORDER BY ' . $order_by;
        if ($desc) {
            $query .= ' DESC';
        }

        $res = $db->query($query, $this->globNamespace($namespace));

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
     * @param string $newTagName
     */
    public function renameTag($formerTagName, $newTagName) {

        if (empty($formerTagName) || empty($newTagName)) {
            msg($this->getLang("admin enter tag names"), -1);

            return;
        }

        $db = $this->getDb();

        $res = $db->query('SELECT pid FROM taggings WHERE CLEANTAG(tag) = ?', $this->cleanTag($formerTagName));
        $check = $db->res2arr($res);

        if (empty($check)) {
            msg($this->getLang("admin tag does not exists"), -1);

            return;
        }

        $res = $db->query("UPDATE taggings SET tag = ? WHERE CLEANTAG(tag) = ?", $newTagName, $this->cleanTag($formerTagName));
        $db->res2arr($res);

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

        $res = $db->query('SELECT pid FROM taggings WHERE CLEANTAG(tag) = ? AND pid = ?', $this->cleanTag($formerTagName), $pid);
        $check = $db->res2arr($res);

        if (empty($check)) {
            return array(true, $this->getLang('admin tag does not exists'));
        }

        if (empty($newTagName)) {
            $res = $db->query('DELETE FROM taggings WHERE pid = ? AND CLEANTAG(tag) = ?', $pid, $this->cleanTag($formerTagName));
        } else {
            $res = $db->query('UPDATE taggings SET tag = ? WHERE pid = ? AND CLEANTAG(tag) = ?', $newTagName, $pid, $this->cleanTag($formerTagName));
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

        $queryBody = 'FROM taggings WHERE pid LIKE ? AND (' .
            implode(' OR ', array_fill(0, count($tags), 'CLEANTAG(tag) = ?')) . ')';
        $args = array_map(array($this, 'cleanTag'), $tags);
        array_unshift($args, $this->globNamespace($namespace));


        $affectedPagesQuery= 'SELECT DISTINCT pid ' . $queryBody;
        $resAffectedPages = $db->query($affectedPagesQuery, $args);
        $numAffectedPages = count($resAffectedPages->fetchAll());

        $deleteQuery = 'DELETE ' . $queryBody;
        $db->query($deleteQuery, $args);

        msg(sprintf($this->getLang("admin deleted"), count($tags), $numAffectedPages), 1);
    }
}

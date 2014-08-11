<?php

if(!defined('DOKU_INC')) die();
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
        if(!is_null($db)) {
            return $db;
        }

        /** @var helper_plugin_sqlite $db */
        $db = plugin_load('helper', 'sqlite');
        if(is_null($db)) {
            msg('The tagging plugin needs the sqlite plugin', -1);
            return false;
        }
        $db->init('tagging', dirname(__FILE__) . '/db/');
        $db->create_function('CLEANTAG', array($this, 'cleanTag'), 1);
        return $db;
    }

    /**
     * Canonicalizes the tag to its lower case nospace form
     *
     * @param $tag
     * @return string
     */
    public function cleanTag($tag) {
        $tag = str_replace(' ', '', $tag);
        $tag = utf8_strtolower($tag);
        return $tag;
    }

    public function replaceTags($id, $user, $tags) {
        $db = $this->getDB();
        $db->query('BEGIN TRANSACTION');
        $queries = array(array('DELETE FROM taggings WHERE pid = ? AND tagger = ?', $id, $user));
        foreach($tags as $tag) {
            $queries[] = array('INSERT INTO taggings (pid, tagger, tag) VALUES(?, ?, ?)', $id, $user, $tag);
        }

        foreach($queries as $query) {
            if(!call_user_func_array(array($db, 'query'), $query)) {
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
     * @return array associative array in form of value => count
     */
    public function findItems($filter, $type, $limit=0) {
        $db = $this->getDB();
        if(!$db) return array();

        // create WHERE clause
        $where = '1=1';
        foreach($filter as $field => $value) {
            // compare clean tags only
            if($field === 'tag') {
                $field = 'CLEANTAG(tag)';
                $q     = 'CLEANTAG(?)';
            } else {
                $q = '?';
            }
            // detect LIKE filters
            if($this->useLike($value)) {
                $where .= " AND $field LIKE $q";
            } else {
                $where .= " AND $field = $q";
            }
        }
        // group and order
        if($type == 'tag') {
            $groupby = 'CLEANTAG(tag)';
            $orderby = 'CLEANTAG(tag)';
        } else {
            $groupby = $type;
            $orderby = "cnt DESC, $type";
        }

        // limit results
        if($limit) {
            $limit = " LIMIT $limit";
        }else{
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
        foreach($res as $row) {
            $ret[$row['item']] = $row['cnt'];
        }
        return $ret;
    }

    /**
     * Check if the given string is a LIKE statement
     *
     * @param string $value
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
     * @return string
     */
    public function getTagSearchURL($tag, $ns = '') {
        // wrap tag in quotes if non clean
        $ctag = $this->cleanTag($tag);
        if($ctag != utf8_strtolower($tag)) $tag = '"'.$tag.'"';

        $ret = '?do=search&id=' . rawurlencode($tag);
        if($ns) $ret .= rawurlencode(' @' . $ns);

        return $ret;
    }

    /**
     * Calculates the size levels for the given list of clouds
     *
     * Automatically determines sensible tresholds
     *
     * @param array $tags list of tags => count
     * @param int   $levels
     * @return mixed
     */
    public function cloudData($tags, $levels = 10) {
        $min = min($tags);
        $max = max($tags);

        // calculate tresholds
        $tresholds = array();
        for($i = 0; $i <= $levels; $i++) {
            $tresholds[$i] = pow($max - $min + 1, $i / $levels) + $min - 1;
        }

        // assign weights
        foreach($tags as $tag => $cnt) {
            foreach($tresholds as $tresh => $val) {
                if($cnt <= $val) {
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
     * @return string
     */
    public function html_cloud($tags, $type, $func, $wrap = true, $return = false, $ns = '') {
        $ret = '';
        if($wrap) $ret .= '<ul class="tagging_cloud clearfix">';
        if(count($tags) === 0) {
            // Produce valid XHTML (ul needs a child)
            $this->setupLocale();
            $ret .= '<li><div class="li">' . $this->lang['js']['no' . $type . 's'] . '</div></li>';
        } else {
            $tags = $this->cloudData($tags);
            foreach($tags as $val => $size) {
                $ret .= '<li class="t' . $size . '"><div class="li">';
                $ret .= call_user_func($func, $val, $ns);
                $ret .= '</div></li>';
            }
        }
        if($wrap) $ret .= '</ul>';
        if($return) return $ret;
        echo $ret;
        return '';
    }

    /**
     * Get the link to a search for the given tag
     *
     * @param string $tag search for this tag
     * @param string $ns  limit search to this namespace
     * @return string
     */
    protected function linkToSearch($tag, $ns = '') {
        return '<a href="' . hsc($this->getTagSearchURL($tag, $ns)) . '">' . $tag . '</a>';
    }


    public function tpl_tags() {
        global $INFO;
        global $lang;
        $tags = $this->findItems(array('pid' => $INFO['id']), 'tag');
        echo '<div class="plugin_tagging_edit">';
        $this->html_cloud($tags, 'tag', array($this, 'linkToSearch'));

        if(isset($_SERVER['REMOTE_USER']) && $INFO['writable']) {
            $lang['btn_tagging_edit'] = $lang['btn_secedit'];
            echo html_btn('tagging_edit', $INFO['id'], '', array());
            $form = new Doku_Form(array('id' => 'tagging__edit'));
            $form->addHidden('tagging[id]', $INFO['id']);
            $form->addHidden('call', 'plugin_tagging_save');
            $form->addElement(form_makeTextField('tagging[tags]', implode(', ', array_keys($this->findItems(array('pid' => $INFO['id'], 'tagger' => $_SERVER['REMOTE_USER']), 'tag')))));
            $form->addElement(form_makeButton('submit', 'save', $lang['btn_save'], array('id' => 'tagging__edit_save')));
            $form->addElement(form_makeButton('submit', 'cancel', $lang['btn_cancel'], array('id' => 'tagging__edit_cancel')));
            $form->printForm();
        }
        echo '</div>';
    }

    /**
     * @return array
     */
    public function getAllTags() {

        $db  = $this->getDb();
        $res = $db->query('SELECT pid, tag, tagger FROM taggings ORDER BY tag');

        $tags_tmp = $db->res2arr($res);
        $tags     = array();
        foreach($tags_tmp as $tag) {
            $tid = $this->cleanTag($tag['tag']);

            //$tags[$tid]['pid'][] = $tag['pid'];

            if(isset($tags[$tid]['count'])) {
                $tags[$tid]['count']++;
                $tags[$tid]['tagger'][] = $tag['tagger'];
            } else {
                $tags[$tid]['count']  = 1;
                $tags[$tid]['tagger'] = array($tag['tagger']);
            }
        }
        return $tags;
    }

    /**
     * Renames a tag
     *
     * @param string $formerTagName
     * @param string $newTagName
     */
    public function renameTag($formerTagName, $newTagName) {

        if(empty($formerTagName) || empty($newTagName)) {
            msg($this->getLang("admin enter tag names"), -1);
            return;
        }

        $db = $this->getDb();

        $res   = $db->query('SELECT pid FROM taggings WHERE tag= ?', $formerTagName);
        $check = $db->res2arr($res);

        if(empty($check)) {
            msg($this->getLang("admin tag does not exists"), -1);
            return;
        }

        $res = $db->query("UPDATE taggings SET tag = ? WHERE tag = ?", $newTagName, $formerTagName);
        $db->res2arr($res);

        msg($this->getLang("admin saved"), 1);
        return;
    }

}

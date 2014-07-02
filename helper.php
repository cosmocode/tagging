<?php

if(!defined('DOKU_INC')) die();
class helper_plugin_tagging extends DokuWiki_Plugin {

    /**
     * @return helper_plugin_sqlite
     */
    public function getDB() {
        static $db = null;
        if (!is_null($db)) {
            return $db;
        }

        /** @var helper_plugin_sqlite $db */
        $db = plugin_load('helper', 'sqlite');
        if (is_null($db)) {
            msg('The tagging plugin needs the sqlite plugin', -1);
            return false;
        }
        $db->init('tagging',dirname(__FILE__).'/db/');
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
        foreach ($tags as $tag) {
            $queries[] = array('INSERT INTO taggings (pid, tagger, tag) VALUES(?, ?, ?)', $id, $user, $tag);
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
     * Get a list of Tags or Pages matching a search criteria
     *
     * @param array  $search What to search for array('field' => 'searchterm')
     * @param string $return What field to return 'tag'|'pid'
     * @return array associative array in form of value => count
     * @todo this is a really ugly function. It should be split into separate ones
     */
    public function getTags($search, $return) {
        $where = '1=1';
        foreach($search as $k => $v) {
            if ($k === 'tag') {
                $k = 'CLEANTAG(tag)';
            }

            if ($this->useLike($v)) {
                $where .= " AND $k LIKE";
            } else {
                $where .= " AND $k =";
            }

            if ($k === 'CLEANTAG(tag)') {
                $where .= ' CLEANTAG(?)';
            } else {
                $where .= ' ?';
            }
        }

        if($return == 'tag') {
            $groupby = 'CLEANTAG(tag)';
        } else {
            $groupby = $return;
        }

        $db = $this->getDB();
        $res = $db->query('SELECT ' . $return . ', COUNT(*) ' .
                          'FROM taggings WHERE ' . $where . ' GROUP BY ' . $groupby .
                          ' ORDER BY tag',
                          array_values($search));

        $res = $db->res2arr($res);
        $ret = array();
        foreach ($res as $v) {
            $ret[$v[$return]] = $v['COUNT(*)'];
        }
        return $ret;
    }

    private function useLike($v) {
        return strpos($v, '%') === 0 || strrpos($v, '%') === strlen($v) - 1;
    }

    /**
     * Constructs the URL to search for a tag
     *
     * @param $tag
     * @return string
     */
    public function getTagSearchURL($tag) {
        return '?do=search&id=' . rawurlencode($tag);
    }

    public function cloudData($tags, $levels = 10) {
        $min = min($tags);
        $max = max($tags);

        // calculate tresholds
        $tresholds = array();
        for($i=0; $i<=$levels; $i++){
            $tresholds[$i] = pow($max - $min + 1, $i/$levels) + $min - 1;
        }

        // assign weights
        foreach($tags as $tag => $cnt){
            foreach($tresholds as $tresh => $val){
                if($cnt <= $val){
                    $tags[$tag] = $tresh;
                    break;
                }
                $tags[$tag] = $levels;
            }
        }
        return $tags;
    }

    public function html_cloud($tags, $type, $func, $wrap = true, $return = false) {
        $ret = '';
        if ($wrap) $ret .= '<ul class="tagging_cloud clearfix">';
        if (count($tags) === 0) {
            // Produce valid XHTML (ul needs a child)
            $this->setupLocale();
            $ret .= '<li><div class="li">' . $this->lang['js']['no' . $type . 's'] . '</div></li>';
        } else {
            $tags = $this->cloudData($tags);
            foreach ($tags as $val => $size) {
                $ret .= '<li class="t' . $size . '"><div class="li">';
                $ret .= call_user_func($func, $val);
                $ret .= '</div></li>';
            }
        }
        if ($wrap) $ret .= '</ul>';
        if ($return) return $ret;
        echo $ret;
    }

    protected function linkToSearch($tag) {
        return '<a href="' . hsc($this->getTagSearchURL($tag)) . '">' .
               $tag . '</a>';
    }

    public function tpl_tags() {
        global $ID;
        global $INFO;
        global $lang;
        $tags = $this->getTags(array('pid' => $ID), 'tag');
        $this->html_cloud($tags, 'tag', array($this, 'linkToSearch'));

        if (isset($_SERVER['REMOTE_USER']) && $INFO['writable']) {
            $lang['btn_tagging_edit'] = $lang['btn_secedit'];
            echo html_btn('tagging_edit', $ID, '', array());
            $form = new Doku_Form(array('id' => 'tagging__edit'));
            $form->addHidden('tagging[id]', $ID);
            $form->addHidden('call', 'plugin_tagging_save');
            $form->addElement(form_makeTextField('tagging[tags]', implode(', ', array_keys($this->getTags(array('pid' => $ID, 'tagger' => $_SERVER['REMOTE_USER']), 'tag')))));
            $form->addElement(form_makeButton('submit', 'save', $lang['btn_save'], array('id' => 'tagging__edit_save')));
            $form->addElement(form_makeButton('submit', 'cancel', $lang['btn_cancel'], array('id' => 'tagging__edit_cancel')));
            $form->printForm();
        }
    }

    /**
     * @return array
     */
    public function getAllTags(){

        $db = $this->getDb();
        $res = $db->query('SELECT pid, tag, tagger FROM taggings ORDER BY tag');

        $tags_tmp = $db->res2arr($res);
        $tags = array();
        foreach ($tags_tmp as $tag) {
            $tid = $this->cleanTag($tag['tag']);

            //$tags[$tid]['pid'][] = $tag['pid'];

            if (isset($tags[$tid]['count'])) {
                $tags[$tid]['count']++;
                $tags[$tid]['tagger'][] = $tag['tagger'];
            } else {
                $tags[$tid]['count'] = 1;
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

        $res = $db->query('SELECT pid FROM taggings WHERE tag= ?', $formerTagName);
        $check = $db->res2arr($res);

        if (empty($check)) {
            msg($this->getLang("admin tag does not exists"), -1);
            return;
        }

        $res = $db->query("UPDATE taggings SET tag = ? WHERE tag = ?", $newTagName, $formerTagName);
        $db->res2arr($res);

        msg($this->getLang("admin saved"), 1);
        return;
    }

}

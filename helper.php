<?php

if(!defined('DOKU_INC')) die();
class helper_plugin_tagging extends DokuWiki_Plugin {

    private function getDB() {
        static $db;
        if (!is_null($db)) {
            return $db;
        }

        $db = plugin_load('helper', 'sqlite');
        if (is_null($db)) {
            msg('The tagging plugin needs the sqlite plugin', -1);
            return false;
        }
        if($db->init('tagging',dirname(__FILE__).'/db/')){
            return $db;
        }
        return false;
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

    public function getTags($search, $return) {
        $where = '1=1';
        foreach($search as $k => $v) {
            if (strpos($v, '%') === 0 || strrpos($v, '%') === strlen($v) - 1) {
                $where .= " AND $k LIKE ?";
            } else {
                $where .= " AND $k = ?";
            }
        }
        $db = $this->getDB();
        $res = $db->query('SELECT ' . $return . ', COUNT(*) ' .
                          'FROM taggings WHERE ' . $where . ' GROUP BY ' . $return .
                          ' ORDER BY tag',
                          array_values($search));

        $res = $db->res2arr($res);
        $ret = array();
        foreach ($res as $v) {
            $ret[$v[$return]] = $v['COUNT(*)'];
        }
        return $ret;
    }

    public function getTagSearchURL($tag) {
        return '?do=search&id=' . $tag . '#' .
               str_replace(' ', '_', strtolower($this->getLang('search_section_title')));
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

    private function linkToSearch($tag) {
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
            $form = new Doku_Form(array('id' => 'tagging_edit', 'style' => 'display: none;'));
            $form->addHidden('tagging[id]', $ID);
            $form->addElement(form_makeTextField('tagging[tags]', implode(', ', array_keys($this->getTags(array('pid' => $ID, 'tagger' => $_SERVER['REMOTE_USER']), 'tag')))));
            $form->addElement(form_makeButton('submit', 'save', $lang['btn_save'], array('id' => 'tagging_edit_save')));
            $form->addElement(form_makeButton('submit', 'cancel', $lang['btn_cancel'], array('id' => 'tagging_edit_cancel')));
            $form->printForm();
        }
    }
}

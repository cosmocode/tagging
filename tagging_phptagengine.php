<?php

// Copyright (c) 2006-2007 Alex King. All rights reserved.
// http://alexking.org/projects/php-tag-engine
//
// Released under the LGPL license
// http://www.opensource.org/licenses/lgpl-license.php
//
// **********************************************************************
// This program is distributed in the hope that it will be useful, but
// WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
// **********************************************************************

/**
 * This file contains the PHP Tag Engine class definition.
 *
 * @package phptagengine
 */

require_once 'phptagengine/phptagengine.class.inc.php';

/**
 * The phptagengine class, all the good stuff happens here.
 *
 * @package phptagengine
 */
class tagging_phptagengine extends phptagengine {
    public function __construct($db, $table_prefix, $section_title, $user, $lang) {
        // -- database info
            $this->db = $db; // where $db is your ADOdb instance
            $this->table_tags = $table_prefix.'tags';
            $this->table_tag_names = $table_prefix.'tag_names';
            $this->table_users = $table_prefix.'users';
            $this->table_users_name = 'name';

        $dict = NewDataDictionary($db);
        $tables = $dict->MetaTables();
        $is_installed = true;
        foreach(array($this->table_tags, $this->table_tag_names) as $table) {
            if (!in_array($table, $tables)) {
                $is_installed = false;
            }
        }
        if (!$is_installed) {
            $this->install();
        }

        // -- misc

        $this->base_url = DOKU_URL . 'lib/plugins/tagging/phptagengine/';
        $this->ajax_handler = DOKU_URL . 'lib/exe/ajax.php?call=tagging';
        $this->tag_browse_url = '?do=search&id=<tag>#' . str_replace(' ', '_', strtolower($section_title));

        // -- default values (optional)

        $this->default_type = '';
        $this->default_user = $user;

        // -- language file

        $langmap = array('en' => 'english', 'de' => 'german', 'fr' => 'french',
                         'nl' => 'nederlands', 'no' => 'norwegian',
                         'es' => 'spanish');

        if (isset($langmap[$lang])) {
            $lang = $langmap[$lang];
        }

        require_once 'phptagengine/languages/english.inc.php';
        include_once "phptagengine/languages/$lang.inc.php";
        $this->strings = $pte->strings;

        // -- buttons

        $this->edit_button_display = 'text';
        $this->edit_button_image_url = 'phptagengine/images/icon_edit_tag_small.gif';

        $this->show_remove_links = false;
        $this->delete_button_display = 'text';
        $this->delete_button_image_url = 'phptagengine/images/icon_delete_tag.gif';

        // -- Yahoo! Auto-Complete
        $this->yac = true;
    }

    /**
     * Set a tag to lowercase and remove spaces, could be extended in the future
     * @param mixed $value the value to be normalized
     * @param string $type the type of value, used for the switch statement
     * @return mixed
     */
    function normalize($value, $type = 'tag') {
        switch ($type) {
            case 'tag':
                $value = mb_strtolower($value, 'UTF-8');
                $value = preg_replace('|[^\w\dßäüö_.\-@#$%*!&]|i', '', $value);
                break;
        }
        return $value;
    }

    /**
     * Search items with a specific tag
     */
    function browse_tag($name) {
        $id = $this->get_tag_id($name);
        if ($id === false) {
            return false;
        }
        $result = $this->db->Execute("
            SELECT item AS ITEM, COUNT(user) as N
            FROM $this->table_tags
            WHERE tag = $id
            GROUP BY item
        ") or die(throw_error($this->db->ErrorMsg(), __FILE__, __LINE__));
        return $result;
    }

    /**
     * Return data for a page-specific tagcloud
     */
    function tagcloud($page, $maxcount = 20) {
        $result = $this->db->Execute("
            SELECT tn.name AS NAME, COUNT(t.user) AS N
            FROM $this->table_tag_names tn
            LEFT OUTER JOIN $this->table_tags t
            ON tn.id = t.tag
            WHERE t.item = '$page'
            GROUP BY t.tag
            ORDER BY N DESC
            LIMIT $maxcount
        ") or die(throw_error($this->db->ErrorMsg(), __FILE__, __LINE__));

        $data_arr = array();
        $max = -1;
        $min = -1;

        while ($data = $result->FetchNextObject()) {
            $data_arr[$data->NAME] = $data->N;
            if ($max < $data->N) $max = $data->N;
            if ($min > $data->N || $min === -1) $min = $data->N;
        }

        ksort($data_arr);

        return array($min, $max, $data_arr);
    }

    /**
     * Print the CSS and JS needed in the HTML output
     */
    function html_pte() {
        require_once DOKU_INC . '/inc/JSON.php';
        $json = new JSON();
        $keys = array('ajax_handler', 'tag_browse_url', 'strings',
                      'show_remove_links', 'edit_button_display',
                      'edit_button_image_url', 'delete_button_display',
                      'delete_button_image_url');
        $data = array();
        foreach($keys as $key) {
            $data[$key] = $this->$key;
        }
        $data['req'] = false;
        echo 'var pte = ' . $json->encode($data) . ';' . DOKU_LF;
    }

    function html_head() {
        require_once DOKU_INC . '/inc/JSON.php';
        $json = new JSON();
        ?><script type="text/javascript" charset="utf-8"><!--//--><![CDATA[//><!--
        var tagging_tags = <?php echo $json->encode(array_values($this->get_all_tags())); ?>;
        //--><!]]></script>
        <style type="text/css">
            @import url('<?php echo $this->base_url; ?>phptagengine.css?version=<?php echo $this->version; ?>');
        </style><?php
    }
}

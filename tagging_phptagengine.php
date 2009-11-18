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
        $is_installed = false;
        foreach($tables as $table) {
            if (strpos($table, $table_prefix) === 0) {
                $is_installed = true;
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
    function html_head($state) {
        if (!$state || $state === 'before') {
            ?>
            <script type="text/javascript" charset="utf-8"><!--//--><![CDATA[//><!--
            var pte = {
                req : false
                , ajax_handler : '<?php print($this->ajax_handler); ?>'
                , tag_browse_url : '<?php print($this->tag_browse_url); ?>'
                , strings : {
                <?php
                // put language strings into the JS scope
                foreach ($this->strings as $k => $v) {
                    echo "'$k': '" . $this->slash($v) . "',\n";
                }
                ?>
                }
                , show_remove_links : <?php echo $this->show_remove_links ? 'true' : 'false'; ?>
                , edit_button_display : '<?php print($this->edit_button_display); ?>'
                , edit_button_image_url : '<?php print($this->edit_button_image_url); ?>'
                , delete_button_display : '<?php print($this->delete_button_display); ?>'
                , delete_button_image_url : '<?php print($this->delete_button_image_url); ?>'
            };
            //--><!]]></script><?php
        }

        if (!$state || $state === 'after') {
            ?><script type="text/javascript" charset="utf-8"><!--//--><![CDATA[//><!--
                var tags = ['<?php echo implode("','", $this->get_all_tags()); ?>'];
                var yac_tags = new YAHOO.widget.DS_JSArray(tags);
            //--><!]]></script>
            <style type="text/css">
                @import url('<?php echo $this->base_url; ?>phptagengine.css?version=<?php echo $this->version; ?>');
            </style><?php
        }
    }
}

<?php
/**
 * Allow users to tag a page
 *
 * @author Adrian Lang <lang@cosmocode.de>
 */

if(!defined('DOKU_INC')) die();
if(!defined('DOKU_PLUGIN')) define('DOKU_PLUGIN',DOKU_INC.'lib/plugins/');
require_once DOKU_PLUGIN.'action.php';

class action_plugin_tagging extends DokuWiki_Action_Plugin {

    public static $pte; // An instance of phptagengine

    /**
     * return some info
     */
    function getInfo(){
        return array(
             'author' => 'Adrian Lang',
             'email'  => 'lang@cosmocode.de',
             'date'   => '2009-11-17',
             'name'   => 'Tagging plugin',
             'desc'   => 'Allow users to tag wiki pages',
             'url'    => '',
             );
    }

    /**
     * Initialize the phptagengine instance
     */
    public function init_pte() {
        if (isset(action_plugin_tagging::$pte)) {
            return;
        }

        global $conf;

        require_once 'adodb/adodb.inc.php';
        require_once 'tagging_phptagengine.php';

        if ($this->getConf('db_dsn') === '') return;
        $db = ADONewConnection($this->getConf('db_dsn'));
        if (!$db) return;
        action_plugin_tagging::$pte = new tagging_phptagengine(
                  $db,
                  $this->getConf('db_prefix'),
                  $this->getLang('search_section_title'),
                  isset($_SERVER['REMOTE_USER']) ? $_SERVER['REMOTE_USER'] : '',
                  isset($conf['lang']) ? $conf['lang'] : 'en');
    }

    /**
     * Register handlers
     */
    function register(&$controller) {
        $controller->register_hook('TPL_METAHEADER_OUTPUT', 'AFTER', $this,
                                   'echo_head', 'after');
        $controller->register_hook('TPL_METAHEADER_OUTPUT', 'BEFORE', $this,
                                   'echo_head', 'before');
        $controller->register_hook('TPL_ACT_RENDER', 'AFTER', $this,
                                   'echo_searchresults');
        $controller->register_hook('TPL_ACT_RENDER', 'BEFORE', $this,
                                   'init_pte_on_show');
        $controller->register_hook('AJAX_CALL_UNKNOWN', 'BEFORE', $this,
                                   'handle_ajax');
    }

    /**
     * Include phptagengine’s css and javascript
     */
    function echo_head(&$event, $param) {
        global $ACT;
        try {
            if (!in_array($ACT, array('search', 'show')) && $param === 'before') {
                throw new Exception();
            }
            $this->init_pte();
            if (!action_plugin_tagging::$pte) {
                throw new Exception();
            }
            action_plugin_tagging::$pte->html_head($param);
        } catch (Exception $e) {
            // Assure that pte is defined to avoid JavaScript errors.
            ?><script type="text/javascript" charset="utf-8"><!--//--><![CDATA[//><!--
                var pte = {};
            //--><!]]></script><?php
        }
    }

    /**
     * Echo own tags and all tags (when showing a page) or pages with selected
     * tag (when searching)
     */
    function echo_searchresults(&$event, $param) {
        global $ACT;
        if ($ACT !== 'search') {
            return;
        }
        global $QUERY;
        $this->init_pte();
        if (!action_plugin_tagging::$pte) return;
        $result = action_plugin_tagging::$pte->browse_tag($QUERY);
        if ($result === false) {
            return;
        }
        $sec = '===== ' . $this->getLang('search_section_title') . " =====\n";
        while ($data = $result->FetchNextObject()) {
            $sec .= '  * [[' . $data->ITEM . ']] ' .
                    sprintf($this->getLang('search_nr_users'), $data->N) .
                    DOKU_LF;
        }
        echo p_render('xhtml', p_get_instructions($sec), $info);
    }

    function init_pte_on_show(&$event, $param) {
        global $ACT;
        if ($ACT !== 'show') {
            return;
        }
        $this->init_pte();
    }

    /**
     * Handle AJAX request
     */
    function handle_ajax($event) {
        if ($event->data !== 'tagging') {
            return;
        }
        if (isset($_SERVER['REMOTE_USER'])) {
            $this->init_pte();
            if (!$this->pte) return;
            $this->pte->ajax_engine();
        }
        $event->stopPropagation();
        $event->preventDefault();
    }
}

function tpl_tagging_tagcloud() {
    global $ID;
    if (!action_plugin_tagging::$pte) return;
    $pte = action_plugin_tagging::$pte;
    list($min, $max, $data_arr) = $pte->tagcloud($ID, 10);

    $div = log($max) - log($min);
    $factor = ($div === 0) ? 10 : (10 * $div);

    echo '<ul class="tagcloud">';
    foreach ($data_arr as $tag => $number) {
        echo '<li class="t' .
             round($factor * (log($number) - log($min))) . '">' .
             '<a href="' . $pte->tag_browse_url($tag) . '">' .
             $tag . '</a>' . '</li> ';
    }
    echo '</ul>';
}

function tpl_tagging_tagedit() {
    global $ID;
    if (!action_plugin_tagging::$pte) return;
    if (isset($_SERVER['REMOTE_USER'])) {
        action_plugin_tagging::$pte->html_item_tags($ID);
    }
}

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

    private $pte; // An instance of phptagengine

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
        if (isset($this->pte)) {
            return;
        }

        global $conf;

        require_once 'adodb/adodb.inc.php';
        require_once 'tagging_phptagengine.php';

        if ($this->getConf('db_dsn') === '') return;
        $db = ADONewConnection($this->getConf('db_dsn'));
        if (!$db) return;
        $this->pte = new tagging_phptagengine(
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
                                   'echo_tags');
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
            if (!$this->pte) {
                throw new Exception();
            }
            $this->pte->html_head($param);
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
    function echo_tags(&$event, $param) {
        global $ACT;
        switch ($ACT) {
        case 'search':
            global $QUERY;
            $this->init_pte();
            if (!$this->pte) return;
            $result = $this->pte->browse_tag($QUERY);
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
            break;
        case 'show':
            global $ID;
            $this->init_pte();
            if (!$this->pte) return;
            if (isset($_SERVER['REMOTE_USER'])) {
                $this->pte->html_item_tags($ID);
            }
            list($min, $max, $data_arr) = $this->pte->tagcloud($ID, 10);

            $div = log($max) - log($min);
            $factor = ($div === 0) ? 10 : (10 * $div);

            echo '<ul class="tagcloud">';
            foreach ($data_arr as $tag => $number) {
                echo '<li class="t' .
                     round($factor * (log($number) - log($min))) . '">' .
                     '<a href="' . $this->pte->tag_browse_url($tag) . '">' .
                     $tag . '</a>' . '</li> ';
            }
            echo '</ul>';
            break;
        }
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

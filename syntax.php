<?php
/**
 * DokuWiki Plugin tagging (Syntax Component)
 *
 * @license GPL 2 http://www.gnu.org/licenses/gpl-2.0.html
 * @author  Adrian Lang <lang@cosmocode.de>
 */

// must be run within Dokuwiki
if (!defined('DOKU_INC')) die();

if (!defined('DOKU_LF')) define('DOKU_LF', "\n");
if (!defined('DOKU_TAB')) define('DOKU_TAB', "\t");
if (!defined('DOKU_PLUGIN')) define('DOKU_PLUGIN',DOKU_INC.'lib/plugins/');

require_once DOKU_PLUGIN.'syntax.php';
require_once DOKU_PLUGIN.'tagging/common.php';

class syntax_plugin_tagging extends DokuWiki_Syntax_Plugin {

    function getType() {
        return 'substition';
    }

    function getPType() {
        return 'block';
    }

    function getSort() {
        return 13;
    }

    function connectTo($mode) {
        $this->Lexer->addSpecialPattern('{{tagging::\w+(?:>[^}]+)?}}',$mode,'plugin_tagging');
    }

    function handle($match, $state, $pos, &$handler){
        $data = array();
        $matches = array();
        preg_match('/{{tagging::(\w+)(?:>([^}]+))?}}/', $match, $matches);
        $data['cmd'] = $matches[1];

        switch($data['cmd']) {
        case 'user':
            if (count($matches) > 2) {
                $data['user'] = trim($matches[2]);
            }
            break;
        }

        return $data;
    }

    function render($mode, &$renderer, $data) {
        if ($mode !== 'xhtml') {
            return false;
        }

        $pte = tagging_get_pte($this);

        switch($data['cmd']) {
        case 'user':
            $renderer->info['cache'] = false;
            if (!isset($data['user'])) {
                $data['user'] = $_SERVER['REMOTE_USER'];
            }
            if (is_null($pte)) return;
            list($min, $max, $data_arr) = $pte->user_tagcloud($data['user'], 10);

            cloud_weight($data_arr, $min, $max, 10);

            $renderer->doc .= '<ul class="tagcloud" id="tagging_tagcloud">';
            if (count($data_arr) === 0) {
                // Produce valid XHTML (ul needs a child)
                $this->setupLocale();
                $renderer->doc .=  '<li>' . $this->lang['js']['notags'] . '</li>';
            }
            foreach ($data_arr as $tag => $size) {
                $renderer->doc .=  '<li class="t' .
                     $size . '">' .
                     '<a href="' . hsc($pte->tag_browse_url($tag)) . '">' .
                     $tag . '</a>' . '</li> ';
            }
            $renderer->doc .= '</ul>';

            break;
        }

        return true;
    }
}

// vim:ts=4:sw=4:et:enc=utf-8:

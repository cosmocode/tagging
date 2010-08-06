<?php
/**
 * DokuWiki Plugin tagging (Syntax Component)
 *
 * @license GPL 2 http://www.gnu.org/licenses/gpl-2.0.html
 * @author  Adrian Lang <lang@cosmocode.de>
 */

// must be run within Dokuwiki
if (!defined('DOKU_INC')) die();

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

        $hlp = plugin_load('helper', 'tagging');

        switch($data['cmd']) {
        case 'user':
            $renderer->info['cache'] = false;
            if (!isset($data['user'])) {
                $data['user'] = $_SERVER['REMOTE_USER'];
            }
            $tags = $hlp->getTags(array('tagger' => $data['user']), 'tag');
            $renderer->doc .= $hlp->html_cloud($tags, 'tag', array($hlp, 'linkToSearch'), true, true);

            break;
        }

        return true;
    }
}

// vim:ts=4:sw=4:et:enc=utf-8:

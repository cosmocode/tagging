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
        case 'ns':
            if (count($matches) > 2) {
                $data['ns'] = trim($matches[2]);
            }
            break;
        }

        return $data;
    }

    function render($mode, &$renderer, $data) {
        if ($mode !== 'xhtml') {
            return false;
        }

        /** @var helper_plugin_tagging $hlp */
        $hlp = plugin_load('helper', 'tagging');

        switch($data['cmd']) {
            case 'user':
                $renderer->info['cache'] = false;
                if(!isset($data['user'])) {
                    $data['user'] = $_SERVER['REMOTE_USER'];
                }
                $tags = $hlp->findItems(array('tagger' => $data['user']), 'tag');
                $renderer->doc .= $hlp->html_cloud($tags, 'tag', array($hlp, 'linkToSearch'), true, true);

                break;
            case 'ns':
                $renderer->info['cache'] = false;
                if(!isset($data['ns'])) {
                    global $INFO;
                    $data['ns'] = $INFO['namespace'];
                }
                global $ID;
                $data['ns'] = resolve_id(getNS($ID), $data['ns'] . ':');
                if($data['ns'] !== '') {
                    // Do not match nsbla, only ns:bla
                    $data['ns'] .= ':';
                }
                $tags = $hlp->findItems(array('pid' => $data['ns'].'%'), 'tag');
                $renderer->doc .= $hlp->html_cloud($tags, 'tag', array($hlp, 'linkToSearch'), true, true, $data['ns']);

                break;
            case 'input':
                $hlp->tpl_tags();

                break;
        }

        return true;
    }
}

// vim:ts=4:sw=4:et:enc=utf-8:

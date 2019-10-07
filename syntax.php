<?php
/**
 * DokuWiki Plugin tagging (Syntax Component)
 *
 * @license GPL 2 http://www.gnu.org/licenses/gpl-2.0.html
 * @author  Adrian Lang <lang@cosmocode.de>
 */

// must be run within Dokuwiki
if (!defined('DOKU_INC')) {
    die();
}

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
        $this->Lexer->addSpecialPattern('{{tagging::\w+(?:>[^}\?]+)?(?:\?[0-9]+)?}}', $mode, 'plugin_tagging');
    }

    function handle($match, $state, $pos, Doku_Handler $handler) {
        $data = array();
        $matches = array();
        preg_match('/{{tagging::(\w+)(?:>([^}\?]+))?(\?[0-9]+)?}}/', $match, $matches);
        $data['cmd'] = $matches[1];
        $data['limit'] = (int)ltrim($matches[3], '?');
        if (!$data['limit']) {
            $data['limit'] = $this->getConf('cloudlimit');
        }

        switch ($data['cmd']) {
            case 'user':
                if (count($matches) > 2) {
                    $data['user'] = trim($matches[2]);
                }
                break;
            case 'tag':
                if (count($matches) > 2) {
                    $data['tag'] = trim($matches[2]);
                }
                break;
            case 'ns':
                if (count($matches) > 2) {
                    $data['ns'] = trim($matches[2]);
                }
                break;
            case 'manage':
                if (count($matches) > 2) {
                    $data['manage'] = trim($matches[2]);
                }
                break;
        }

        return $data;
    }

    function render($mode, Doku_Renderer $renderer, $data) {
        if ($mode !== 'xhtml') {
            return false;
        }

        /** @var helper_plugin_tagging $hlp */
        $hlp = plugin_load('helper', 'tagging');

        switch ($data['cmd']) {
            case 'user':
                $renderer->info['cache'] = false;
                if (!isset($data['user'])) {
                    $data['user'] = $_SERVER['REMOTE_USER'];
                }
                $tags = $hlp->findItems(array('tagger' => $data['user']), 'tag', $data['limit']);
                
                $renderer->doc .= $hlp->html_cloud($tags, 'tag', array($hlp, 'linkToSearch'), true, true);

                break;
            case 'tag':
                $renderer->info['cache'] = false;
                
                $pids = $hlp->findItems(array('tag' => $data['tag']), 'pid', $data['limit']);

                $renderer->doc .= $hlp->html_page_list($pids);
               
                break;
            case 'ns':
                $renderer->info['cache'] = false;
                if (!isset($data['ns'])) {
                    global $INFO;
                    $data['ns'] = $INFO['namespace'];
                }
                global $ID;
                $data['ns'] = resolve_id(getNS($ID), $data['ns'] . ':');
                if ($data['ns'] !== '') {
                    // Do not match nsbla, only ns:bla
                    $data['ns'] .= ':';
                }
                $tags = $hlp->findItems(['pid' => $hlp->globNamespace($data['ns'])], 'tag', $data['limit']);
                $renderer->doc .= $hlp->html_cloud($tags, 'tag', array($hlp, 'linkToSearch'), true, true, $data['ns']);

                break;
            case 'input':
                $renderer->nocache();
                $renderer->doc .= $hlp->tpl_tags(false);
                break;
            case 'manage':
                $renderer->nocache();
                $ns = $data['manage'] ?: '';
                $renderer->doc .= $hlp->manageTags($ns);
                break;
        }

        return true;
    }
}

// vim:ts=4:sw=4:et:enc=utf-8:

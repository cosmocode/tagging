<?php

use dokuwiki\Extension\SyntaxPlugin;
use dokuwiki\File\PageResolver;

/**
 * DokuWiki Plugin tagging (Syntax Component)
 *
 * @license GPL 2 http://www.gnu.org/licenses/gpl-2.0.html
 * @author  Adrian Lang <lang@cosmocode.de>
 */
class syntax_plugin_tagging extends SyntaxPlugin
{
    public function getType()
    {
        return 'substition';
    }

    public function getPType()
    {
        return 'block';
    }

    public function getSort()
    {
        return 13;
    }

    public function connectTo($mode)
    {
        $this->Lexer->addSpecialPattern('{{tagging::\w+(?:>[^}\?]+)?(?:\?\d+)?}}', $mode, 'plugin_tagging');
    }

    public function handle($match, $state, $pos, Doku_Handler $handler)
    {
        $data = [];
        $matches = [];
        preg_match('/{{tagging::(\w+)(?:>([^}\?]+))?(\?\d+)?}}/', $match, $matches);
        $data['cmd'] = $matches[1];
        $data['limit'] = (int)ltrim($matches[3] ?: '', '?');
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

    public function render($mode, Doku_Renderer $renderer, $data)
    {
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
                $tags = $hlp->findItems(['tagger' => $data['user']], 'tag', $data['limit']);

                $renderer->doc .= $hlp->html_cloud($tags, 'tag', [$hlp, 'linkToSearch'], true, true);

                break;
            case 'tag':
                $renderer->info['cache'] = false;

                $pids = $hlp->findItems(['tag' => $data['tag']], 'pid', $data['limit']);

                $renderer->doc .= $hlp->html_page_list($pids);

                break;
            case 'ns':
                $renderer->info['cache'] = false;
                if (!isset($data['ns'])) {
                    global $INFO;
                    $data['ns'] = $INFO['namespace'];
                }
                global $ID;
                $resolver = new PageResolver($ID);
                $data['ns'] = $resolver->resolveId($data['ns'] . ':');
                if ($data['ns'] !== '') {
                    // Do not match nsbla, only ns:bla
                    $data['ns'] .= ':';
                }
                $tags = $hlp->findItems(['pid' => $hlp->globNamespace($data['ns'])], 'tag', $data['limit']);
                $renderer->doc .= $hlp->html_cloud($tags, 'tag', [$hlp, 'linkToSearch'], true, true, $data['ns']);

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

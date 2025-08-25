<?php

use dokuwiki\Extension\ActionPlugin;
use dokuwiki\Extension\Event;
use dokuwiki\Extension\EventHandler;

/**
 * Class action_plugin_tagging_main
 */
class action_plugin_tagging_main extends ActionPlugin
{
    /**
     * Register handlers
     *
     * @param EventHandler $controller
     */
    public function register(EventHandler $controller)
    {
        $controller->register_hook(
            'AJAX_CALL_UNKNOWN',
            'BEFORE',
            $this,
            'handle_ajax_call_unknown'
        );

        $controller->register_hook(
            'ACTION_ACT_PREPROCESS',
            'BEFORE',
            $this,
            'handle_jump'
        );

        $controller->register_hook(
            'DOKUWIKI_STARTED',
            'AFTER',
            $this,
            'js_add_security_token'
        );

        $controller->register_hook(
            'PLUGIN_MOVE_PAGE_RENAME',
            'AFTER',
            $this,
            'update_moved_page'
        );
    }

    /**
     * Add sectok to JavaScript to secure ajax requests
     *
     * @param Event $event
     * @param            $param
     */
    public function js_add_security_token(Event $event, $param)
    {
        global $JSINFO;
        $JSINFO['sectok'] = getSecurityToken();
    }

    /**
     * Handle our AJAX requests
     *
     * @param Event $event
     * @param            $param
     */
    public function handle_ajax_call_unknown(Event $event, $param)
    {
        $handled = true;

        if ($event->data == 'plugin_tagging_save') {
            $this->save();
        } elseif ($event->data == 'plugin_tagging_autocomplete') {
            $this->autocomplete();
        } elseif ($event->data === 'plugin_tagging_admin_change') {
            $this->admin_change();
        } elseif ($event->data === 'plugin_tagging_html_pages') {
            $this->getPagesHtml();
        } elseif ($event->data === 'plugin_tagging_delete') {
            $this->deleteTag();
        } elseif ($event->data === 'plugin_tagging_rename') {
            $this->renameTag();
        } else {
            $handled = false;
        }
        if (!$handled) {
            return;
        }

        $event->preventDefault();
        $event->stopPropagation();
    }

    /**
     * Jump to a tag
     *
     * @param Event $event
     * @param            $param
     */
    public function handle_jump(Event &$event, $param)
    {
        if (act_clean($event->data) != 'tagjmp') {
            return;
        }

        $event->preventDefault();
        $event->stopPropagation();

        $event->data = 'show';

        global $INPUT;
        $tags = $INPUT->arr('tag', (array)$INPUT->str('tag'));
        $lang = $INPUT->str('lang');

        /** @var helper_plugin_tagging $hlp */
        $hlp = plugin_load('helper', 'tagging');

        foreach ($tags as $tag) {
            $filter = ['tag' => $tag];
            if ($lang) {
                $filter['lang'] = $lang;
            }
            $pages = $hlp->findItems($filter, 'pid', 1);
            if (!count($pages)) {
                continue;
            }

            $pages = array_keys($pages);
            $id = array_pop($pages);
            send_redirect(wl($id, '', true, '&'));
        }

        $tags = array_map('hsc', $tags);
        msg(sprintf($this->getLang('tagjmp_error'), implode(', ', $tags)), -1);
    }

    /**
     * Save new/changed tags
     */
    public function save()
    {
        global $INPUT;
        global $INFO;

        /** @var helper_plugin_tagging $hlp */
        $hlp = plugin_load('helper', 'tagging');

        $data = $INPUT->arr('tagging');
        $id = $data['id'];
        $INFO['writable'] = auth_quickaclcheck($id) >= AUTH_EDIT; // we also need this in findItems

        if ($INFO['writable'] && $hlp->getUser()) {
            $hlp->replaceTags(
                $id,
                $hlp->getUser(),
                preg_split(
                    '/(\s*,\s*)|(\s*,?\s*\n\s*)/',
                    $data['tags'],
                    -1,
                    PREG_SPLIT_NO_EMPTY
                )
            );
            $hlp->updateElasticState($id);
        }

        $tags = $hlp->findItems(['pid' => $id], 'tag');
        $hlp->html_cloud($tags, 'tag', [$hlp, 'linkToSearch'], false);
    }

    /**
     * Return autocompletion data
     */
    public function autocomplete()
    {
        global $INPUT;

        /** @var helper_plugin_tagging $hlp */
        $hlp = plugin_load('helper', 'tagging');

        $search = $INPUT->str('term');
        $tags = $hlp->findItems(['tag' => '*' . $hlp->getDB()->escape_string($search) . '*'], 'tag');
        arsort($tags);
        $tags = array_keys($tags);

        header('Content-Type: application/json');

        echo json_encode(array_combine($tags, $tags));
    }

    /**
     * Allow admins to change all tags (not only their own)
     * We change the tag for every user
     */
    public function admin_change()
    {
        global $INPUT;

        /** @var helper_plugin_tagging $hlp */
        $hlp = plugin_load('helper', 'tagging');

        header('Content-Type: application/json');

        if (!auth_isadmin()) {
            echo json_encode(['status' => 'error', 'msg' => $this->getLang('no_admin')]);
            return;
        }

        if (!checkSecurityToken()) {
            echo json_encode(['status' => 'error', 'msg' => 'Security Token did not match. Possible CSRF attack.']);
            return;
        }

        if (!$INPUT->has('id')) {
            echo json_encode(['status' => 'error', 'msg' => 'No page id given.']);
            return;
        }
        $pid = $INPUT->str('id');

        if (!$INPUT->has('oldValue') || !$INPUT->has('newValue')) {
            echo json_encode(['status' => 'error', 'msg' => 'No proper input. Give "oldValue" and "newValue"']);
            return;
        }


        [$err, $msg] = $hlp->modifyPageTag($pid, $INPUT->str('oldValue'), $INPUT->str('newValue'));
        if ($err) {
            echo json_encode(['status' => 'error', 'msg' => $msg]);
            return;
        }

        $tags = $hlp->findItems(['pid' => $pid], 'tag');
        $userTags = $hlp->findItems(['pid' => $pid, 'tagger' => $hlp->getUser()], 'tag');
        echo json_encode([
            'status' => 'ok',
            'tags_edit_value' => implode(', ', array_keys($userTags)),
            'html_cloud' => $hlp->html_cloud($tags, 'tag', [$hlp, 'linkToSearch'], false, true)
        ]);
    }

    /**
     * Management: delete all occurrences of a tag
     */
    public function deleteTag()
    {
        global $INPUT;
        $data = $INPUT->arr('tagging');

        /** @var helper_plugin_tagging $hlp */
        $hlp = plugin_load('helper', 'tagging');
        $hlp->deleteTags($data['tid']);

        // update elasticsearch state for all relevant pages
        $pids = $hlp->findItems(['tag' => $data['tid'][0]], 'pid');
        if (!empty($pids)) {
            foreach (array_keys($pids) as $pid) {
                $hlp->updateElasticState($pid);
            }
        }
    }

    /**
     * Management: rename all occurrences of a tag
     */
    public function renameTag()
    {
        global $INPUT;
        $data = $INPUT->arr('tagging');

        /** @var helper_plugin_tagging $hlp */
        $hlp = plugin_load('helper', 'tagging');
        $hlp->renameTag($data['oldValue'], $data['newValue']);

        // update elasticsearch state for all relevant pages
        $pids = $hlp->findItems(['tag' => $data['newValue']], 'pid');
        if (!empty($pids)) {
            foreach (array_keys($pids) as $pid) {
                $hlp->updateElasticState($pid);
            }
        }
    }

    /**
     * Tag dialog HTML: print links to all pages with a given tag
     */
    public function getPagesHtml()
    {
        global $INPUT;
        $data = $INPUT->arr('tagging');

        /** @var helper_plugin_tagging $hlp */
        $hlp = plugin_load('helper', 'tagging');
        echo $hlp->getPagesHtml($data['tid']);
    }

    /**
     * Updates tagging database after a page has been moved/renamed by the move plugin
     *
     * @param Event $event
     * @param $param
     */
    public function update_moved_page(Event $event, $param)
    {
        $src = $event->data['src_id'];
        $dst = $event->data['dst_id'];

        /** @var helper_plugin_tagging $hlp */
        $hlp = plugin_load('helper', 'tagging');
        $hlp->renamePage($src, $dst);
    }
}

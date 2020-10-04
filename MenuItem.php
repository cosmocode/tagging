<?php

namespace dokuwiki\plugin\tagging;

use dokuwiki\Menu\Item\AbstractItem;

/**
 * Class MenuItemTagging
 *
 * Implements the tagging button for DokuWiki's menu system
 * 
 * @package dokuwiki\plugin\tagging
 */
class MenuItem extends AbstractItem {

    /** @var string do action for this plugin */
    protected $type = 'plugin_tagging__edit';

    /** @var string icon file */
    protected $svg = DOKU_INC . 'lib/plugins/tagging/admin.svg';

    /**
     * MenuItem constructor.
     */
    public function __construct() {
        parent::__construct();
        global $REV;
        if($REV) $this->params['rev'] = $REV;
    }

    /**
     * Get label from plugin language file
     *
     * @return string
     */
    public function getLabel() {
        $hlp = plugin_load('helper', 'tagging');
        return $hlp->getLang('edit_tags_button');
    }

    /**
     * Return the link this item links to
     * 
     * @return string
     */
    public function getLink() {
        return 'javascript:void(0);';
    }

    /**
     * Convenience method to get the attributes for constructing an <a> element
     *
     * @see buildAttributes()
     * @return array
     */
    public function getLinkAttributes($classprefix = 'menuitemtagging ') {
        global $INFO;
        global $lang;

        $attr = array(
            'href' => $this->getLink(),
            'title' => $this->getTitle(),
        );
        $attr['rel'] = 'nofollow';

        /** @var helper_plugin_tagging $hlp */
        $hlp = plugin_load('helper', 'tagging');

        $filter = array('pid' => $INFO['id']);
        if ($hlp->getConf('singleusermode')) {
            $filter['tagger'] = 'auto';
        }

        $tags = $hlp->findItems($filter, 'tag');
        $attr['data-tags'] = implode(', ', array_keys($tags));

        return $attr;
    }
}

<?php

/**
 * Class action_plugin_tagging_elasticsearch
 */
class action_plugin_tagging_elasticsearch extends DokuWiki_Action_Plugin
{

    public function register(Doku_Event_Handler $controller)
    {
        $controller->register_hook(
            'PLUGIN_ELASTICSEARCH_CREATEMAPPING', 'BEFORE', $this,
            'elasticMapping'
        );

        $controller->register_hook(
            'PLUGIN_ELASTICSEARCH_INDEXPAGE', 'BEFORE', $this,
            'elasticIndexPage'
        );

        $controller->register_hook(
            'PLUGIN_ELASTICSEARCH_FILTERS', 'BEFORE', $this,
            'elasticSearchFilter'
        );

        $controller->register_hook(
            'PLUGIN_ELASTICSEARCH_SEARCHFIELDS', 'BEFORE', $this,
            'elasticSearchFields'
        );

        $controller->register_hook(
            'PLUGIN_ELASTICSEARCH_QUERY', 'BEFORE', $this,
            'setupTagSearchElastic'
        );
    }
    /**
     * Add our own field mapping to Elasticsearch
     *
     * @param Doku_Event $event
     */
    public function elasticMapping(Doku_Event $event)
    {
        $event->data[] = ['tagging' => ['type' => 'keyword']];
    }

    /**
     * Add taggings to Elastic index
     *
     * @param Doku_Event $event
     */
    public function elasticIndexPage(Doku_Event $event)
    {
        $data = &$event->data;

        /** @var helper_plugin_tagging $hlp */
        $hlp = plugin_load('helper', 'tagging');
        $tags = $hlp->findItems(['pid' => $data['uri']], 'tag');

        $data['tagging'] = array_map(function ($tag) use ($hlp) {
            return $hlp->cleanTag($tag);
        }, array_keys($tags));
    }

    /**
     * Add configuration for tagging filter in advanced search
     * when using Elasticsearch plugin
     *
     * @param Doku_Event $event
     */
    public function elasticSearchFilter(Doku_Event $event)
    {
        $event->data['tagging'] = [
            'label' => $this->getLang('search_filter_label'),
            'prefix' => '#',
            'id' => 'plugin__tagging-tags',
            'fieldPath' => 'tagging',
            'limit' => '100',
        ];
    }

    /**
     * Remove tags from query string and put them into $INPUT
     * to be used as filter by Elasticsearch.
     * Also return new #tag values to be appended to the query.
     *
     * @param Doku_Event $event
     */
    public function setupTagSearchElastic(Doku_Event $event)
    {
        global $QUERY;
        global $INPUT;

        /** @var helper_plugin_tagging $hlp */
        $hlp = plugin_load('helper', 'tagging');

        $taggingFilter = $INPUT->arr('tagging');

        // get (hash)tags from query
        preg_match_all('/(?:#)(\w+)/u', $QUERY, $matches);
        if (isset($matches[1])) {
            $matches[1] = array_map([$hlp, 'cleanTag'], $matches[1]);
            $INPUT->set('tagging', array_merge($matches[1], $taggingFilter));
        }
        \action_plugin_tagging_search::removeTagsFromQuery($QUERY);

        // return tagging filter as hashtags to be appended to the original query (without doubles)
        if ($taggingFilter) {
            $additions = array_map(function ($tag) use ($matches) {
                if (!isset($matches[1]) || !in_array($tag, $matches[1])) {
                    return "#$tag";
                }
                return null;
            }, $taggingFilter);
            $event->data += array_filter($additions);
        }
    }

    /**
     * Add tagging to the list of search fields
     *
     * @param Doku_Event $event
     */
    public function elasticSearchFields(Doku_Event $event)
    {
        array_push($event->data, 'tagging');
    }
}

<?php

/**
 * Helper tests for the tagging plugin
 *
 * @group plugin_tagging
 * @group plugins
 */
class helper_plugin_tagging_test extends DokuWikiTest
{
    protected $pluginsEnabled = ['tagging', 'sqlite'];

    /**
     * Provide the test data
     *
     * @return array
     */
    public function dataTags()
    {
        return [
            [
                ['ortag' => 'image'],
                'SELECT pid AS item, COUNT(*) AS cnt
                FROM taggings
                WHERE 1=1
                AND CLEANTAG(tag) IN ( CLEANTAG(?) )
                AND GETACCESSLEVEL(pid) >= '. AUTH_READ .'
                GROUP BY pid
                ORDER BY cnt DESC, pid'
            ],
            [
                ['ortag' => 'acks, image'],
                'SELECT pid AS item, COUNT(*) AS cnt
                FROM taggings
                WHERE 1=1
                AND CLEANTAG(tag) IN ( CLEANTAG(?), CLEANTAG(?) )
                AND GETACCESSLEVEL(pid) >= '. AUTH_READ .'
                GROUP BY pid
                ORDER BY cnt DESC, pid'
            ],
            [
                ['andtag' => 'acks, image'],
                'SELECT pid AS item, COUNT(*) AS cnt
                FROM taggings
                WHERE 1=1
                AND CLEANTAG(tag) IN ( CLEANTAG(?), CLEANTAG(?) )
                AND GETACCESSLEVEL(pid) >= '. AUTH_READ .'
                GROUP BY pid
                HAVING cnt = 2
                ORDER BY cnt DESC, pid'
            ],
            [
                [
                    'ortag' => 'image',
                    'pid' => 'wiki:*'
                ],
                'SELECT pid AS item, COUNT(*) AS cnt
                FROM taggings
                WHERE 1=1
                AND CLEANTAG(tag) IN ( CLEANTAG(?) )
                AND pid GLOB ?
                AND GETACCESSLEVEL(pid) >= '. AUTH_READ .'
                GROUP BY pid
                ORDER BY cnt DESC, pid'
            ],
            [
                [
                    'ortag' => 'image',
                    'notpid0' => 'wiki:*'
                ],
                'SELECT pid AS item, COUNT(*) AS cnt
                FROM taggings
                WHERE 1=1
                AND CLEANTAG(tag) IN ( CLEANTAG(?) )
                AND pid NOT GLOB ?
                AND GETACCESSLEVEL(pid) >= '. AUTH_READ .'
                GROUP BY pid
                ORDER BY cnt DESC, pid'

            ],
            [
                [
                    'ortag' => 'image',
                    'notpid0' => 'wiki:*',
                    'notpid1' => 'awiki:*'
                ],
                'SELECT pid AS item, COUNT(*) AS cnt
                FROM taggings
                WHERE 1=1
                AND CLEANTAG(tag) IN ( CLEANTAG(?) )
                AND pid NOT GLOB ? AND pid NOT GLOB ?
                AND GETACCESSLEVEL(pid) >= '. AUTH_READ .'
                GROUP BY pid
                ORDER BY cnt DESC, pid'
            ],
        ];
    }

    /**
     * Search results
     *
     * @dataProvider dataTags
     * @param array $filter
     * @param string $expected
     */
    public function testSearchSql($filter, $expected)
    {
        /** @var helper_plugin_tagging $helper */
        $helper = plugin_load('helper', 'tagging');
        $actual = $helper->getWikiSearchSql($filter, 'pid', 0);
        $this->assertEquals($this->toSingleLine($expected), $this->toSingleLine($actual));
    }

    /**
     * @param string $string
     * @return string
     */
    protected function toSingleLine($string)
    {
        $string = str_replace(["\r","\n"], '', $string);
        $string = preg_replace('/ +/', ' ', $string);
        return trim($string);
    }
}

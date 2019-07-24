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
                ['ortag' => ['image']],
                'SELECT pid AS item, COUNT(*) AS cnt
                FROM taggings
                WHERE 1=1
                AND CLEANTAG(tag) = CLEANTAG(?)
                AND GETACCESSLEVEL(pid) >= '. AUTH_READ .'
                GROUP BY pid
                ORDER BY cnt DESC, pid'
            ],
            [
                ['ortag' => ['acks', 'image']],
                'SELECT pid AS item, COUNT(*) AS cnt
                FROM taggings
                WHERE 1=1
                AND CLEANTAG(tag) = CLEANTAG(?) OR CLEANTAG(tag) = CLEANTAG(?)
                AND GETACCESSLEVEL(pid) >= '. AUTH_READ .'
                GROUP BY pid
                ORDER BY cnt DESC, pid'
            ],
            [
                ['andtag' => ['acks', 'image']],
                'SELECT pid AS item, COUNT(*) AS cnt
                FROM taggings
                WHERE 1=1
                AND CLEANTAG(tag) = CLEANTAG(?) OR CLEANTAG(tag) = CLEANTAG(?)
                AND GETACCESSLEVEL(pid) >= '. AUTH_READ .'
                GROUP BY pid
                HAVING cnt = 2
                ORDER BY cnt DESC, pid'
            ],
            [
                [
                    'ortag' => ['image'],
                    'ns' => ['wiki:*']
                ],
                'SELECT pid AS item, COUNT(*) AS cnt
                FROM taggings
                WHERE 1=1
                AND pid GLOB ?
                AND CLEANTAG(tag) = CLEANTAG(?)
                AND GETACCESSLEVEL(pid) >= '. AUTH_READ .'
                GROUP BY pid
                ORDER BY cnt DESC, pid'
            ],
            [
                [
                    'ortag' => ['image'],
                    'notns' => ['wiki:*']
                ],
                'SELECT pid AS item, COUNT(*) AS cnt
                FROM taggings
                WHERE 1=1
                AND pid NOT GLOB ?
                AND CLEANTAG(tag) = CLEANTAG(?)
                AND GETACCESSLEVEL(pid) >= '. AUTH_READ .'
                GROUP BY pid
                ORDER BY cnt DESC, pid'

            ],
            [
                [
                    'ortag' => ['image'],
                    'notns' => ['wiki:*', 'awiki:*']
                ],
                'SELECT pid AS item, COUNT(*) AS cnt
                FROM taggings
                WHERE 1=1
                AND pid NOT GLOB ? AND pid NOT GLOB ?
                AND CLEANTAG(tag) = CLEANTAG(?)
                AND GETACCESSLEVEL(pid) >= '. AUTH_READ .'
                GROUP BY pid
                ORDER BY cnt DESC, pid'
            ],
            [
                ['ortag' => ['acks*']],
                'SELECT pid AS item, COUNT(*) AS cnt
                FROM taggings
                WHERE 1=1
                AND CLEANTAG(tag) GLOB CLEANTAG(?)
                AND GETACCESSLEVEL(pid) >= '. AUTH_READ .'
                GROUP BY pid
                ORDER BY cnt DESC, pid'
            ],
            [
                ['ortag' => ['acks', 'image*']],
                'SELECT pid AS item, COUNT(*) AS cnt
                FROM taggings
                WHERE 1=1
                AND CLEANTAG(tag) = CLEANTAG(?) OR CLEANTAG(tag) GLOB CLEANTAG(?)
                AND GETACCESSLEVEL(pid) >= '. AUTH_READ .'
                GROUP BY pid
                ORDER BY cnt DESC, pid'
            ],
            [
                ['ortag' => ['acks*', 'image']],
                'SELECT pid AS item, COUNT(*) AS cnt
                FROM taggings
                WHERE 1=1
                AND CLEANTAG(tag) GLOB CLEANTAG(?) OR CLEANTAG(tag) = CLEANTAG(?)
                AND GETACCESSLEVEL(pid) >= '. AUTH_READ .'
                GROUP BY pid
                ORDER BY cnt DESC, pid'
            ],
            [
                ['ortag' => ['acks*', 'image*']],
                'SELECT pid AS item, COUNT(*) AS cnt
                FROM taggings
                WHERE 1=1
                AND CLEANTAG(tag) GLOB CLEANTAG(?) OR CLEANTAG(tag) GLOB CLEANTAG(?)
                AND GETACCESSLEVEL(pid) >= '. AUTH_READ .'
                GROUP BY pid
                ORDER BY cnt DESC, pid'
            ],
            [
                ['andtag' => ['acks*', 'image*']],
                'SELECT pid AS item, COUNT(*) AS cnt
                FROM taggings
                WHERE 1=1
                AND CLEANTAG(tag) GLOB CLEANTAG(?) OR CLEANTAG(tag) GLOB CLEANTAG(?)
                AND GETACCESSLEVEL(pid) >= '. AUTH_READ .'
                GROUP BY pid
                HAVING cnt = 2
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
        /** @var helper_plugin_tagging_querybuilder $queryBuilder */
        $queryBuilder = plugin_load('helper', 'tagging_querybuilder');
        $queryBuilder->setField('pid');

        if (isset($filter['andtag'])) {
            $queryBuilder->setTags($filter['andtag']);
            $queryBuilder->setLogicalAnd(true);
        } else {
            $queryBuilder->setTags($filter['ortag']);
        }

        if (isset($filter['ns'])) $queryBuilder->includeNS($filter['ns']);
        if (isset($filter['notns'])) $queryBuilder->excludeNS($filter['notns']);

        $actual = $queryBuilder->getPages()->getSql();
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

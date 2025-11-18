<?php

namespace dokuwiki\plugin\tagging\test;

use \DokuWikiTest;

/**
 * Syntax tests for the tagging plugin
 *
 * @group plugin_tagging
 * @group plugins
 */
class SyntaxTest extends DokuWikiTest
{
    protected $pluginsEnabled = ['tagging', 'sqlite'];

    /**
     * Provide the test data
     *
     * @return array
     */
    public function nsFilters()
    {
        return [
            [
                [],
                'test:plugins:tagging'
            ],
            [
                ['ns' => '*'],
                ':'
            ],
            [
                ['ns' => 'foo'],
                'foo'
            ],
            [
                ['ns' => ':foo'],
                'foo'
            ],
            [
                ['ns' => 'foo:bar'],
                'foo:bar'
            ],
            [
                ['ns' => '.'],
                'test:plugins:tagging'
            ],
            [
                ['ns' => '..'],
                'test:plugins'
            ],
            [
                ['ns' => '.:sub'],
                'test:plugins:tagging:sub'
            ],
        ];
    }

    /**
     * Search results
     *
     * @dataProvider nsFilters
     * @param array $data
     * @param string $expected
     */
    public function testNs($data, $expected)
    {
        global $ID;
        $ID = 'test:plugins:tagging:start';

        $hlp = plugin_load('helper', 'tagging');

        $actual = $hlp->resolveNs($data);
        $this->assertEquals($expected, $actual);
    }
}

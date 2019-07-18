<?php
/**
 * Tagging Plugin (helper component)
 */
class helper_plugin_tagging_querybuilder extends DokuWiki_Plugin {

    /** @var string */
    protected $field;
    /** @var bool */
    protected $logicalAnd = false;
    /** @var array */
    protected $tags = [];
    /** @var array  */
    protected $ns = [];
    /** @var array */
    protected $notns = [];
    /** @var string */
    protected $pid;
    /** @var string */
    protected $tagger = '';
    /** @var int */
    protected $limit;
    /** @var string */
    protected $where;
    /** @var string */
    protected $orderby;
    /** @var string */
    protected $groupby;
    /** @var string */
    protected $having = '';
    /** @var array */
    protected $values = [];

    /**
     * Shorthand method: calls the appropriate getter deduced from $this->field
     *
     * @return helper_plugin_tagging_querybuilder
     */
    public function getQuery()
    {
        if (!$this->field) {
            throw new \RuntimeException('Failed to build a query, no field specified');
        }
        return ($this->field === 'pid') ? $this->getPages() : $this->getTags();
    }

    /**
     * Processes all parts of the query for fetching tagged pages
     *
     * Returns the query builder object
     *
     * @return helper_plugin_tagging_querybuilder
     */
    public function getPages()
    {
        $this->where = $this->getWhere();
        $this->groupby = 'pid';
        $this->orderby = "cnt DESC, pid";
        if ($this->tags && $this->logicalAnd) $this->having = ' HAVING cnt = ' . count($this->tags);

        return $this;
    }

    /**
     * Processes all parts of the query for fetching tags
     *
     * Returns the query builder object
     *
     * @return helper_plugin_tagging_querybuilder
     */
    public function getTags()
    {
        $this->where = $this->getWhere();
        $this->groupby = 'CLEANTAG(tag)';
        $this->orderby = 'CLEANTAG(tag)';

        return $this;
    }

    /**
     * Returns the SQL string
     * @return string
     */
    public function getSql()
    {
        return $this->composeSql();
    }

    /**
     * Returns the parameter values
     * @return array
     */
    public function getParameterValues()
    {
        return $this->values;
    }

    /**
     * Tags to search for
     * @param array $tags
     */
    public function setTags($tags)
    {
        $this->tags = $tags;
    }

    /**
     * Namespaces to limit search to
     * @param array $ns
     */
    public function includeNS($ns)
    {
        $this->ns = $this->globNS($ns);
    }

    /**
     * Namespaces to exclude from search
     * @param array $ns
     */
    public function excludeNS($ns)
    {
        $this->notns = $this->globNS($ns);
    }

    /**
     * Sets the logical operator used in tag search to AND
     * @param bool $and
     */
    public function setLogicalAnd($and)
    {
        $this->logicalAnd = (bool)$and;
    }

    /**
     * Result limit
     * @param int $limit
     */
    public function setLimit($limit)
    {
        $this->limit = $limit;
    }

    /**
     * Database field to select
     * @param string $field
     */
    public function setField($field)
    {
        $this->field = $field;
    }

    /**
     * Limit search to this page id
     * @param string $pid
     */
    public function setPid($pid)
    {
        $this->pid = $pid;
    }

    /**
     * Limit results to this tagger
     * @param string $tagger
     */
    public function setTagger($tagger)
    {
        $this->tagger = $tagger;
    }

    /**
     * Returns full query SQL
     * @return string
     */
    protected function composeSql()
    {
        $sql = "SELECT $this->field AS item, COUNT(*) AS cnt
                  FROM taggings
                 WHERE $this->where
              GROUP BY $this->groupby
              $this->having
              ORDER BY $this->orderby
              ";

        if ($this->limit) {
            $sql .= ' LIMIT ?';
            $this->values[] = $this->limit;
        }

        return $sql;
    }

    /**
     * Builds the WHERE part of query string
     * @return string
     */
    protected function getWhere()
    {
        $where = '1=1';

        if ($this->pid) {
            $where .= ' AND pid';
            $where .= $this->useLike($this->pid) ? ' GLOB' : ' =';
            $where .= '  ?';
            $this->values[] = $this->pid;
        }

        if ($this->tagger) {
            $where .= ' AND tagger = ?';
            $this->values[] = $this->tagger;
        }

        if ($this->ns) {
            $where .= ' AND ';

            $nsCnt = count($this->ns);
            $i = 0;
            foreach ($this->ns as $ns) {
                $where .= ' pid';
                $where .= ' GLOB';
                $where .= ' ?';
                if (++$i < $nsCnt) $where .= ' OR';
                $this->values[] = $ns;
            }
        }

        if ($this->notns) {
            $where .= ' AND ';

            $nsCnt = count($this->notns);
            $i = 0;
            foreach ($this->notns as $notns) {
                $where .= ' pid';
                $where .= ' NOT GLOB';
                $where .= ' ?';
                if (++$i < $nsCnt) $where .= ' AND';
                $this->values[] = $notns;
            }
        }

        if ($this->tags) {
            $where .= ' AND ';

            $tagCnt = count($this->tags);
            $i = 0;
            foreach ($this->tags as $tag) {
                $where .= ' CLEANTAG(tag)';
                $where .= $this->useLike($tag) ? ' GLOB' : ' =';
                $where .= ' CLEANTAG(?)';
                if (++$i < $tagCnt) $where .= ' OR';
                $this->values[] = $tag;
            }
        }

        $where .= ' AND GETACCESSLEVEL(pid) >= ' . AUTH_READ;


        return $where;
    }

    /**
     * Check if the given string is a LIKE statement
     *
     * @param string $value
     * @return bool
     */
    protected function useLike($value) {
        return strpos($value, '*') === 0 || strrpos($value, '*') === strlen($value) - 1;
    }

    /**
     * Converts namespaces into a wildcard form suitable for SQL queries
     *
     * @param array $item
     * @return array
     */
    protected function globNS(array $item)
    {
        return array_map(function($ns) {
            return cleanId($ns) . '*';
        }, $item);
    }

}

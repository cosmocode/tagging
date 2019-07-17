<?php
/**
 * Tagging Plugin (helper component)
 */
class helper_plugin_tagging_querybuilder extends DokuWiki_Plugin {

    const QUERY_ORDER = ['pid', 'tagger', 'tags', 'ns', 'notns'];

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
    /** @var string */
    protected $limit = '';
    /** @var string */
    protected $where;
    /** @var string */
    protected $orderby;
    /** @var string */
    protected $groupby;
    /** @var string */
    protected $having = '';

    /**
     * Shorthand method: deduces the appropriate getter from $this->field
     *
     * @return string
     */
    public function getQuery()
    {
        if (!$this->field) {
            throw new \RuntimeException('Failed to build a query, no field specified');
        }
        return ($this->field === 'pid') ? $this->getPages() : $this->getTags();
    }

    /**
     * Returns SQL query for fetching tagged pages
     *
     * @return string
     */
    public function getPages()
    {
        $this->where = $this->getWhere();
        $this->groupby = 'pid';
        $this->orderby = "cnt DESC, pid";
        if ($this->tags && $this->logicalAnd) $this->having = ' HAVING cnt = ' . count($this->tags);

        return $this->getSql();
    }

    /**
     * Returns SQL query for fetching tags
     *
     * @return string
     */
    public function getTags()
    {
        $this->where = $this->getWhere();
        $this->groupby = 'CLEANTAG(tag)';
        $this->orderby = 'CLEANTAG(tag)';

        return $this->getSql();
    }

    /**
     * @param array $tags
     */
    public function setTags($tags)
    {
        $this->tags = $tags;
    }

    /**
     * Namespaces to limit search to
     *
     * @param array $ns
     */
    public function includeNS($ns)
    {
        $this->ns = $ns;
    }

    /**
     * Namespaces to exclude from search
     *
     * @param array $ns
     */
    public function excludeNS($ns)
    {
        $this->notns = $ns;
    }

    /**
     * @param bool $and
     */
    public function setLogicalAnd($and)
    {
        $this->logicalAnd = $and;
    }

    /**
     * @param string $limit
     */
    public function setLimit($limit)
    {
        $this->limit = $limit ? " LIMIT $limit" : '';
    }

    /**
     * @param string $field
     */
    public function setField($field)
    {
        $this->field = $field;
    }

    /**
     * @param string $pid
     */
    public function setPid($pid)
    {
        $this->pid = $pid;
    }

    /**
     * @param string $tagger
     */
    public function setTagger($tagger)
    {
        $this->tagger = $tagger;
    }

    /**
     * Returns query SQL
     * @return string
     */
    protected function getSql()
    {
        $sql = "SELECT $this->field AS item, COUNT(*) AS cnt
                  FROM taggings
                 WHERE $this->where
              GROUP BY $this->groupby
              $this->having
              ORDER BY $this->orderby
                $this->limit
              ";

        return $sql;
    }

    /**
     * Builds query string. The order is important
     * @return string
     */
    protected function getWhere()
    {
        $where = '1=1';

        if ($this->pid) {
            $where .= ' AND pid = ?';
        }

        if ($this->tagger) {
            $where .= ' AND tagger = ?';
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
}

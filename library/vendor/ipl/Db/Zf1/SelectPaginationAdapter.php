<?php

namespace ipl\Db\Zf1;

use Icinga\Application\Benchmark;
use ipl\Data\Paginatable;
use Zend_Db_Select as ZfSelect;

class SelectPaginationAdapter implements Paginatable
{
    private $query;

    private $countQuery;

    public function __construct(ZfSelect $query)
    {
        $this->query = $query;
    }

    public function getCountQuery()
    {
        if ($this->countQuery === null) {
            $this->countQuery = (new CountQuery($this->query))->getQuery();
        }

        return $this->countQuery;
    }

    public function count()
    {
        Benchmark::measure('Running count() for pagination');
        $count = $this->query->getAdapter()->fetchOne(
            $this->getCountQuery()
        );
        Benchmark::measure("Counted $count rows");

        return $count;
    }

    public function limit($count = null, $offset = null)
    {
        $this->query->limit($count, $offset);
    }

    public function hasLimit()
    {
        return $this->getLimit() !== null;
    }

    public function getLimit()
    {
        return $this->query->getPart(ZfSelect::LIMIT_COUNT);
    }

    public function setLimit($limit)
    {
        $this->query->limit(
            $limit,
            $this->getOffset()
        );
    }

    public function hasOffset()
    {
        return $this->getOffset() !== null;
    }

    public function getOffset()
    {
        return $this->query->getPart(ZfSelect::LIMIT_OFFSET);
    }

    public function setOffset($offset)
    {
        $this->query->limit(
            $this->getLimit(),
            $offset
        );
    }
}

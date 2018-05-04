<?php

namespace dipl\Data;

use Icinga\Application\Benchmark;
use Icinga\Data\SimpleQuery;

class SimpleQueryPaginationAdapter implements Paginatable
{
    /** @var SimpleQuery */
    private $query;

    public function __construct(SimpleQuery $query)
    {
        $this->query = $query;
    }

    public function count()
    {
        Benchmark::measure('Running count() for pagination');
        $count = $this->query->count();
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
        return $this->query->getLimit();
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
        return $this->query->getOffset();
    }

    public function setOffset($offset)
    {
        $this->query->limit(
            $this->getLimit(),
            $offset
        );
    }
}

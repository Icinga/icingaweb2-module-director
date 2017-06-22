<?php

namespace ipl\Db\Zf1;

use Zend_Db_Select as ZfSelect;

class CountQuery
{
    /** @var ZfSelect */
    private $query;

    private $maxRows;

    /**
     * ZfCountQuery constructor.
     * @param ZfSelect $query
     */
    public function __construct(ZfSelect $query)
    {
        $this->query = $query;
    }

    public function setMaxRows($max)
    {
        $this->maxRows = $max;
        return $this;
    }

    public function getQuery()
    {
        if ($this->needsSubQuery()) {
            return $this->buildSubQuery();
        } else {
            return $this->buildSimpleQuery();
        }
    }

    protected function hasOneOf($parts)
    {
        foreach ($parts as $part) {
            if ($this->hasPart($part)) {
                return true;
            }
        }

        return false;
    }

    protected function hasPart($part)
    {
        $values = $this->query->getPart($part);
        return ! empty($values);
    }

    protected function needsSubQuery()
    {
        return null !== $this->maxRows || $this->hasOneOf([
            ZfSelect::GROUP,
            ZfSelect::UNION
        ]);
    }

    protected function buildSubQuery()
    {
        $sub = clone($this->query);
        $sub->limit(null, null);
        $query = new ZfSelect($this->query->getAdapter());
        $query->from($sub, ['cnt' => 'COUNT(*)']);
        if (null !== $this->maxRows) {
            $sub->limit($this->maxRows + 1);
        }

        return $query;
    }

    protected function buildSimpleQuery()
    {
        $query = clone($this->query);
        $query->reset(ZfSelect::COLUMNS);
        $query->reset(ZfSelect::ORDER);
        $query->reset(ZfSelect::LIMIT_COUNT);
        $query->reset(ZfSelect::LIMIT_OFFSET);
        $query->columns(['cnt' => 'COUNT(*)']);
        return $query;
    }
}

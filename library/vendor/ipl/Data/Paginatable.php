<?php

namespace ipl\Data;

use Countable;

interface Paginatable extends Countable
{
    /**
     * Set a limit count and offset
     *
     * @param   int $count  Number of rows to return
     * @param   int $offset Skip that many rows
     *
     * @return  self
     */
    public function limit($count = null, $offset = null);

    /**
     * Whether a limit is set
     *
     * @return bool
     */
    public function hasLimit();

    /**
     * Get the limit if any
     *
     * @return int|null
     */
    public function getLimit();

    /**
     * Set limit
     *
     * @param   int $count  Number of rows to return
     *
     * @return int|null
     */
    public function setLimit($limit);

    /**
     * Whether an offset is set
     *
     * @return bool
     */
    public function hasOffset();

    /**
     * Get the offset if any
     *
     * @return int|null
     */
    public function getOffset();

    /**
     * Set offset
     *
     * @param   int $offset Skip that many rows
     *
     * @return int|null
     */
    public function setOffset($offset);
}

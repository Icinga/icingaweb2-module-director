<?php

namespace Icinga\Module\Director\Daemon;

use Icinga\Module\Director\Db;
use React\Promise\PromiseInterface;

interface DbBasedComponent
{
    /**
     * @param Db $db
     *
     * @return PromiseInterface;
     */
    public function initDb(Db $db);

    /**
     * @return PromiseInterface;
     */
    public function stopDb();
}

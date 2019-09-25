<?php

namespace Icinga\Module\Director\Daemon;

use Icinga\Module\Director\Db;

interface DbBasedComponent
{
    /**
     * @param Db $db
     * @return \React\Promise\ExtendedPromiseInterface;
     */
    public function initDb(Db $db);

    /**
     * @return \React\Promise\ExtendedPromiseInterface;
     */
    public function stopDb();
}

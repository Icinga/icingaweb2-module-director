<?php

// SPDX-FileCopyrightText: 2019 Icinga GmbH <https://icinga.com>
// SPDX-License-Identifier: GPL-3.0-or-later

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

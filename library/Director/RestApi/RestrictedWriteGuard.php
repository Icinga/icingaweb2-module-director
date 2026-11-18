<?php

// SPDX-FileCopyrightText: 2026 Icinga GmbH <https://icinga.com>
// SPDX-License-Identifier: GPL-3.0-or-later

namespace Icinga\Module\Director\RestApi;

use Icinga\Authentication\Auth;
use Icinga\Exception\NotFoundError;
use Icinga\Module\Director\Db;
use Icinga\Module\Director\Objects\IcingaHost;
use Icinga\Module\Director\Objects\IcingaObject;
use Icinga\Module\Director\Restriction\HostFilterRestriction;
use Icinga\Security\SecurityException;

/**
 * Make sure a REST API write respects director/filter/hosts
 *
 * The API offers many ways to change a host: properties, vars, vars.<name>,
 * replacing the whole object and the variables endpoint. Instead of guarding
 * each of them, the changed host is checked after the write, which has to run
 * in a transaction.
 */
class RestrictedWriteGuard
{
    /** @var HostFilterRestriction */
    protected $hosts;

    public function __construct(Db $db, Auth $auth)
    {
        $this->hosts = new HostFilterRestriction($db, $auth);
    }

    /**
     * Assert that the object about to be changed may be accessed
     *
     * @param ?IcingaObject $object null if a new object is going to be created
     *
     * @return void
     *
     * @throws NotFoundError If the user isn't allowed to access the given host
     */
    public function before(?IcingaObject $object): void
    {
        // Hint: objects loaded by the controller have been checked already, service overrides have not
        if ($object instanceof IcingaHost && ! $this->hosts->allowsHost($object)) {
            throw new NotFoundError('Not found');
        }
    }

    /**
     * Assert that the changed object still respects the restriction
     *
     * @param IcingaObject $object As stored
     *
     * @return void
     *
     * @throws SecurityException
     */
    public function after(IcingaObject $object): void
    {
        if ($object instanceof IcingaHost && ! $this->hosts->allowsHost($object)) {
            throw new SecurityException(
                'Unable to store a host with the given properties because of insufficient permissions'
            );
        }
    }
}

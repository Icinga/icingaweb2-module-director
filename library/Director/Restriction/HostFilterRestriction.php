<?php

// SPDX-FileCopyrightText: 2026 Icinga GmbH <https://icinga.com>
// SPDX-License-Identifier: GPL-3.0-or-later

namespace Icinga\Module\Director\Restriction;

use Icinga\Authentication\Auth;
use Icinga\Data\Filter\Filter;
use Icinga\Module\Director\Auth\Restriction;
use Icinga\Module\Director\Db;
use Icinga\Module\Director\Objects\IcingaHost;
use Icinga\Module\Director\Objects\IcingaObject;
use Zend_Db_Select as ZfSelect;

/**
 * Restrict hosts and their services by the director/filter/hosts restriction
 */
class HostFilterRestriction extends ObjectRestriction
{
    protected $name = Restriction::FILTER_HOSTS;

    /** @var Db */
    protected $connection;

    /** @var ?Filter The restriction, prepared for HostFilterMatches */
    protected $filter;

    /** @var ?string[] */
    protected $allowedHostNames;

    public function __construct(Db $connection, Auth $auth)
    {
        parent::__construct($connection, $auth);
        $this->connection = $connection;
    }

    public function allows(IcingaObject $object)
    {
        if ($object instanceof IcingaHost) {
            return $this->allowsHost($object);
        }

        return true;
    }

    /**
     * Whether access to the given host is allowed
     *
     * Templates are never allowed, they are no host of anybody.
     *
     * @param IcingaHost $host
     *
     * @return bool
     */
    public function allowsHost(IcingaHost $host): bool
    {
        if (! $this->isRestricted()) {
            return true;
        }

        if ($host->isTemplate()) {
            return false;
        }

        return HostFilterMatches::matchesHost($host, $this->getFilter());
    }

    public function isRestricted()
    {
        return $this->getFilter() !== null;
    }

    protected function filterQuery(ZfSelect $query, $tableAlias = 'o')
    {
        $table = $this->getQueryTableByAlias($query, $tableAlias);
        switch ($table) {
            case 'icinga_host':
                $this->filterHostsQuery($query, $tableAlias);
                break;
            case 'icinga_service':
                // TODO: Alias is hardcoded, like in HostgroupRestriction
                $this->filterHostsQuery($query, 'h');
                break;
            // Hint: other tables are ignored, so please take care!
        }

        return $query;
    }

    /**
     * Apply the restriction to the given Hosts Query
     *
     * @param ZfSelect $query
     * @param string $tableAlias
     *
     * @return void
     */
    public function filterHostsQuery(ZfSelect $query, $tableAlias = 'o')
    {
        if (! $this->isRestricted()) {
            return;
        }

        $names = $this->listAllowedHostNames();
        if (empty($names)) {
            $query->where('(1 = 0)');
        } else {
            $query->where("{$tableAlias}.object_name IN (?)", $names);
        }
    }

    /**
     * Get the names of all allowed hosts
     *
     * @return string[]
     */
    protected function listAllowedHostNames(): array
    {
        if ($this->allowedHostNames === null) {
            $this->allowedHostNames = HostFilterMatches::listMatchingHostNames(
                $this->getFilter(),
                $this->connection
            );
        }

        return $this->allowedHostNames;
    }

    /**
     * Get the restriction prepared for HostFilterMatches
     *
     * @return ?Filter null if there is nothing to restrict
     */
    protected function getFilter(): ?Filter
    {
        if ($this->filter === null) {
            $filter = HostFilter::fromRestrictions($this->auth->getRestrictions($this->getName()));
            if ($filter !== null) {
                $this->filter = HostFilter::prepareForMatching($filter);
            }
        }

        return $this->filter;
    }
}

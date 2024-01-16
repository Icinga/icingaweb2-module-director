<?php

namespace Icinga\Module\Director\Integration\Icingadb;

use Icinga\Module\Director\Auth\Permission;
use Icinga\Module\Director\Auth\Restriction;
use Icinga\Module\Director\Integration\BackendInterface;
use Icinga\Module\Icingadb\Common\Auth;
use Icinga\Module\Icingadb\Common\Database;
use Icinga\Module\Icingadb\Model\Host;
use Icinga\Module\Icingadb\Model\Service;
use Icinga\Web\Url;
use ipl\Orm\Query;
use ipl\Stdlib\Filter;

class IcingadbBackend implements BackendInterface
{
    use Database;
    use Auth;

    public function hasHost(?string $hostName): bool
    {
        if ($hostName === null) {
            return false;
        }

        return $this->getHostQuery($hostName)->first() !== null;
    }

    public function hasService(?string $hostName, ?string $serviceName): bool
    {
        if ($hostName === null || $serviceName === null) {
            return false;
        }

        return $this->getServiceQuery($hostName, $serviceName)->first() !== null;
    }

    public function getHostUrl(?string $hostName): ?Url
    {
        if ($hostName === null) {
            return null;
        }

        return Url::fromPath('icingadb/host', ['name' => $hostName]);
    }

    public function canModifyHost(?string $hostName): bool
    {
        if ($hostName === null
            || ! $this->getAuth()->hasPermission(Permission::ICINGADB_HOSTS)
        ) {
            return false;
        }

        $query = $this->getHostQuery($hostName);

        return $query->first() !== null;
    }

    public function canModifyService(?string $hostName, ?string $serviceName): bool
    {
        if ($hostName === null
            || $serviceName === null
            || ! $this->getAuth()->hasPermission(Permission::ICINGADB_SERVICES)
        ) {
            return false;
        }

        $query = $this->getServiceQuery($hostName, $serviceName);

        return $query->first() !== null;
    }

    /**
     * Get the query for given host
     *
     * @param string $hostName
     *
     * @return Query
     */
    protected function getHostQuery(string $hostName): Query
    {
        $query = Host::on($this->getDb())
            ->filter(Filter::equal('host.name', $hostName));

        $this->applyDirectorRestrictions($query);

        return $query;
    }

    /**
     * Get the query for given host and service
     *
     * @param string $hostName
     * @param string $serviceName
     *
     * @return Query
     */
    protected function getServiceQuery(string $hostName, string $serviceName): Query
    {
        $query = Service::on($this->getDb())
            ->filter(Filter::all(
                Filter::equal('service.name', $serviceName),
                Filter::equal('host.name', $hostName)
            ));

        $this->applyDirectorRestrictions($query);

        return $query;
    }

    /**
     * Apply director restrictions on the given query
     *
     * @param Query $query
     */
    protected function applyDirectorRestrictions(Query $query): void
    {
        $queryFilter = Filter::any();
        foreach ($this->getAuth()->getRestrictions(Restriction::ICINGADB_RW_OBJECT_FILTER) as $restriction) {
            $queryFilter->add($this->parseRestriction($restriction, Restriction::ICINGADB_RW_OBJECT_FILTER));
        }

        $query->filter($queryFilter);
    }
}

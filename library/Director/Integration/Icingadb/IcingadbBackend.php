<?php

namespace Icinga\Module\Director\Integration\Icingadb;

use Icinga\Application\Modules\Module;
use Icinga\Module\Director\Integration\BackendInterface;
use Icinga\Module\Icingadb\Common\Auth;
use Icinga\Module\Icingadb\Common\Database;
use Icinga\Module\Icingadb\Model\Host;
use Icinga\Module\Icingadb\Model\Service;
use Icinga\Web\Url;
use ipl\Stdlib\Filter;

class IcingadbBackend implements BackendInterface
{
    use Database;
    use Auth;

    public function isAvailable(): bool
    {
        return Module::exists('icingadb');
    }

    public function hasHost(?string $hostName): bool
    {
        if ($hostName === null) {
            return false;
        }

        $query = Host::on($this->getDb())
            ->filter(Filter::equal('host.name', $hostName));

        $this->applyRestrictions($query);

        return $query->first() !== null;
    }

    public function hasService(?string $hostName, ?string $serviceName): bool
    {
        if ($hostName === null || $serviceName === null) {
            return false;
        }

        $query = Service::on($this->getDb())
            ->filter(Filter::all(
                Filter::equal('service.name', $serviceName),
                Filter::equal('host.name', $hostName)
            ));

        $this->applyRestrictions($query);

        return $query->first() !== null;
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
        if ($hostName === null) {
            return false;
        }
        // TODO: Implement canModifyService() method.
       return false;
    }

    public function canModifyService(?string $hostName, ?string $serviceName): bool
    {
        if ($hostName === null || $serviceName === null) {
            return false;
        }
        // TODO: Implement canModifyService() method.
        return false;
    }
}

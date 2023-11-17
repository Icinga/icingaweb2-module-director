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

    public function hasHost($hostname): bool
    {
        $query = Host::on($this->getDb())
            ->filter(Filter::equal('host.name', $hostname));

        $this->applyRestrictions($query);

        return $query->first() !== null;
    }

    public function hasService($hostname, $service): bool
    {
        $query = Service::on($this->getDb())
            ->filter(Filter::all(
                Filter::equal('service.name', $service),
                Filter::equal('host.name', $hostname)
            ));

        $this->applyRestrictions($query);

        return $query->first() !== null;
    }

    public function getHostUrl(string $hostname): Url
    {
        return Url::fromPath('icingadb/host', ['name' => $hostname]);
    }

    public function canModifyHost(string $hostName): bool
    {
        // TODO: Implement canModifyService() method.
       return false;
    }

    public function canModifyService(string $hostName, string $serviceName): bool
    {
        // TODO: Implement canModifyService() method.
        return false;
    }
}

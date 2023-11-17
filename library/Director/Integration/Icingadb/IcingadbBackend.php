<?php

namespace Icinga\Module\Director\Integration\Icingadb;

use gipfl\IcingaWeb2\Link;
use Icinga\Application\Icinga;
use Icinga\Data\Filter\Filter as DataFilter;
use Icinga\Module\Director\Integration\BackendInterface;
use Icinga\Module\Director\Objects\IcingaHost;
use Icinga\Module\Icingadb\Common\Auth;
use Icinga\Module\Icingadb\Common\Database;
use Icinga\Module\Icingadb\Model\Host;
use Icinga\Module\Icingadb\Model\Service;
use Icinga\Module\Icingadb\Redis\VolatileStateResults;
use Icinga\Web\Url;
use ipl\Stdlib\Filter;

class IcingadbBackend implements BackendInterface
{
    use Database;
    use Auth;

    public function isAvailable(): bool
    {
        $app = Icinga::app();
        $modules = $app->getModuleManager();
        return $modules->hasLoaded('icingadb');
    }

    public function hasHost($hostname): bool
    {
        $query = Host::on($this->getDb());
        $query->filter(Filter::equal('host.name', $hostname));

        $this->applyRestrictions($query);

        /** @var Host $host */
        $host = $query->first();

        return ($host !== null);
    }

    public function hasService($hostname, $service): bool
    {
        $query = Service::on($this->getDb());
        $query
            ->filter(Filter::all(
                Filter::equal('service.name', $service),
                Filter::equal('host.name', $hostname)
            ));

        $this->applyRestrictions($query);

        /** @var Service $service */
        $service = $query->first();

        return ($service !== null);
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

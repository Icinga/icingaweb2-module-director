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

    /** @var bool */
    protected $isAvailable;

    public function __construct()
    {
        $this->isAvailable = Module::exists('icingadb');
    }

    public function isAvailable(): bool
    {
        return $this->isAvailable;
    }

    public function hasHost(?string $hostName): bool
    {
        if ($hostName === null || ! $this->isAvailable()) {
            return false;
        }

        $query = Host::on($this->getDb())
            ->filter(Filter::equal('host.name', $hostName));

        $this->applyRestrictions($query);

        return $query->first() !== null;
    }

    public function hasService(?string $hostName, ?string $serviceName): bool
    {
        if ($hostName === null || $serviceName === null || ! $this->isAvailable()) {
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
        if ($hostName === null || ! $this->isAvailable()) {
            return null;
        }

        return Url::fromPath('icingadb/host', ['name' => $hostName]);
    }

    public function canModifyHost(?string $hostName): bool
    {
        if ($hostName === null || ! $this->isAvailable()) {
            return false;
        }
        // TODO: Implement canModifyService() method.
       return false;
    }

    public function canModifyService(?string $hostName, ?string $serviceName): bool
    {
        if ($hostName === null || $serviceName === null || ! $this->isAvailable()) {
            return false;
        }
        // TODO: Implement canModifyService() method.
        return false;
    }
}

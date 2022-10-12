<?php

namespace Icinga\Module\Director;

use gipfl\IcingaWeb2\Link;
use Icinga\Application\Icinga;
use Icinga\Module\Icingadb\Common\Auth;
use Icinga\Module\Icingadb\Common\Database;
use Icinga\Module\Icingadb\Model\Host;
use Icinga\Module\Icingadb\Model\Service;
use Icinga\Module\Icingadb\Redis\VolatileStateResults;
use ipl\Stdlib\Filter;

class MonitorBackendIcingadb implements MonitorBackend
{
    use Database;
    use Auth;

    public function isAvailable()
    {
        $app = Icinga::app();
        $modules = $app->getModuleManager();
        return $modules->hasLoaded('icingadb');
    }

    public function hasHost($hostname)
    {
        $query = Host::on($this->getDb());
        $query->filter(Filter::equal('host.name', $hostname));

        $this->applyRestrictions($query);

        /** @var Host $host */
        $host = $query->first();

        return ($host !== null);
    }

    public function hasService($hostname, $service)
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

    public function getHostLink($title, $hostname, array $attributes = null)
    {
        return Link::create(
            $title,
            'icingadb/host',
            ['name' => $hostname],
            $attributes
        );
    }

    public function getHostState($hostname)
    {
        $hostStates = [
            '0'  => 'up',
            '1'  => 'down',
            '2'  => 'unreachable',
            '99' => 'pending',
        ];

        $query = Host::on($this->getDb())->with(['state']);
        $query
            ->setResultSetClass(VolatileStateResults::class)
            ->filter(Filter::equal('host.name', $hostname));

        $this->applyRestrictions($query);

        /** @var Host $host */
        $host = $query->first();

        $result = (object) [
            'hostname'     => $hostname,
            'state'        => '99',
            'problem'      => '0',
            'acknowledged' => '0',
            'in_downtime'  => '0',
            'output'       => null,
        ];

        if ($host !== null) {
            // TODO: implement this for icingadb (function is unused atm)
            /**
            $query = $this->backend->select()->from('hostStatus', [
                'hostname'     => 'host_name',
                'state'        => 'host_state',
                'problem'      => 'host_problem',
                'acknowledged' => 'host_acknowledged',
                'in_downtime'  => 'host_in_downtime',
                'output'       => 'host_output',
            ])->where('host_name', $hostname);
            */
        }

        $result->state = $hostStates[$result->state];
        return $result;
    }
}

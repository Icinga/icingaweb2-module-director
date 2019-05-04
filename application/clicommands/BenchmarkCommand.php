<?php

namespace Icinga\Module\Director\Clicommands;

use Icinga\Application\Benchmark;
use Icinga\Data\Filter\Filter;
use Icinga\Data\Filter\FilterChain;
use Icinga\Data\Filter\FilterExpression;
use Icinga\Module\Director\Cli\Command;
use Icinga\Module\Director\Objects\HostGroupMembershipResolver;
use Icinga\Module\Director\Objects\IcingaHost;
use Icinga\Module\Director\Objects\IcingaHostVar;

class BenchmarkCommand extends Command
{
    public function resolvehostgroupsAction()
    {
        $resolver = new HostGroupMembershipResolver($this->db());
        $resolver->refreshDb();
    }

    public function filterAction()
    {
        $flat = array();

        /** @var FilterChain|FilterExpression $filter */
        $filter = Filter::fromQueryString(
            // 'object_name=*ic*2*&object_type=object'
            'vars.bpconfig=*'
        );
        Benchmark::measure('ready');
        $objs = IcingaHost::loadAll($this->db());
        Benchmark::measure('db done');

        foreach ($objs as $host) {
            $flat[$host->get('id')] = (object) array();
            foreach ($host->getProperties() as $k => $v) {
                $flat[$host->get('id')]->$k = $v;
            }
        }
        Benchmark::measure('objects ready');

        $vars = IcingaHostVar::loadAll($this->db());
        Benchmark::measure('vars loaded');
        foreach ($vars as $var) {
            if (! array_key_exists($var->get('host_id'), $flat)) {
                // Templates?
                continue;
            }
            $flat[$var->get('host_id')]->{'vars.' . $var->get('varname')} = $var->get('varvalue');
        }
        Benchmark::measure('vars done');

        foreach ($flat as $host) {
            if ($filter->matches($host)) {
                echo $host->object_name . "\n";
            }
        }
    }
}

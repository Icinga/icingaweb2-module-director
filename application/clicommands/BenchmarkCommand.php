<?php

namespace Icinga\Module\Director\Clicommands;

use Icinga\Application\Benchmark;
use Icinga\Data\Filter\Filter;
use Icinga\Module\Director\Cli\Command;
use Icinga\Module\Director\Objects\IcingaHost;
use Icinga\Module\Director\Objects\IcingaHostVar;

class BenchmarkCommand extends Command
{
    public function filterAction()
    {
        $flat = array();

        $filter = Filter::fromQueryString(
            // 'object_name=*ic*2*&object_type=object'
            'vars.bpconfig=*'
        );
        Benchmark::measure('ready');
        $objs = IcingaHost::loadAll($this->db());
        Benchmark::measure('db done');

        foreach ($objs as $host) {
            $flat[$host->id] = (object) array();
            foreach ($host->getProperties() as $k => $v) {
                $flat[$host->id]->$k = $v;
            }
        }
        Benchmark::measure('objects ready');

        $vars = IcingaHostVar::loadAll($this->db());
        Benchmark::measure('vars loaded');
        foreach ($vars as $var) {
            if (! array_key_exists($var->host_id, $flat)) {
                // Templates?
                continue;
            }
            $flat[$var->host_id]->{'vars.' . $var->varname} = $var->varvalue;
        }
        Benchmark::measure('vars done');

        foreach ($flat as $host) {
            if ($filter->matches($host)) {
                echo $host->object_name . "\n";
            }
        }
        return;

        Benchmark::measure('all done');
    }
}

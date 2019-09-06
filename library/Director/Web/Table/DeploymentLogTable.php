<?php

namespace Icinga\Module\Director\Web\Table;

use gipfl\IcingaWeb2\Link;
use gipfl\IcingaWeb2\Table\ZfQueryBasedTable;
use Icinga\Date\DateFormatter;

class DeploymentLogTable extends ZfQueryBasedTable
{
    use DbHelper;

    protected $activeStageName;

    public function setActiveStageName($name)
    {
        $this->activeStageName = $name;
        return $this;
    }

    public function assemble()
    {
        $this->getAttributes()->add('class', 'deployment-log');
    }

    public function renderRow($row)
    {
        $this->splitByDay($row->start_time);

        $shortSum = $this->getShortChecksum($row->config_checksum);
        $tr = $this::tr([
            $this::td(Link::create(
                $shortSum === null ? $row->peer_identity : [$row->peer_identity, " ($shortSum)"],
                'director/deployment',
                ['id' => $row->id]
            )),
            $this::td(DateFormatter::formatTime($row->start_time))
        ])->addAttributes(['class' => $this->getMyRowClasses($row)]);

        return $tr;
    }

    protected function getMyRowClasses($row)
    {
        if ($row->startup_succeeded === 'y') {
            $classes = ['succeeded'];
        } elseif ($row->startup_succeeded === 'n') {
            $classes = ['failed'];
        } elseif ($row->stage_collected === null) {
            $classes = ['pending'];
        } elseif ($row->dump_succeeded === 'y') {
            $classes = ['sent'];
        } else {
            // TODO: does this ever be stored?
            $classes = ['notsent'];
        }

        if ($this->activeStageName !== null
            && $row->stage_name === $this->activeStageName
        ) {
            $classes[] = 'running';
        }

        return $classes;
    }

    public function getColumns()
    {
        $columns = [
            'id'                => 'l.id',
            'peer_identity'     => 'l.peer_identity',
            'start_time'        => 'UNIX_TIMESTAMP(l.start_time)',
            'stage_collected'   => 'l.stage_collected',
            'dump_succeeded'    => 'l.dump_succeeded',
            'stage_name'        => 'l.stage_name',
            'startup_succeeded' => 'l.startup_succeeded',
            'config_checksum'   => 'l.config_checksum',
        ];

        return $columns;
    }

    public function prepareQuery()
    {
        return $this->db()->select()->from(
            array('l' => 'director_deployment_log'),
            $this->getColumns()
        )->order('l.start_time DESC')->limit(100);
    }
}

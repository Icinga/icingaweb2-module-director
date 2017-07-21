<?php

namespace Icinga\Module\Director\Web\Table;

use ipl\Html\Link;
use ipl\Web\Table\ZfQueryBasedTable;

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
        $this->attributes()->add('class', 'deployment-log');
    }

    public function renderRow($row)
    {
        $this->splitByDay($row->start_time);

        $shortSum = $this->getShortChecksum($row->config_checksum);
        $tr = $this::tr([
            $this::td(Link::create(
                [$row->peer_identity, " ($shortSum)"],
                'director/deployment',
                ['id' => $row->id]
            )),
            $this::td(strftime('%H:%M:%S', $row->start_time))
        ])->addAttributes(['class' => $this->getMyRowClasses($row)]);

        return $tr;
    }

    protected function getShortChecksum($checksum)
    {
        return substr(bin2hex($this->wantBinaryValue($checksum)), 0, 7);
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

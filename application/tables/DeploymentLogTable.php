<?php

namespace Icinga\Module\Director\Tables;

use Icinga\Module\Director\Web\Table\QuickTable;

class DeploymentLogTable extends QuickTable
{
    protected $activeStageName;

    public function setActiveStageName($name)
    {
        $this->activeStageName = $name;
        return $this;
    }

    protected function listTableClasses()
    {
        return array_merge(array('deployment-log'), parent::listTableClasses());
    }

    protected function getRowClasses($row)
    {
        if ($row->startup_succeeded === 'y') {
            $classes = array('succeeded');
        } elseif ($row->startup_succeeded === 'n') {
            $classes = array('failed');
        } elseif ($row->stage_collected === null) {
            $classes = array('pending');
        } elseif ($row->dump_succeeded === 'y') {
            $classes = array('sent');
        } else {
            // TODO: does this ever be stored?
            $classes = array('notsent');
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
        $columns = array(
            'id'                => 'l.id',
            'peer_identity'     => 'l.peer_identity',
            'start_time'        => 'l.start_time',
            'stage_collected'   => 'l.stage_collected',
            'dump_succeeded'    => 'l.dump_succeeded',
            'stage_name'        => 'l.stage_name',
            'startup_succeeded' => 'l.startup_succeeded',
            'checksum'          => 'LOWER(HEX(c.checksum))',
            'duration'          => "l.duration_dump || 'ms'",
        );

        if ($this->connection->isPgsql()) {
            $columns['checksum'] = "LOWER(ENCODE(c.checksum, 'hex'))";
        }

        return $columns;
    }

    protected function getActionUrl($row)
    {
        return $this->url('director/deployment', array('id' => $row->id));
    }

    public function getTitles()
    {
        $view = $this->view();
        return array(
            'peer_identity'     => $view->translate('Icinga Node'),
            'start_time'        => $view->translate('Time'),
        );
    }

    public function getBaseQuery()
    {
        $db = $this->connection()->getConnection();

        $query = $db->select()->from(
            array('l' => 'director_deployment_log'),
            array()
        )->joinLeft(
            array('c' => 'director_generated_config'),
            'c.checksum = l.config_checksum',
            array()
        )->order('l.start_time DESC');

        return $query;
    }
}

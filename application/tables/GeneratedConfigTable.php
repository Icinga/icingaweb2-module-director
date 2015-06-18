<?php

namespace Icinga\Module\Director\Tables;

use Icinga\Module\Director\Web\Table\QuickTable;

class GeneratedConfigTable extends QuickTable
{
    public function getColumns()
    {
        return array(
            'checksum'            => 'LOWER(HEX(c.checksum))',
            'duration'            => "c.duration || 'ms'",
            'files'               => 'COUNT(cf.file_checksum)',
            'last_related_change' => 'l.change_time',
            'activity_log_id'     => 'l.id'
        );
    }

    protected function getActionUrl($row)
    {
        return $this->url('director/config/show', array('checksum' => $row->checksum));
    }

    public function getTitles()
    {
        $view = $this->view();
        return array(
            'checksum' => $view->translate('Checksum'),
            'duration' => $view->translate('Duration'),
            'last_related_change' => $view->translate('Last related change'),
        );
    }

    public function fetchData()
    {
        $db = $this->connection()->getConnection();

        $query = $db->select()->from(
            array('l' => 'director_activity_log'),
            $this->getColumns()
        )->joinRight(
            array('c' => 'director_generated_config'),
            'c.last_activity_checksum = l.checksum',
            array()
        )->joinLeft(
            array('cf' => 'director_generated_config_file'),
            'cf.config_checksum = c.checksum',
            array()
        )->group('c.checksum')->order('l.change_time DESC');

        return $db->fetchAll($query);
    }
}

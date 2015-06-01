<?php

namespace Icinga\Module\Director\Tables;

use Icinga\Module\Director\Web\Table\QuickTable;

class IcingaZoneTable extends QuickTable
{
    public function getColumns()
    {
        return array(
            'id'      => 'z.id',
            'zone'    => 'z.object_name',
        );
    }

    protected function getActionUrl($row)
    {
        return $this->url('director/object/zone', array('id' => $row->id));
    }

    public function getTitles()
    {
        $view = $this->view();
        return array(
            'zone'    => $view->translate('Zone'),
        );
    }

    public function fetchData()
    {
        $db = $this->connection()->getConnection();
        $query = $db->select()->from(
            array('z' => 'icinga_zone'),
            $this->getColumns()
        );

        return $db->fetchAll($query);
    }
}

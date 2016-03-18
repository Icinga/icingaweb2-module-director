<?php

namespace Icinga\Module\Director\Tables;

use Icinga\Module\Director\Web\Table\IcingaObjectTable;

// TODO: quickform once apply has been moved elsewhere
class IcingaServiceTable extends IcingaObjectTable
{
    protected $searchColumns = array(
        'service',
    );

    public function getColumns()
    {
        return array(
            'id'          => 's.id',
            'service'     => 's.object_name',
            'object_type' => 's.object_type',
        );
    }

    protected function getActionUrl($row)
    {
        // TODO: Remove once we got a separate apply table
        if ($row->object_type === 'apply') {
            $params['id'] = $row->id;
        } else {
            $params = array('name' => $row->service);

        }

        return $this->url('director/service', $params);
    }

    public function getTitles()
    {
        $view = $this->view();
        return array(
            'service' => $view->translate('Servicename'),
        );
    }

    public function getUnfilteredQuery()
    {
        $db = $this->connection()->getConnection();
        $query = $db->select()->from(
            array('s' => 'icinga_service'),
            array()
        );

        return $query;
    }

    public function getBaseQuery()
    {
        // TODO: remove apply
        return $this->getUnfilteredQuery()->where(
            's.object_type IN (?)',
            array('template', 'apply')
        );
    }
}

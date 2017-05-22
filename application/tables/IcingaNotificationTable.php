<?php

namespace Icinga\Module\Director\Tables;

use Icinga\Module\Director\Web\Table\QuickTable;

class IcingaNotificationTable extends QuickTable
{
    protected $searchColumns = array(
        'notification',
    );

    public function getColumns()
    {
        return array(
            'id'                    => 'n.id',
            'object_type'           => 'n.object_type',
            'notification'          => 'n.object_name',
            'disabled'              => 'n.disabled',
        );
    }

    protected function getActionUrl($row)
    {
        return $this->url('director/notification', array('id' => $row->id));
    }

    public function getTitles()
    {
        $view = $this->view();
        return array(
            'notification' => $view->translate('Notification'),
        );
    }

    protected function getRowClasses($row)
    {
        if ($row->disabled === 'y') {
            return 'disabled';
        } else {
            return null;
        }
    }

    public function getUnfilteredQuery()
    {
        return $this->db()->select()->from(
            array('n' => 'icinga_notification'),
            array()
        )->order('n.object_name');
    }

    public function getBaseQuery()
    {
        return $this->getUnfilteredQuery();
    }
}

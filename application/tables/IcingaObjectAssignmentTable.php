<?php

namespace Icinga\Module\Director\Tables;

use Icinga\Module\Director\Objects\IcingaObject;
use Icinga\Module\Director\Web\Table\QuickTable;
use Icinga\Web\Url;

// TODO: we need different ones
class IcingaObjectAssignmentTable extends QuickTable
{
    protected $object;

    public function setObject(IcingaObject $object)
    {
        $this->object = $object;
        $this->setConnection($object->getConnection());
        return $this;
    }

    protected $searchColumns = array(
        'filter',
    );

    public function getColumns()
    {
        return array(
            'id'            => 'oa.id',
            'filter_string' => 'oa.filter_string',
        );
    }

    protected function getActionUrl($row)
    {
        return Url::fromRequest()->with('rule_id', $row->id);
    }

    public function getTitles()
    {
        $view = $this->view();
        return array(
            'filter_string' => $view->translate('Filter string'),
        );
    }

    public function getBaseQuery()
    {
        $db = $this->connection()->getConnection();
        $otable = $this->object->getTableName() . '_assignment';
        $oname  = $this->object->getShortTableName();

        $query = $db->select()->from(
            array('oa' => $otable),
            array()
        )->where('oa.' . $oname . '_id = ?', $this->object->id)
         ->order('oa.filter_string ASC');

        return $query;
    }
}

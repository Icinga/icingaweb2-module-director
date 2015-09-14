<?php

namespace Icinga\Module\Director\Tables;

use Icinga\Module\Director\Objects\IcingaObject;
use Icinga\Module\Director\Web\Table\QuickTable;
use Icinga\Web\Url;

class IcingaObjectDatafieldTable extends QuickTable
{
    protected $object;

    public function setObject(IcingaObject $object)
    {
        $this->object = $object;
        $this->setConnection($object->getConnection());
        return $this;
    }

    protected $searchColumns = array(
        'varname',
    );

    public function getColumns()
    {
        return array(
            'id'          => 'f.id',
            'varname'     => 'f.varname',
            'caption'     => 'f.caption',
            'description' => 'f.description',
            'datatype'    => 'f.datatype',
            'required'    => "CASE WHEN of.is_required = 'y' THEN 'mandatory' ELSE 'optional' END",
        );
    }

    protected function getActionUrl($row)
    {
        return Url::fromRequest()->with('field_id', $row->id);
    }

    public function getTitles()
    {
        $view = $this->view();
        return array(
            'caption'     => $view->translate('Label'),
            'varname'     => $view->translate('Field name'),
            'required'    => $view->translate('Required'),
        );
    }

    public function getBaseQuery()
    {
        $db = $this->connection()->getConnection();
        $otable = $this->object->getTableName() . '_field';
        $oname  = $this->object->getShortTableName();

        $query = $db->select()->from(
            array('of' => $otable),
            array()
        )->join(
            array('f' => 'director_datafield'),
            'f.id = of.datafield_id',
            array()
        )->where('of.' . $oname . '_id = ?', $this->object->id)
         ->order('caption ASC');

        return $query;
    }
}

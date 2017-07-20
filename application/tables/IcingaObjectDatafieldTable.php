<?php

namespace Icinga\Module\Director\Tables;

use Icinga\Data\DataArray\ArrayDatasource;
use Icinga\Module\Director\Objects\IcingaObject;
use Icinga\Module\Director\Web\Form\IcingaObjectFieldLoader;
use Icinga\Module\Director\Web\Table\QuickTable;
use Icinga\Web\Url;

class IcingaObjectDatafieldTable extends QuickTable
{
    protected $object;

    /** @var int */
    protected $objectId;

    public function setObject(IcingaObject $object)
    {
        $this->object = $object;
        $this->objectId = (int) $object->id;
        $this->setConnection($object->getConnection());
        return $this;
    }

    protected $searchColumns = array(
        'varname',
        'caption'
    );

    public function getColumns()
    {
        return array(
            'object_id',
            'var_filter',
            'is_required',
            'id',
            'varname',
            'caption',
            'description',
            'datatype',
            'format',
        );
    }

    protected function getActionUrl($row)
    {
        if ((int) $row->object_id !== $this->objectId) {
            return null;
        }

        return Url::fromRequest()->with('field_id', $row->id);
    }

    protected function getRowClasses($row)
    {
        if ((int) $row->object_id !== $this->objectId) {
            return array('disabled');
        }
    }

    public function getTitles()
    {
        $view = $this->view();
        return array(
            'caption'     => $view->translate('Label'),
            'varname'     => $view->translate('Field name'),
            'is_required' => $view->translate('Mandatory'),
        );
    }

    public function count()
    {
        return $this->getBaseQuery()->count();
    }

    public function fetchData()
    {
        return $this->getBaseQuery()->fetchAll();
    }

    public function getBaseQuery()
    {
        $loader = new IcingaObjectFieldLoader($this->object);
        $fields = $loader->fetchFieldDetailsForObject($this->object);
        $ds = new ArrayDatasource($fields);
        return $ds->select();
    }
}

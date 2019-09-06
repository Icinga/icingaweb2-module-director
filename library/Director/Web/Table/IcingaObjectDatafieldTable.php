<?php

namespace Icinga\Module\Director\Web\Table;

use Icinga\Data\DataArray\ArrayDatasource;
use Icinga\Module\Director\Objects\IcingaObject;
use Icinga\Module\Director\Web\Form\IcingaObjectFieldLoader;
use Icinga\Web\Url;
use gipfl\IcingaWeb2\Link;
use gipfl\IcingaWeb2\Table\SimpleQueryBasedTable;

class IcingaObjectDatafieldTable extends SimpleQueryBasedTable
{
    protected $object;

    /** @var int */
    protected $objectId;

    public function __construct(IcingaObject $object)
    {
        $this->object = $object;
        $this->objectId = (int) $object->id;
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

    public function getColumnsToBeRendered()
    {
        return array(
            'caption'     => $this->translate('Label'),
            'varname'     => $this->translate('Field name'),
            'is_required' => $this->translate('Mandatory'),
        );
    }

    public function renderRow($row)
    {
        $definedOnThis = (int) $row->object_id === $this->objectId;
        if ($definedOnThis) {
            $caption = new Link(
                $row->caption,
                Url::fromRequest()->with('field_id', $row->id)
            );
        } else {
            $caption = $row->caption;
        }

        $row = $this::row([
            $caption,
            $row->varname,
            $row->is_required
        ]);

        if (! $definedOnThis) {
            $row->getAttributes()->add('class', 'disabled');
        }

        return $row;
    }

    public function prepareQuery()
    {
        $loader = new IcingaObjectFieldLoader($this->object);
        $fields = $loader->fetchFieldDetailsForObject($this->object);
        $ds = new ArrayDatasource($fields);
        return $ds->select();
    }
}

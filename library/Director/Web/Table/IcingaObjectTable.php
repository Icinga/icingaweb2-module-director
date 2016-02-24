<?php

namespace Icinga\Module\Director\Web\Table;

abstract class IcingaObjectTable extends QuickTable
{
    protected function getRowClasses($row)
    {
        switch ($row->object_type) {
            case 'object':
                return 'icinga-object';
            case 'template':
                return 'icinga-template';
            case 'external_object':
                return 'icinga-object-external';
            case 'apply':
                return 'icinga-apply';
        }
    }

    protected function listTableClasses()
    {
        return array_merge(array('icinga-objects'), parent::listTableClasses());
    }
}

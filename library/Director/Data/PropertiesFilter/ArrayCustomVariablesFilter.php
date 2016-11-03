<?php

namespace Icinga\Module\Director\Data\PropertiesFilter;

class ArrayCustomVariablesFilter extends CustomVariablesFilter
{
    public function match($type, $name, $object = null)
    {
        return parent::match($type, $name, $object)
        && $object !== null
        && preg_match('/DataType(Meta|)Array[\w]*$/', $object->datatype);
    }
}

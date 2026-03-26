<?php

// SPDX-FileCopyrightText: 2018 Icinga GmbH <https://icinga.com>
// SPDX-License-Identifier: GPL-3.0-or-later

namespace Icinga\Module\Director\Data\PropertiesFilter;

class ArrayCustomVariablesFilter extends CustomVariablesFilter
{
    public function match($type, $name, $object = null)
    {
        return parent::match($type, $name, $object)
        && $object !== null
        && isset($object->datatype)
        && (
            preg_match('/DataTypeArray[\w]*$/', $object->datatype)
            || (
                preg_match('/DataTypeDatalist$/', $object->datatype)
                && $object->format === 'json'
            )
        );
    }
}

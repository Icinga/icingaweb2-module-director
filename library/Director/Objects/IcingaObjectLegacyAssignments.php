<?php

namespace Icinga\Module\Director\Objects;

use LogicException;

/**
 * This class is required for historical reasons
 *
 * Objects with assignments in your activity log would otherwise not be able
 * to render themselves
 */
class IcingaObjectLegacyAssignments
{
    public static function applyToObject(IcingaObject $object, $values)
    {
        if (! $object->supportsAssignments()) {
            throw new LogicException(sprintf(
                'I can only assign for applied objects, got %s',
                $object->object_type
            ));
        }

        if ($values === null) {
            return $object;
        }

        if (! is_array($values)) {
            static::throwCompatError();
        }

        if (empty($values)) {
            return $object;
        }

        $assigns = array();
        $ignores = array();
        foreach ($values as $type => $value) {
            if (strpos($value, '|') !== false || strpos($value, '&') !== false) {
                $value = '(' . $value . ')';
            }

            if ($type === 'assign') {
                $assigns[] = $value;
            } elseif ($type === 'ignore') {
                $ignores[] = $value;
            } else {
                static::throwCompatError();
            }
        }

        $assign = implode('|', $assigns);
        $ignore = implode('&', $ignores);
        if (empty($assign)) {
            $filter = $ignore;
        } elseif (empty($ignore)) {
            $filter = $assign;
        } else {
            if (count($assigns) === 1) {
                $filter = $assign . '&' . $ignore;
            } else {
                $filter = '(' . $assign . ')&(' . $ignore . ')';
            }
        }

        $object->assign_filter = $filter;

        return $object;
    }

    protected static function throwCompatError()
    {
        throw new LogicException(
            'You ran into an unexpected compatibility issue. Please report'
            . ' this with details helping us to reproduce this to the'
            . ' Icinga project'
        );
    }
}

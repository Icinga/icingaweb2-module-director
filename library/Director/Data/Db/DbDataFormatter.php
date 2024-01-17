<?php

namespace Icinga\Module\Director\Data\Db;

use InvalidArgumentException;

class DbDataFormatter
{
    public static function normalizeBoolean($value): ?string
    {
        if ($value === 'y' || $value === '1' || $value === true || $value === 1) {
            return 'y';
        }
        if ($value === 'n' || $value === '0' || $value === false || $value === 0) {
            return 'n';
        }
        if ($value === '' || $value === null) {
            return null;
        }

        throw new InvalidArgumentException(sprintf(
            'Got invalid boolean: %s',
            var_export($value, true)
        ));
    }

    public static function booleanForDbValue($value): ?bool
    {
        if ($value === 'y') {
            return true;
        }
        if ($value === 'n') {
            return false;
        }

        return $value; // let this fail elsewhere, if not null
    }
}

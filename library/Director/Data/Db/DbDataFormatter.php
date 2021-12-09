<?php

namespace Icinga\Module\Director\Data\Db;

use InvalidArgumentException;

class DbDataFormatter
{
    public static function normalizeBoolean($value)
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
            var_export($value, 1)
        ));
    }
}

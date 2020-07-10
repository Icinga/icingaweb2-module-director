<?php

namespace Icinga\Module\Director\Data;

use InvalidArgumentException;
use ipl\Html\Error;

class RecursiveUtf8Validator
{
    protected static $rowNum;

    protected static $column;

    /**
     * @param array $rows Usually array of stdClass
     * @return bool
     */
    public static function validateRows($rows)
    {
        foreach ($rows as self::$rowNum => $row) {
            foreach ($row as self::$column => $value) {
                static::assertUtf8($value);
            }
        }

        return true;
    }

    protected static function assertUtf8($value)
    {
        if (\is_string($value)) {
            static::assertUtf8String($value);
        } elseif (\is_array($value) || $value instanceof \stdClass) {
            foreach ((array) $value as $k => $v) {
                static::assertUtf8($k);
                static::assertUtf8($v);
            }
        } elseif ($value !== null && !\is_scalar($value)) {
            throw new InvalidArgumentException("Cannot validate " . Error::getPhpTypeName($value));
        }
    }

    protected static function assertUtf8String($string)
    {
        if (@\iconv('UTF-8', 'UTF-8', $string) != $string) {
            $row = self::$rowNum;
            if (is_int($row)) {
                $row++;
            }
            throw new InvalidArgumentException(\sprintf(
                'Invalid UTF-8 on row %s, column %s: "%s" (%s)',
                $row,
                self::$column,
                \iconv('UTF-8', 'UTF-8//IGNORE', $string),
                '0x' . \bin2hex($string)
            ));
        }
    }
}

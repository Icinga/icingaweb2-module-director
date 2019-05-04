<?php

namespace Icinga\Module\Director\IcingaConfig;

use InvalidArgumentException;

class IcingaLegacyConfigHelper
{
    public static function renderKeyValue($key, $value, $prefix = '    ')
    {
        return self::renderKeyOperatorValue($key, "\t", $value, $prefix);
    }

    public static function renderKeyOperatorValue($key, $operator, $value, $prefix = '    ')
    {
        $string = sprintf(
            "%s%s%s",
            $key,
            $operator,
            $value
        );

        if ($prefix && strpos($string, "\n") !== false) {
            return $prefix . implode("\n" . $prefix, explode("\n", $string)) . "\n";
        }

        return $prefix . $string . "\n";
    }

    public static function renderBoolean($value)
    {
        if ($value === 'y') {
            return '1';
        } elseif ($value === 'n') {
            return '0';
        } else {
            throw new InvalidArgumentException('%s is not a valid boolean', $value);
        }
    }

    // TODO: Double-check legacy "encoding"
    public static function renderString($string)
    {
        $special = [
            '/\\\/',
            '/\$/',
            '/\t/',
            '/\r/',
            '/\n/',
            // '/\b/', -> doesn't work
            '/\f/',
        ];

        $replace = [
            '\\\\\\',
            '\\$',
            '\\t',
            '\\r',
            '\\n',
            // '\\b',
            '\\f',
        ];

        $string = preg_replace($special, $replace, $string);

        return $string;
    }

    /**
     * @param array $array
     * @return string
     */
    public static function renderArray($array)
    {
        $data = [];
        foreach ($array as $entry) {
            if ($entry instanceof IcingaConfigRenderer) {
                // $data[] = $entry;
                $data[] = 'INVALID_ARRAY_MEMBER';
            } else {
                $data[] = self::renderString($entry);
            }
        }

        return implode(', ', $data);
    }

    public static function renderDictionary($dictionary)
    {
        return 'INVALID_DICTIONARY';
    }

    public static function renderExpression($string)
    {
        return 'INVALID_EXPRESSION';
    }

    public static function alreadyRendered($string)
    {
        return new IcingaConfigRendered($string);
    }

    public static function renderInterval($interval)
    {
        if ($interval < 60) {
            $interval = 60;
        }
        return $interval / 60;
    }
}

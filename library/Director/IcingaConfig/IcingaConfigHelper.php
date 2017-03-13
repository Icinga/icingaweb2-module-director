<?php

namespace Icinga\Module\Director\IcingaConfig;

use Icinga\Exception\IcingaException;
use Icinga\Exception\ProgrammingError;

class IcingaConfigHelper
{
    /**
     * Reserved words according to
     * https://docs.icinga.com/icinga2/snapshot/doc/module/icinga2/chapter/language-reference#reserved-keywords
     */
    protected static $reservedWords = array(
        'object',
        'template',
        'include',
        'include_recursive',
        'ignore_on_error',
        'library',
        'null',
        'true',
        'false',
        'const',
        'var',
        'this',
        'use',
        'apply',
        'to',
        'where',
        'import',
        'assign',
        'ignore',
        'function',
        'return',
        'for',
        'if',
        'else',
        'in',
        'current_filename',
        'current_line',
    );

    public static function renderKeyValue($key, $value, $prefix = '    ')
    {
        return self::renderKeyOperatorValue($key, '=', $value, $prefix);
    }

    public static function renderKeyOperatorValue($key, $operator, $value, $prefix = '    ')
    {
        $string = sprintf(
            "%s %s %s",
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
        if ($value === 'y' || $value === true) {
            return 'true';
        } elseif ($value === 'n' || $value === false) {
            return 'false';
        } else {
            throw new ProgrammingError('%s is not a valid boolean', $value);
        }
    }

    protected static function renderInteger($value)
    {
        return (string) $value;
    }

    protected static function renderFloat($value)
    {
        // Render .0000 floats as integers, mainly because of some JSON
        // implementations:
        if ((string) (int) $value === (string) $value) {
            return static::renderInteger((int) $value);
        } else {
            return sprintf('%F', $value);
        }
    }

    protected static function renderNull()
    {
        return 'null';
    }

    // TODO: Find out how to allow multiline {{{...}}} strings.
    //       Parameter? Dedicated method? Always if \n is found?
    public static function renderString($string)
    {
        $special = array(
            '/\\\/',
            '/"/',
            '/\$/',
            '/\t/',
            '/\r/',
            '/\n/',
            // '/\b/', -> doesn't work
            '/\f/'
        );

        $replace = array(
            '\\\\\\',
            '\\"',
            '\\$',
            '\\t',
            '\\r',
            '\\n',
            // '\\b',
            '\\f',
        );

        $string = preg_replace($special, $replace, $string);

        return '"' . $string . '"';
    }

    public static function renderPhpValue($value)
    {
        if (is_null($value)) {
            return static::renderNull();
        } elseif (is_bool($value)) {
            return static::renderBoolean($value);
        } elseif (is_integer($value)) {
            return static::renderInteger($value);
        } elseif (is_float($value)) {
            return static::renderFloat($value);
        // TODO:
        // } elseif (is_object($value) || static::isAssocArray($value)) {
        //     return static::renderHash($value, $prefix)
        // TODO: also check array
        } elseif (is_array($value)) {
            return static::renderArray($value);
        } elseif (is_string($value)) {
            return static::renderString($value);
        } else {
            throw new IcingaException('Unexpected type %s', var_export($value, 1));
        }
    }

    public static function renderDictionaryKey($key)
    {
        if (preg_match('/^[a-z_]+[a-z0-9_]*$/i', $key)) {
            return static::escapeIfReserved($key);
        } else {
            return static::renderString($key);
        }
    }

    // Requires an array
    public static function renderArray($array)
    {
        $data = array();
        foreach ($array as $entry) {
            if ($entry instanceof IcingaConfigRenderer) {
                $data[] = $entry;
            } else {
                $data[] = self::renderString($entry);
            }
        }

        return static::renderEscapedArray($data);
    }

    public static function renderEscapedArray($array)
    {
        $str = '[ ' . implode(', ', $array) . ' ]';

        if (strlen($str) < 60) {
            return $str;
        }

        // Prefix for toConfigString?
        return "[\n    " . implode(",\n    ", $array) . "\n]";
    }

    public static function renderDictionary($dictionary)
    {
        $vals = array();
        foreach ($dictionary as $key => $value) {
            $vals[$key] = rtrim(
                self::renderKeyValue(
                    self::renderDictionaryKey($key),
                    $value
                )
            );
        }

        if (empty($vals)) {
            return '{}';
        }
        ksort($vals, SORT_STRING);

        // Prefix for toConfigString?
        return "{\n" . implode("\n", $vals) . "\n}";
    }

    public static function renderExpression($string)
    {
        return "{{\n    " . $string . "\n}}";
    }

    public static function alreadyRendered($string)
    {
        return new IcingaConfigRendered($string);
    }

    public static function isReserved($string)
    {
        return in_array($string, self::$reservedWords, true);
    }

    public static function escapeIfReserved($string)
    {
        if (self::isReserved($string)) {
            return '@' . $string;
        } else {
            return $string;
        }
    }

    public static function isValidInterval($interval)
    {
        if (ctype_digit($interval)) {
            return true;
        }

        $parts = preg_split('/\s+/', $interval, -1, PREG_SPLIT_NO_EMPTY);
        foreach ($parts as $part) {
            if (! preg_match('/^(\d+)([dhms]?)$/', $part)) {
                return false;
            }
        }

        return true;
    }

    public static function parseInterval($interval)
    {
        if ($interval === null || $interval === '') {
            return null;
        }

        if (ctype_digit($interval)) {
            return (int) $interval;
        }

        $parts = preg_split('/\s+/', $interval, -1, PREG_SPLIT_NO_EMPTY);
        $value = 0;
        foreach ($parts as $part) {
            if (! preg_match('/^(\d+)([dhms]?)$/', $part, $m)) {
                throw new ProgrammingError(
                    '"%s" is not a valid time (duration) definition',
                    $interval
                );
            }
            switch ($m[2]) {
                case 'd':
                    $value += $m[1] * 86400;
                    break;
                case 'h':
                    $value += $m[1] * 3600;
                    break;
                case 'm':
                    $value += $m[1] * 60;
                    break;
                default:
                    $value += (int) $m[1];
            }
        }

        return $value;
    }

    public static function renderInterval($interval)
    {
        // TODO: compat only, do this at munge time. All db fields should be int
        $seconds = self::parseInterval($interval);
        if ($seconds === 0) {
            return '0s';
        }

        $steps = array(
            'd' => 86400,
            'h' => 3600,
            'm' => 60,
        );

        foreach ($steps as $unit => $duration) {
            if ($seconds % $duration === 0) {
                return (int) floor($seconds / $duration) . $unit;
            }
        }

        return $seconds . 's';
    }

    public static function stringHasMacro($string)
    {
        return preg_match('/(?<!\$)\$[\w\.]+\$(?!\$)/', $string);
    }

    public static function renderStringWithVariables($string)
    {
        $string = preg_replace(
            '/(?<!\$)\$([\w\.]+)\$(?!\$)/',
            '" + ${1} + "',
            static::renderString($string)
        );

        if (substr($string, 0, 5) === '"" + ') {
            $string = substr($string, 5);
        }
        if (substr($string, -5) === ' + ""') {
            $string = substr($string, 0, -5);
        }

        return $string;
    }
}

<?php

namespace Icinga\Module\Director\IcingaConfig;

use Icinga\Exception\ProgrammingError;

class IcingaConfigHelper
{
    /**
     * Reserved words according to
     * http://docs.icinga.org/icinga2/snapshot/doc/module/icinga2/chapter/language-reference#reserved-keywords
     */
    protected static $reservedWords = array(
        'object',
        'template',
        'include',
        'include_recursive',
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
        if ($value === 'y') {
            return 'true';
        } elseif ($value === 'n') {
            return 'false';
        } else {
            throw new ProgrammingError('%s is not a valid boolean', $value);
        }
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
        $str = '[ ' . implode(', ', $data) . ' ]';

        if (strlen($str) < 60) {
            return $str;
        }

        // Prefix for toConfigString?
        return "[\n    " . implode(",\n    ", $data) . "\n]";

    }

    public static function renderDictionary($dictionary)
    {
        $vals = array();
        foreach ($dictionary as $key => $value) {
            $vals[$key] = rtrim(self::renderKeyValue(self::renderString($key), $value));
        }
        ksort($vals);

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
        return in_array($string, self::$reservedWords);
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
        $value = 0;
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

        $parts = array();
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
}

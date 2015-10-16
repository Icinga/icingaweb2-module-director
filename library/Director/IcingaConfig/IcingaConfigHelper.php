<?php

namespace Icinga\Module\Director\IcingaConfig;

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
        $string = sprintf(
            "%s = %s",
            $key,
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
            $vals[] = rtrim(self::renderKeyValue(self::renderString($key), $value));
        }

        // Prefix for toConfigString?
        return "{\n" . implode("\n", $vals) . "\n}";

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
}

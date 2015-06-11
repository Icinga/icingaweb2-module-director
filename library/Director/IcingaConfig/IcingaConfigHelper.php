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

    public static function renderKeyValue($key, $value, $prefix = '')
    {
        return sprintf(
            "%s%s = %s\n",
            $prefix,
            $key,
            $value
        );
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

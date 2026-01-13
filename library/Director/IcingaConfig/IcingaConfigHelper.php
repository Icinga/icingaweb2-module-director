<?php

namespace Icinga\Module\Director\IcingaConfig;

use Icinga\Module\Director\CustomVariable\CustomVariableString;
use InvalidArgumentException;

use function ctype_digit;
use function explode;
use function floor;
use function implode;
use function preg_match;
use function preg_split;
use function sprintf;
use function strlen;
use function strpos;
use function substr;

class IcingaConfigHelper
{
    /**
     * Reserved words according to
     * https://icinga.com/docs/icinga2/latest/doc/17-language-reference/#reserved-keywords
     */
    protected static $reservedWords = [
        'object',
        'template',
        'include',
        'include_recursive',
        'include_zones',
        'library',
        'null',
        'true',
        'false',
        'const',
        'var',
        'this',
        'globals',
        'locals',
        'use',
        'default',
        'ignore_on_error',
        'current_filename',
        'current_line',
        'apply',
        'to',
        'where',
        'import',
        'assign',
        'ignore',
        'function',
        'return',
        'break',
        'continue',
        'for',
        'if',
        'else',
        'while',
        'throw',
        'try',
        'except',
        'in',
        'using',
        'namespace',
    ];

    public static function renderKeyValue($key, $value, $prefix = '    ')
    {
        return self::renderKeyOperatorValue($key, '=', $value, $prefix);
    }

    public static function renderKeyOperatorValue($key, $operator, $value, $prefix = '    ')
    {
        if ($value instanceof CustomVariableString && ! empty($value->getWhiteList())) {
            $value = $value->toConfigString(true);
        }

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
        }
        if ($value === 'n' || $value === false) {
            return 'false';
        }

        throw new InvalidArgumentException(sprintf(
            '%s is not a valid boolean',
            $value
        ));
    }

    protected static function renderInteger($value)
    {
        return (string) $value;
    }

    public static function renderFloat($value)
    {
        // Render .0000 floats as integers, mainly because of some JSON
        // implementations:
        if ((string) (int) $value === (string) $value) {
            return static::renderInteger((int) $value);
        }

        return sprintf('%F', $value);
    }

    protected static function renderNull()
    {
        return 'null';
    }

    // TODO: Find out how to allow multiline {{{...}}} strings.
    //       Parameter? Dedicated method? Always if \n is found?
    public static function renderString($string)
    {
        $special = [
            '/\\\/',
            '/"/',
            '/\$/',
            '/\t/',
            '/\r/',
            '/\n/',
            // '/\b/', -> doesn't work
            '/\f/',
        ];

        $replace = [
            '\\\\\\',
            '\\"',
            '\\$',
            '\\t',
            '\\r',
            '\\n',
            // '\\b',
            '\\f',
        ];

        $string = preg_replace($special, $replace, $string);

        return '"' . $string . '"';
    }

    public static function renderPhpValue($value)
    {
        if (is_null($value)) {
            return static::renderNull();
        }
        if (is_bool($value)) {
            return static::renderBoolean($value);
        }
        if (is_int($value)) {
            return static::renderInteger($value);
        }
        if (is_float($value)) {
            return static::renderFloat($value);
        }
        // TODO:
        // if (is_object($value) || static::isAssocArray($value)) {
        //     return static::renderHash($value, $prefix)
        // TODO: also check array
        if (is_array($value)) {
            return static::renderArray($value);
        }
        if (is_string($value)) {
            return static::renderString($value);
        }

        throw new InvalidArgumentException(sprintf(
            'Unexpected type %s',
            var_export($value, true)
        ));
    }

    public static function renderDictionaryKey($key)
    {
        if (preg_match('/^[a-z_]+[a-z0-9_]*$/i', $key)) {
            return static::escapeIfReserved($key);
        }

        return static::renderString($key);
    }

    // Requires an array
    public static function renderArray($array)
    {
        $data = [];
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
        $values = [];
        foreach ($dictionary as $key => $value) {
            $values[$key] = rtrim(
                self::renderKeyValue(
                    self::renderDictionaryKey($key),
                    $value
                )
            );
        }

        if (empty($values)) {
            return '{}';
        }
        ksort($values, SORT_STRING);

        // Prefix for toConfigString?
        return "{\n" . implode("\n", $values) . "\n}";
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
        }

        return $string;
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

        if (is_int($interval) || ctype_digit($interval)) {
            return (int) $interval;
        }

        $parts = preg_split('/\s+/', $interval, -1, PREG_SPLIT_NO_EMPTY);
        $value = 0;
        foreach ($parts as $part) {
            if (! preg_match('/^(\d+)([dhms]?)$/', $part, $m)) {
                throw new InvalidArgumentException(sprintf(
                    '"%s" is not a valid time (duration) definition',
                    $interval
                ));
            }

            $duration = (int) $m[1];

            switch ($m[2]) {
                case 'd':
                    $value += $duration * 86400;
                    break;
                case 'h':
                    $value += $duration * 3600;
                    break;
                case 'm':
                    $value += $duration * 60;
                    break;
                default:
                    $value += $duration;
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

        $steps = [
            'd' => 86400,
            'h' => 3600,
            'm' => 60,
        ];

        foreach ($steps as $unit => $duration) {
            if ($seconds % $duration === 0) {
                return (int) floor($seconds / $duration) . $unit;
            }
        }

        return $seconds . 's';
    }

    public static function stringHasMacro($string, $macroName = null)
    {
        $len = strlen($string);
        $start = false;
        // TODO: robust UTF8 support. It works, but it is not 100% correct
        for ($i = 0; $i < $len; $i++) {
            if ($string[$i] === '$') {
                if ($start === false) {
                    $start = $i;
                } else {
                    // Escaping, $$
                    if ($start + 1 === $i) {
                        $start = false;
                    } else {
                        if ($macroName === null) {
                            return true;
                        }
                        if ($macroName === substr($string, $start + 1, $i - $start - 1)) {
                            return true;
                        }

                        $start = false;
                    }
                }
            }
        }

        return false;
    }

    /**
     * Hint: this isn't complete, but let's restrict ourselves right now
     *
     * TODO: Not sure if this covers all cases.
     *
     * @param string $name
     * @param ?array $whiteList
     *
     * @return bool
     */
    public static function isValidMacroName(string $name, ?array $whiteList = null): bool
    {
        $hasMacroPattern = preg_match('/^[A-z_][A-z_.\d]+$/', $name)
            && ! preg_match('/\.$/', $name);

        if (! $hasMacroPattern && $whiteList === null) {
            return false;
        }

        if (in_array($name, $whiteList, true)) {
            return true;
        }

        foreach ($whiteList as $pattern) {
            if (str_contains($pattern, '*')) {
                if (
                    preg_match(
                        '/^' . str_replace('\*', '.*', preg_quote($pattern, '/')) . '$/',
                    $name
                    )
                ) {
                    return true;
                }
            }
        }

        return false;
    }

    public static function renderStringWithVariables($string, array $whiteList = [])
    {
        $len = strlen($string);
        $start = false;
        $parts = [];
        // TODO: UTF8...
        $offset = 0;
        for ($i = 0; $i < $len; $i++) {
            if ($string[$i] === '$') {
                if ($start === false) {
                    $start = $i;
                } else {
                    // Ignore $$
                    if ($start + 1 === $i) {
                        $start = false;
                    } else {
                        // We got a macro
                        $macroName = substr($string, $start + 1, $i - $start - 1);
                        if (static::isValidMacroName($macroName, $whiteList)) {
                            if ($start > $offset) {
                                $parts[] = static::renderString(
                                    substr($string, $offset, $start - $offset)
                                );
                            }

                            $parts[] = $macroName;
                            $offset = $i + 1;
                        }

                        $start = false;
                    }
                }
            }
        }

        if ($offset < $i) {
            $parts[] = static::renderString(substr($string, $offset, $i - $offset));
        }

        if (! empty($parts)) {
            return implode(' + ', $parts);
        }

        return '""';
    }
}

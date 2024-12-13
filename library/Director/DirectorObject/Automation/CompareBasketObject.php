<?php

namespace Icinga\Module\Director\DirectorObject\Automation;

use Icinga\Module\Director\Core\Json;
use ipl\Html\Error;
use RuntimeException;

use function array_key_exists;
use function is_array;
use function is_object;
use function is_scalar;

class CompareBasketObject
{
    public static function normalize(&$value)
    {
        if (is_scalar($value)) {
            return;
        }
        if (is_array($value)) {
            foreach ($value as $k => &$v) {
                static::normalize($v);
            }
            unset($v);
        }
        if (is_object($value)) {
            $sorted = (array) $value;
            // foreign baskets might not sort as we do:
            ksort($sorted);
            foreach ($sorted as $k => &$v) {
                static::normalize($v);
            }
            unset($v);
            $value = (object) $sorted;

            // foreign baskets might not sort those lists correctly:
            if (isset($value->list_name) && isset($value->entries)) {
                static::sortListBy('entry_name', $value->entries);
            }
            if (isset($value->fields)) {
                static::sortListBy('datafield_id', $value->fields);
            }
        }
    }

    protected static function sortListBy($key, &$list)
    {
        usort($list, function ($a, $b) use ($key) {
            if (is_array($a)) {
                return $a[$key] > $b[$key] ? -1 : 1;
            } else {
                return $a->$key > $b->$key ? -1 : 1;
            }
        });
    }

    public static function equals($a, $b)
    {
        if (is_scalar($a)) {
            return $a === $b;
        }

        if ($a === null) {
            return $b === null;
        }

        // Well... this is annoying :-/
        $a = Json::decode(Json::encode($a, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        $b = Json::decode(Json::encode($b, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        if (is_array($a)) {
            // Empty arrays VS empty objects :-( This is a fallback, not needed unless en/decode takes place
            if (empty($a) && is_object($b) && (array) $b === []) {
                return true;
            }
            if (! is_array($b)) {
                return false;
            }
            if (array_keys($a) !== array_keys($b)) {
                return false;
            }
            foreach ($a as $k => $v) {
                if (array_key_exists($k, $b) && static::equals($b[$k], $v)) {
                    continue;
                }
                return  false;
            }

            return true;
        }

        if (is_object($a)) {
            // Well... empty arrays VS empty objects :-(
            if ($b === [] && (array) $a === []) {
                return true;
            }
            if (! is_object($b)) {
                return false;
            }

            // Workaround, same as above
            if (isset($a->list_name) && isset($a->entries)) {
                if (! isset($b->entries)) {
                    return false;
                }
                static::sortListBy('entry_name', $a->entries);
                static::sortListBy('entry_name', $b->entries);
            }
            if (isset($a->fields) && isset($b->fields)) {
                static::sortListBy('datafield_id', $a->fields);
                static::sortListBy('datafield_id', $b->fields);
            }
            foreach ((array) $a as $k => $v) {
                if (property_exists($b, $k) && static::equals($v, $b->$k)) {
                    continue;
                }
                if (! property_exists($b, $k)) {
                    if ($v === null) {
                        continue;
                    }
                    // Deal with two special defaults:
                    if ($k === 'set_if_format' && $v === 'string') {
                        continue;
                    }
                    if ($k === 'disabled' && $v === false) {
                        continue;
                    }
                }
                return false;
            }
            foreach ((array) $b as $k => $v) {
                if (! property_exists($a, $k)) {
                    if ($v === null) {
                        continue;
                    }
                    // Once again:
                    if ($k === 'set_if_format' && $v === 'string') {
                        continue;
                    }
                    if ($k === 'disabled' && $v === false) {
                        continue;
                    }
                    return false;
                }
            }
            return true;
        }

        throw new RuntimeException("Cannot compare " . Error::getPhpTypeName($a));
    }
}

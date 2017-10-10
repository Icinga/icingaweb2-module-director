<?php

namespace Icinga\Module\Director\Application;

class MemoryLimit
{
    public static function raiseTo($string)
    {
        $current = static::getBytes();
        $desired = static::parsePhpIniByteString($string);
        if ($current !== -1 && $current < $desired) {
            ini_set('memory_limit', $string);
        }
    }

    public static function getBytes()
    {
        return static::parsePhpIniByteString((string) ini_get('memory_limit'));
    }

    /**
     * Return Bytes from PHP shorthand bytes notation
     *
     * http://www.php.net/manual/en/faq.using.php#faq.using.shorthandbytes
     *
     * > The available options are K (for Kilobytes), M (for Megabytes) and G
     * > (for Gigabytes), and are all case-insensitive. Anything else assumes
     * > bytes.
     *
     * @param $string
     * @return int
     */
    public static function parsePhpIniByteString($string)
    {
        $val = trim($string);
        $last = strtoupper(substr($val, -1, 1));

        switch ($last) {
            case 'G':
                $val *= 1024;
                // Intentional fall-through
            case 'M':
                $val *= 1024;
                // Intentional fall-through
            case 'K':
                $val *= 1024;
        }

        return intval($val);
    }
}

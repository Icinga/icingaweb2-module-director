<?php

namespace Icinga\Module\Director\Daemon;

class DaemonUtil
{
    /**
     * @return int
     */
    public static function timestampWithMilliseconds()
    {
        $mTime = explode(' ', microtime());

        return (int) round($mTime[0] * 1000) + (int) $mTime[1] * 1000;
    }
}

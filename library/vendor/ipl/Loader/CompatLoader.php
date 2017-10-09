<?php

namespace dipl\Loader;

use Icinga\Application\ApplicationBootstrap;

class CompatLoader
{
    public static function delegateLoadingToIcingaWeb(ApplicationBootstrap $app)
    {
        $app->getLoader()->registerNamespace(
            'dipl',
            dirname(__DIR__)
        );
    }
}

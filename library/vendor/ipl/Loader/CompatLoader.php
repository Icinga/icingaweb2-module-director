<?php

namespace ipl\Loader;

use Icinga\Application\ApplicationBootstrap;

class CompatLoader
{
    public static function delegateLoadingToIcingaWeb(ApplicationBootstrap $app)
    {
        $app->getLoader()->registerNamespace(
            'ipl',
            dirname(__DIR__)
        );
    }
}

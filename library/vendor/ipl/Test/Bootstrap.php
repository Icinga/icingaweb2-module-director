<?php

namespace ipl\Test;

use Icinga\Application\Cli;
use ipl\Loader\CompatLoader;

class Bootstrap
{
    public static function cli($basedir = null)
    {
        error_reporting(E_ALL | E_STRICT);
        if ($basedir === null) {
            $basedir = dirname(dirname(dirname(__DIR__)));
        }

        $testsDir = $basedir . '/test';
        require_once 'Icinga/Application/Cli.php';

        if (array_key_exists('ICINGAWEB_CONFIGDIR', $_SERVER)) {
            $configDir = $_SERVER['ICINGAWEB_CONFIGDIR'];
        } else {
            $configDir = $testsDir . '/compat-icingaweb2/config';
        }

        $app = Cli::start($testsDir, $configDir);

        require_once "$basedir/lib/ipl/Loader/CompatLoader.php";
        CompatLoader::delegateLoadingToIcingaWeb($app);
    }
}

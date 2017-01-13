<?php

use Icinga\Application\Cli;

## Load Composer environment, if existing
call_user_func(function () {
    $MODULE_HOME = dirname(dirname(__FILE__));
    $composer_load = $MODULE_HOME . '/vendor/autoload.php';
    if (file_exists($composer_load)) {
        require_once $composer_load;

        # include Icinga Web
        ini_set(
            'include_path',
            $MODULE_HOME . '/vendor/icinga/icingaweb2/library'
            . PATH_SEPARATOR . $MODULE_HOME . '/vendor/icinga/icingaweb2/library/vendor'
            . PATH_SEPARATOR . ini_get('include_path')
        );
    }
});

call_user_func(function () {

    error_reporting(E_ALL | E_STRICT);
    $testbase = __DIR__;
    $base = dirname($testbase);

    require_once 'Icinga/Application/Cli.php';

    if (array_key_exists('ICINGAWEB_CONFIGDIR', $_SERVER)) {
        $configDir = $_SERVER['ICINGAWEB_CONFIGDIR'];
    } else {
        $configDir = $testbase . '/config';
    }

    Cli::start($testbase, $configDir)
        ->getModuleManager()
        ->loadModule('director', $base);
});

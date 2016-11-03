<?php

use Icinga\Application\Cli;

call_user_func(function() {

    error_reporting(E_ALL | E_STRICT);
    $testbase = __DIR__;
    $base = dirname($testbase);

    require_once 'Icinga/Application/Cli.php';
    require_once $base . '/library/Director/Test/BaseTestCase.php';

    if (! file_exists($testbase . '/modules/director')) {
        symlink($base, $testbase . '/modules/director');
    }

    if (array_key_exists('ICINGAWEB_CONFIGDIR', $_SERVER)) {
        $configDir = $_SERVER['ICINGAWEB_CONFIGDIR'];
    } else {
        $configDir = $testbase . '/config';
    }

    Cli::start($testbase, $configDir)
        ->getModuleManager()
        ->loadModule('director');
});

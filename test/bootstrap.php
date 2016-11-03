<?php

use Icinga\Application\Cli;

call_user_func(function() {
    error_reporting(E_ALL | E_STRICT);
    $testbase = __DIR__;
    $base = dirname($testbase);
    require_once 'Icinga/Application/Cli.php';
    require_once $base . '/library/Director/Test/BaseTestCase.php';
    symlink($base, $testbase . '/modules/director');
    Cli::start($testbase, $testbase . '/config')
        ->getModuleManager()
        ->loadModule('director');
});

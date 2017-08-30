<?php

use Icinga\Module\Director\Test\Bootstrap;

call_user_func(function () {
    $basedir = dirname(__DIR__);
    if (! class_exists('PHPUnit_Framework_TestCase')) {
        require_once __DIR__ . '/phpunit-compat.php';
    }

    $include_path = $basedir . '/vendor' . PATH_SEPARATOR . ini_get('include_path');
    ini_set('include_path', $include_path);

    require_once $basedir . '/library/Director/Test/Bootstrap.php';
    Bootstrap::cli($basedir);
});

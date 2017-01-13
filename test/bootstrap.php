<?php

use Icinga\Module\Director\Test\Bootstrap;

call_user_func(function () {
    $basedir = dirname(__DIR__);
    require_once $basedir . '/library/Director/Test/Bootstrap.php';
    Bootstrap::cli($basedir);
});

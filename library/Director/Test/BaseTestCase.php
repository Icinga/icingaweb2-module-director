<?php

namespace Icinga\Module\Director\Test;

use Icinga\Application\Cli;
use PHPUnit_Framework_TestCase;

class BaseTestCase extends PHPUnit_Framework_TestCase
{
    private static $app;

    public function setUp()
    {
        $this->app();
    }

    protected function app()
    {
        if (self::$app === null) {
            $testModuleDir = $_SERVER['PWD'];
            $libDir = dirname(dirname($testModuleDir)) . '/library';
            require_once $libDir . '/Icinga/Application/Cli.php';
            self::$app = Cli::start();
        }

        return self::$app;
    }
}

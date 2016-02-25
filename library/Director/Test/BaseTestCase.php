<?php

namespace Icinga\Module\Director\Test;

use Icinga\Application\Cli;
use PHPUnit_Framework_TestCase;

class BaseTestCase extends PHPUnit_Framework_TestCase
{
    private $app;

    public function setUp()
    {
        $this->app();
    }

    protected function app()
    {
        if ($this->app === null) {
            $testModuleDir = $_SERVER['PWD'];
            $libDir = dirname(dirname($testModuleDir)) . '/library';
            require_once $libDir . '/Icinga/Application/Cli.php';
            $this->app = Cli::start();
        }

        return $this->app;
    }
}

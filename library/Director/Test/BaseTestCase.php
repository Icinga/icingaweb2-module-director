<?php

namespace Icinga\Module\Director\Test;

use Icinga\Application\Cli;
use Icinga\Application\Config;
use Icinga\Module\Director\Db;
use PHPUnit_Framework_TestCase;

class BaseTestCase extends PHPUnit_Framework_TestCase
{
    private static $app;

    private $db;

    public function setUp()
    {
        $this->app();
    }

    protected function getDb()
    {
        if ($this->db === null) {
            $resourceName = Config::module('director')->get('db', 'resource');
            $this->db = Db::fromResourceName($resourceName);
        }

        return $this->db;
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

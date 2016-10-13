<?php

namespace Icinga\Module\Director\Test;

use Icinga\Application\Icinga;
use Icinga\Application\Cli; // <- remove
use Icinga\Application\Config;
use Icinga\Exception\ConfigurationError;
use Icinga\Module\Director\Db;
use Icinga\Module\Director\Db\Migrations;
use Icinga\Module\Director\Objects\IcingaObject;
use PHPUnit_Framework_TestCase;

class BaseTestCase extends PHPUnit_Framework_TestCase
{
    private static $app;

    private $db;

    public function setUp()
    {
        $this->app();
    }

    protected function skipForMissingDb()
    {
        if ($this->hasDb()) {
            return false;
        }

        $this->markTestSkipped('Test db resource has not been configured');

        return true;
    }

    protected function hasDb()
    {
        return $this->getDbResourceName() !== null;
    }

    protected function getDbResourceName()
    {
        return Config::module('director')->get('testing', 'db_resource');
    }

    protected function getDb()
    {
        if ($this->db === null) {
            $resourceName = $this->getDbResourceName();
            if (! $resourceName) {
                throw new ConfigurationError(
                    'Could not run DB-based tests, please configure a testing db resource'
                );
            }
            $this->db = Db::fromResourceName($resourceName);
            $migrations = new Migrations($this->db);
            $migrations->applyPendingMigrations();
        }

        return $this->db;
    }

    protected function newObject($type, $name, $properties = array())
    {
        if (! array_key_exists('object_type', $properties)) {
            $properties['object_type'] = 'object';
        }
        $properties['object_name'] = $name;

        return IcingaObject::createByType($type, $properties);
    }

    protected function app()
    {
        if (self::$app === null) {
            // TODO: Replace this..
            $testModuleDir = $_SERVER['PWD'];
            $libDir = dirname(dirname($testModuleDir)) . '/library';
            require_once $libDir . '/Icinga/Application/Cli.php';
            self::$app = Cli::start();

            // With this:
            // self::$app = Icinga::app();
        }

        return self::$app;
    }
}

<?php

namespace Icinga\Module\Director\Test;

use Icinga\Application\Icinga;
use Icinga\Application\Config;
use Icinga\Data\ResourceFactory;
use Icinga\Exception\ConfigurationError;
use Icinga\Module\Director\Db;
use Icinga\Module\Director\Db\Migrations;
use Icinga\Module\Director\Objects\IcingaObject;
use PHPUnit_Framework_TestCase;

abstract class BaseTestCase extends PHPUnit_Framework_TestCase
{
    private static $app;

    /** @var Db */
    private static $db;

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

    protected static function getDbResourceName()
    {
        if (array_key_exists('DIRECTOR_TESTDB_RES', $_SERVER)) {
            return $_SERVER['DIRECTOR_TESTDB_RES'];
        } else {
            return Config::module('director')->get('testing', 'db_resource');
        }
    }

    /**
     * @return Db
     * @throws ConfigurationError
     */
    protected static function getDb()
    {
        if (self::$db === null) {
            $resourceName = self::getDbResourceName();
            if (! $resourceName) {
                throw new ConfigurationError(
                    'Could not run DB-based tests, please configure a testing db resource'
                );
            }
            $dbConfig = ResourceFactory::getResourceConfig($resourceName);
            if (array_key_exists('DIRECTOR_TESTDB', $_SERVER)) {
                $dbConfig->dbname = $_SERVER['DIRECTOR_TESTDB'];
            }
            if (array_key_exists('DIRECTOR_TESTDB_HOST', $_SERVER)) {
                $dbConfig->host = $_SERVER['DIRECTOR_TESTDB_HOST'];
            }
            if (array_key_exists('DIRECTOR_TESTDB_USER', $_SERVER)) {
                $dbConfig->username = $_SERVER['DIRECTOR_TESTDB_USER'];
            }
            if (array_key_exists('DIRECTOR_TESTDB_PASSWORD', $_SERVER)) {
                $dbConfig->password = $_SERVER['DIRECTOR_TESTDB_PASSWORD'];
            }
            self::$db = new Db($dbConfig);
            $migrations = new Migrations(self::$db);
            $migrations->applyPendingMigrations();
        }

        return self::$db;
    }

    protected function newObject($type, $name, $properties = array())
    {
        if (! array_key_exists('object_type', $properties)) {
            $properties['object_type'] = 'object';
        }
        $properties['object_name'] = $name;

        return IcingaObject::createByType($type, $properties, $this->getDb());
    }

    protected function app()
    {
        if (self::$app === null) {
            self::$app = Icinga::app();
        }

        return self::$app;
    }
}

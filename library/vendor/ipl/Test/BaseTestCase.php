<?php

namespace ipl\Test;

use ipl\Loader\CompatLoader;
use Icinga\Application\Icinga;
use PHPUnit_Framework_TestCase;
use ReflectionClass;

abstract class BaseTestCase extends PHPUnit_Framework_TestCase
{
    private static $app;

    public function setUp()
    {
        // $this->setupCompatLoader();
    }

    /**
     * @param $obj
     * @param $name
     * @return \ReflectionMethod
     */
    public function getProtectedMethod($obj, $name)
    {
        $class = new ReflectionClass($obj);
        $method = $class->getMethod($name);
        $method->setAccessible(true);
        return $method;
    }

    /**
     * @param $obj
     * @param $name
     * @return \ReflectionMethod
     */
    public function getPrivateMethod($obj, $name)
    {
        return $this->getProtectedMethod($obj, $name);
    }

    protected function setupCompatLoader()
    {
        require_once dirname(__DIR__) . '/Loader/CompatLoader.php';
        CompatLoader::delegateLoadingToIcingaWeb($this->app());
    }

    protected function app()
    {
        if (self::$app === null) {
            self::$app = Icinga::app();
        }

        return self::$app;
    }
}

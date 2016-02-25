<?php

use Icinga\Module\Director\Objects\IcingaTimePeriodRange;
use Icinga\Module\Director\Objects\IcingaTimePeriodRanges;
use Icinga\Module\Director\Objects\IcingaTimePeriod;
use Icinga\Test\BaseTestCase;
use Icinga\Application\Config;
use Icinga\Module\Director\Db;

Icinga\Application\Config::$configDir = '/etc/icingaweb2';

/**
 * $ icingacli test php unit /usr/local/icingaweb-modules/director
 */
class TimePeriodRangesTest extends BaseTestCase
{
    public function testFoo()
    {
        $this->assertEquals('foo', 'foo');
    }

    public function getDb()
    {
        $resourceName = Config::module('director')->get('db', 'resource');
        $db = Db::fromResourceName($resourceName);

        return $db;
    }

    public function prepare()
    {
        $db = $this->getDb();
        $object = IcingaTimePeriod::load(1, $db);
        $ranges = $object->ranges();

        $newRanges = array(
            'monday'    => '00:00-24:00',
            'tuesday'   => '00:00-24:00',
            'wednesday' => '00:00-24:00',
        );

        $ranges->set($newRanges);
        $ranges->store();

        return $ranges;
    }

    public function reload()
    {
        $db = $this->getDb();
        $object = IcingaTimePeriod::load(1, $db);
        $ranges = $object->ranges();

        return $ranges;
    }

    public function testUpdate()
    {
        $ranges = $this->prepare();

        $newRanges = array(
            'monday'    => '00:00-24:00',
            'tuesday'   => '18:00-24:00',
            'wednesday' => '00:00-24:00',
        );

        $ranges->set($newRanges);
        $ranges->store();

        $reloaded = $this->reload();
        $newValue = $reloaded->get('tuesday')->timeperiod_value;

        $this->assertEquals('18:00-24:00', $newValue);
    }
}

<?php

namespace Tests\Icinga\Modules\Director\Objects;

use Icinga\Module\Director\Objects\IcingaTimePeriodRange;
use Icinga\Module\Director\Objects\IcingaTimePeriodRanges;
use Icinga\Module\Director\Objects\IcingaTimePeriod;
use Icinga\Module\Director\Test\BaseTestCase;

class IcingaTimePeriodRangesTest extends BaseTestCase
{
    protected $testPeriodName = '___TEST___timerange';

    public function testWhetherUpdatedTimeperiodRangesAreCorrectlyStored()
    {
        $period = $this->createTestPeriod();

        $newRanges = array(
            'monday'    => '00:00-24:00',
            'tuesday'   => '18:00-24:00',
            'wednesday' => '00:00-24:00',
        );
        $period->ranges()->set($newRanges)->store();

        $period = $this->loadTestPeriod();
        $this->assertEquals(
            '18:00-24:00',
            $period->ranges()->get('tuesday')->timeperiod_value
        );
    }

    protected function createTestPeriod()
    {
        $db = $this->getDb();
        $object = IcingaTimePeriod::create(
            array(
                'object_name' => $this->testPeriodName,
                'object_type' => 'object'
            ),
            $db
        );
        $object->store();
        $ranges = $object->ranges();

        $testRanges = array(
            'monday'    => '00:00-24:00',
            'tuesday'   => '00:00-24:00',
            'wednesday' => '00:00-24:00',
        );

        $ranges->set($testRanges);
        $ranges->store();

        return $object;
    }

    protected function loadTestPeriod()
    {
        return IcingaTimePeriod::load($this->testPeriodName, $this->getDb());
    }

    public function tearDown()
    {
        $db = $this->getDb();
        if (IcingaTimePeriod::exists($this->testPeriodName, $db)) {
            IcingaTimePeriod::load($this->testPeriodName, $db)->delete();
        }
    }
}

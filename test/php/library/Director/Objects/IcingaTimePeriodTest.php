<?php

namespace Tests\Icinga\Module\Director\Objects;

use Icinga\Module\Director\Objects\IcingaTimePeriod;
use Icinga\Module\Director\Test\BaseTestCase;

class IcingaTimePeriodTest extends BaseTestCase
{
    protected $testPeriodName = '___TEST___timeperiod';

    public function testWhetherUpdatedTimeperiodsAreCorrectlyStored()
    {
        if ($this->skipForMissingDb()) {
            return;
        }

        $period = $this->createTestPeriod();

        $newRanges = array(
            'monday'    => '00:00-24:00',
            'tuesday'   => '18:00-24:00',
            'wednesday' => '00:00-24:00',
        );
        $period->ranges = $newRanges;
        $this->assertEquals(
            '18:00-24:00',
            $period->toPlainObject()->ranges->tuesday
        );

        $period->store();

        $period = $this->loadTestPeriod();
        $this->assertEquals(
            '18:00-24:00',
            $period->ranges()->get('tuesday')->range_value
        );

        $this->assertEquals(
            '00:00-24:00',
            $period->toPlainObject()->ranges->wednesday
        );

        $period->ranges()->setRange('wednesday', '09:00-17:00');

        $this->assertEquals(
            '09:00-17:00',
            $period->toPlainObject()->ranges->wednesday
        );

        $this->assertEquals(
            '00:00-24:00',
            $period->getPlainUnmodifiedObject()->ranges->wednesday
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
        $object->store();

        return $object;
    }

    public function testIsActiveWorksForWeekdayRanges()
    {
        $period = $this->createTestPeriod();

        $newRanges = array(
            'monday'    => '00:00-24:00',
            'tuesday'   => '18:00-24:00',
            'wednesday' => '00:00-24:00',
        );
        $period->ranges = $newRanges;

        // Tuesday:
        $this->assertFalse($period->isActive(strtotime('2016-05-17 10:00:00')));
        // Wednesday:
        $this->assertTrue($period->isActive(strtotime('2016-05-18 10:00:00')));
        // Thursday:
        $this->assertFalse($period->isActive(strtotime('2016-05-19 10:00:00')));
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

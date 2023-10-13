<?php

namespace Tests\Icinga\Module\Director\Objects;

use Icinga\Module\Director\Objects\IcingaTimePeriod;
use Icinga\Module\Director\Test\BaseTestCase;

class IcingaTimePeriodTest extends BaseTestCase
{
    protected $testPeriodName = '___TEST___timeperiod';

    protected $createdNames = [];

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

    protected function createTestPeriod($suffix = '', $testRanges = [])
    {
        $db = $this->getDb();
        $name = $this->testPeriodName . $suffix;

        $this->createdNames[] = $name;
        $object = IcingaTimePeriod::create(
            array(
                'object_name' => $name,
                'object_type' => 'object'
            ),
            $db
        );
        $object->store();
        $ranges = $object->ranges();

        if (empty($testRanges)) {
            $testRanges = array(
                'monday'    => '00:00-24:00',
                'tuesday'   => '00:00-24:00',
                'wednesday' => '00:00-24:00',
            );
        }

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

    public function testPeriodWithIncludes()
    {
        $period = $this->createTestPeriod();
        $include = $this->createTestPeriod('include', ['thursday' => '00:00-24:00']);

        $period->set('includes', $include->object_name);
        $period->store();

        // Wednesday:
        $this->assertTrue($period->isActive(strtotime('2016-05-18 10:00:00')));
        // Thursday:
        $this->assertTrue($period->isActive(strtotime('2016-05-19 10:00:00')));
    }

    public function testPeriodWithExcludes()
    {
        $period = $this->createTestPeriod();
        $exclude = $this->createTestPeriod('exclude', ['wednesday' => '00:00-24:00']);

        $period->set('excludes', $exclude->object_name);
        $period->store();

        // Wednesday:
        $this->assertFalse($period->isActive(strtotime('2016-05-18 10:00:00')));
        // Thursday:
        $this->assertFalse($period->isActive(strtotime('2016-05-19 10:00:00')));
    }

    public function testPeriodPreferingIncludes()
    {
        $period = $this->createTestPeriod();
        $include = $this->createTestPeriod('include', ['thursday' => '00:00-24:00']);
        $exclude = $this->createTestPeriod('exclude', ['thursday' => '00:00-24:00']);

        $period->set('includes', $include->object_name);
        $period->set('excludes', $exclude->object_name);
        $period->store();

        // Wednesday:
        $this->assertTrue($period->isActive(strtotime('2016-05-18 10:00:00')));
        // Thursday:
        $this->assertTrue($period->isActive(strtotime('2016-05-19 10:00:00')));
    }

    public function testPeriodPreferingExcludes()
    {
        $period = $this->createTestPeriod();
        $include = $this->createTestPeriod('include', ['thursday' => '00:00-24:00']);
        $exclude = $this->createTestPeriod('exclude', ['thursday' => '00:00-24:00']);

        $period->set('prefer_includes', false);
        $period->set('includes', $include->object_name);
        $period->set('excludes', $exclude->object_name);
        $period->store();

        // Wednesday:
        $this->assertTrue($period->isActive(strtotime('2016-05-18 10:00:00')));
        // Thursday:
        $this->assertFalse($period->isActive(strtotime('2016-05-19 10:00:00')));
    }

    protected function loadTestPeriod($suffix = '')
    {
        return IcingaTimePeriod::load($this->testPeriodName . $suffix, $this->getDb());
    }

    public function tearDown(): void
    {
        $db = $this->getDb();

        foreach ($this->createdNames as $name) {
            if (IcingaTimePeriod::exists($name, $db)) {
                IcingaTimePeriod::load($name, $db)->delete();
            }
        }
    }
}

<?php

namespace Tests\Icinga\Module\Director\IcingaConfig;

use Icinga\Module\Director\Import\SyncUtils;
use Icinga\Module\Director\Test\BaseTestCase;

class SyncUtilsTest extends BaseTestCase
{
    public function testVariableNamesAreExtracted()
    {
        $this->assertEquals(
            array(
                'var.name',
                '$Special Var'
            ),
            SyncUtils::extractVariableNames('This ${var.name} is ${$Special Var} are vars')
        );

        $this->assertEquals(
            array(),
            SyncUtils::extractVariableNames('No ${var.name vars ${$Special Var here')
        );
    }

    public function testSpecificValuesCanBeRetrievedByName()
    {
        $row = (object)array(
            'host' => 'localhost',
            'ipaddress' => '127.0.0.1'
        );

        $this->assertEquals(
            '127.0.0.1',
            SyncUtils::getSpecificValue($row, 'ipaddress')
        );
    }

    public function testMissingPropertiesMustBeNull()
    {
        $row = (object)array(
            'host' => 'localhost',
            'ipaddress' => '127.0.0.1'
        );

        $this->assertNull(
            SyncUtils::getSpecificValue($row, 'address')
        );
    }

    public function testNestedValuesCanBeRetrievedByPath()
    {
        $row = $this->getSampleRow();

        $this->assertEquals(
            '192.0.2.10',
            SyncUtils::getSpecificValue($row, 'addresses.entries.eth0:1')
        );

        $this->assertEquals(
            2,
            SyncUtils::getSpecificValue($row, 'addresses.count')
        );
    }

    public function testRootVariablesCanBeExtracted()
    {
        $vars = array('test', 'nested.test', 'nested.dee.per');
        $this->assertEquals(
            array(
                'test' => 'test',
                'nested' => 'nested'
            ),
            SyncUtils::getRootVariables($vars)
        );
    }

    public function testMultipleVariablesAreBeingReplacedCorrectly()
    {
        $string = '${addresses.entries.lo} and ${addresses.entries.eth0:1} are'
            . ' ${This one?.$höüld be}${addressesmissing}';

        $this->assertEquals(
            '127.0.0.1 and 192.0.2.10 are fine',
            SyncUtils::fillVariables(
                $string,
                $this->getSampleRow()
            )
        );
    }

    protected function getSampleRow()
    {
        return (object) array(
            'host'      => 'localhost',
            'addresses' => (object) array(
                'count' => 2,
                'entries' => (object) array(
                    'lo'     => '127.0.0.1',
                    'eth0:1' => '192.0.2.10',
                )
            ),
            'This one?' => (object) array(
                '$höüld be' => 'fine'
            )
        );
    }
}

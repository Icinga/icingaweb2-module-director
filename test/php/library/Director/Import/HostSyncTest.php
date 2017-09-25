<?php

namespace Tests\Icinga\Module\Director\IcingaConfig;

use Icinga\Module\Director\Objects\IcingaObject;
use Icinga\Module\Director\Test\SyncTest;

class HostSyncTest extends SyncTest
{
    protected $objectType = 'host';

    protected $keyColumn = 'host';

    public function testSimpleSync()
    {
        $this->runImport(array(
            array(
                'host'    => 'SYNCTEST_simple',
                'address' => '127.0.0.1',
                'os'      => 'Linux'
            )
        ));

        $this->setUpProperty(array(
            'source_expression' => '${host}',
            'destination_field' => 'object_name',
            'priority'          => 10,
        ));
        $this->setUpProperty(array(
            'source_expression' => '${address}',
            'destination_field' => 'address',
            'priority'          => 11,
        ));
        $this->setUpProperty(array(
            'source_expression' => '${os}',
            'destination_field' => 'vars.os',
            'priority'          => 12,
        ));

        $this->assertTrue($this->sync->hasModifications(), 'Should have modifications pending');
        $this->assertGreaterThan(0, $this->sync->apply(), 'Should successfully apply at least 1 update');
        $this->assertFalse($this->sync->hasModifications(), 'Should not have modifications pending after sync');
    }

    public function testSyncWithoutData()
    {
        $this->runImport(array());

        $this->setUpProperty(array(
            'source_expression' => '${host}',
            'destination_field' => 'object_name',
            'priority'          => 10,
        ));

        $this->assertFalse($this->sync->hasModifications(), 'Should not have modifications pending');
    }

    public function testSyncFilteredData()
    {
        $this->runImport(array(
            array(
                'host'    => 'SYNCTEST_filtered_in',
                'address' => '127.0.0.1',
                'os'      => 'Linux',
                'sync'    => 'yes'
            ),
            array(
                'host'    => 'SYNCTEST_filtered_out',
                'address' => '127.0.0.1',
                'os'      => null,
                'sync'    => 'no'
            ),
            array(
                'host'      => 'SYNCTEST_filtered_in_unusedfield',
                'address'   => '127.0.0.1',
                'os'        => null,
                'sync'      => 'no',
                'othersync' => '1'
            ),
            array(
                'host'      => 'SYNCTEST_filtered_in_unusedfield_propfilter',
                'address'   => '127.0.0.1',
                'os'        => null,
                'magic'     => '2',
                'sync'      => 'no',
                'othersync' => '1'
            )
        ));

        $this->rule->set('filter_expression', 'sync=yes|othersync=1');
        $this->rule->store();

        $this->setUpProperty(array(
            'source_expression' => '${host}',
            'destination_field' => 'object_name',
            'priority'          => 10,
        ));
        $this->setUpProperty(array(
            'source_expression' => '${address}',
            'destination_field' => 'address',
            'priority'          => 11,
        ));
        $this->setUpProperty(array(
            'source_expression' => 'test',
            'destination_field' => 'vars.magic',
            'filter_expression' => 'magic!=',
            'priority'          => 12,
        ));

        $modifications = array();
        /** @var IcingaObject $mod */
        foreach ($this->sync->getExpectedModifications() as $mod) {
            $name = $mod->object_name;
            $modifications[$name] = $mod;

            $this->assertEquals(
                '127.0.0.1',
                $mod->get('address'),
                $name . ': address should not be synced'
            );
            $this->assertNull($mod->get('vars.os'), $name . ': vars.os should not be synced');

            if ($name === 'SYNCTEST_filtered_in_unusedfield_propfilter') {
                $this->assertEquals(
                    'test',
                    $mod->get('vars.magic'),
                    $name . ': vars.magic should not be synced'
                );
            } else {
                $this->assertNull($mod->get('vars.magic'), $name . ': vars.magic should not be synced');
            }
        }

        $this->assertArrayHasKey(
            'SYNCTEST_filtered_in',
            $modifications,
            'SYNCTEST_filtered_in should be modified'
        );
        $this->assertArrayNotHasKey(
            'SYNCTEST_filtered_out',
            $modifications,
            'SYNCTEST_filtered_out should be synced'
        );
        $this->assertArrayHasKey(
            'SYNCTEST_filtered_in_unusedfield',
            $modifications,
            'SYNCTEST_filtered_in_unusedfield should be modified'
        );
        $this->assertArrayHasKey(
            'SYNCTEST_filtered_in_unusedfield_propfilter',
            $modifications,
            'SYNCTEST_filtered_in_unusedfield_propfilter should be modified'
        );
    }
}

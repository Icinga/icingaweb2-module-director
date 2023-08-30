<?php

namespace Tests\Icinga\Module\Director\Objects;

use Icinga\Module\Director\Data\PropertiesFilter\ArrayCustomVariablesFilter;
use Icinga\Module\Director\Data\PropertiesFilter\CustomVariablesFilter;
use Icinga\Module\Director\IcingaConfig\IcingaConfig;
use Icinga\Module\Director\Objects\DirectorDatafield;
use Icinga\Module\Director\Objects\IcingaHost;
use Icinga\Module\Director\Objects\IcingaHostGroup;
use Icinga\Module\Director\Objects\IcingaZone;
use Icinga\Module\Director\Test\BaseTestCase;
use Icinga\Exception\IcingaException;

class IcingaHostTest extends BaseTestCase
{
    protected $testHostName = '___TEST___host';
    protected $testDatafieldName = 'test5';

    public function testPropertiesCanBeSet()
    {
        $host = $this->host();
        $host->display_name = 'Something else';
        $this->assertEquals(
            $host->display_name,
            'Something else'
        );
    }

    public function testCanBeReplaced()
    {
        $host = $this->host();
        $newHost = IcingaHost::create(
            array('display_name' => 'Replaced display'),
            $this->getDb()
        );

        $this->assertEquals(
            count($host->vars()),
            4
        );
        $this->assertEquals(
            $host->address,
            '127.0.0.127'
        );

        $host->replaceWith($newHost);
        $this->assertEquals(
            $host->display_name,
            'Replaced display'
        );
        $this->assertEquals(
            $host->address,
            null
        );

        $this->assertEquals(
            count($host->vars()),
            0
        );
    }

    public function testCanBeMerged()
    {
        $host = $this->host();
        $newHost = IcingaHost::create(
            array('display_name' => 'Replaced display'),
            $this->getDb()
        );

        $this->assertEquals(
            count($host->vars()),
            4
        );
        $this->assertEquals(
            $host->address,
            '127.0.0.127'
        );

        $host->merge($newHost);
        $this->assertEquals(
            $host->display_name,
            'Replaced display'
        );
        $this->assertEquals(
            $host->address,
            '127.0.0.127'
        );
        $this->assertEquals(
            count($host->vars()),
            4
        );
    }

    public function testPropertiesCanBePreservedWhenBeingReplaced()
    {
        if ($this->skipForMissingDb()) {
            return;
        }

        $db = $this->getDb();
        $this->host()->store($db);
        $host = IcingaHost::load($this->testHostName, $db);

        $newHost = IcingaHost::create(
            array(
                'display_name'  => 'Replaced display',
                'address'       => '1.2.2.3',
                'vars'          => array(
                    'test1'     => 'newstring',
                    'test2'     => 18,
                    'initially' => 'set and then preserved',
                )
            ),
            $this->getDb()
        );

        $preserve = array('address', 'vars.test1', 'vars.initially');
        $host->replaceWith($newHost, $preserve);
        $this->assertEquals(
            $host->address,
            '127.0.0.127'
        );

        $this->assertEquals(
            $host->{'vars.test2'},
            18
        );

        $this->assertEquals(
            $host->vars()->test2->getValue(),
            18
        );

        $this->assertEquals(
            $host->{'vars.initially'},
            'set and then preserved'
        );

        $this->assertFalse(
            array_key_exists('address', $host->getModifiedProperties()),
            'Preserved property stays unmodified'
        );

        $newHost->set('vars.initially', 'changed later on');
        $newHost->set('vars.test2', 19);

        $host->replaceWith($newHost, $preserve);
        $this->assertEquals(
            $host->{'vars.initially'},
            'set and then preserved'
        );

        $this->assertEquals(
            $host->get('vars.test2'),
            19
        );


        $host->delete();
    }

    public function testDistinctCustomVarsCanBeSetWithoutSideEffects()
    {
        $host = $this->host();
        $host->set('vars.test2', 18);
        $this->assertEquals(
            $host->vars()->test1->getValue(),
            'string'
        );
        $this->assertEquals(
            $host->vars()->test2->getValue(),
            18
        );
        $this->assertEquals(
            $host->vars()->test3->getValue(),
            false
        );
    }

    public function testVarsArePersisted()
    {
        if ($this->skipForMissingDb()) {
            return;
        }

        $db = $this->getDb();
        $this->host()->store($db);
        $host = IcingaHost::load($this->testHostName, $db);
        $this->assertEquals(
            $host->vars()->test1->getValue(),
            'string'
        );
        $this->assertEquals(
            $host->vars()->test2->getValue(),
            17
        );
        $this->assertEquals(
            $host->vars()->test3->getValue(),
            false
        );
        $this->assertEquals(
            $host->vars()->test4->getValue(),
            (object) array(
                'this' => 'is',
                'a' => array(
                    'dict',
                    'ionary'
                )
            )
        );
    }

    public function testRendersCorrectly()
    {
        $this->assertEquals(
            (string) $this->host(),
            $this->loadRendered('host1')
        );
    }

    public function testGivesPlainObjectWithInvalidUnresolvedDependencies()
    {
        $props = $this->getDummyRelatedProperties();

        $host = $this->host();
        foreach ($props as $k => $v) {
            $host->$k = $v;
        }

        $plain = $host->toPlainObject();
        foreach ($props as $k => $v) {
            $this->assertEquals($plain->$k, $v);
        }
    }

    public function testCorrectlyStoresLazyRelations()
    {
        if ($this->skipForMissingDb()) {
            return;
        }
        $db = $this->getDb();
        $host = $this->host();
        $host->zone = '___TEST___zone';
        $this->assertEquals(
            '___TEST___zone',
            $host->zone
        );

        $zone = $this->newObject('zone', '___TEST___zone');
        $zone->store($db);

        $host->store($db);
        $host->delete();
        $zone->delete();
    }

    /**
     * @expectedException \RuntimeException
     */
    public function testFailsToStoreWithMissingLazyRelations()
    {
        if ($this->skipForMissingDb()) {
            return;
        }
        $db = $this->getDb();
        $host = $this->host();
        $host->zone = '___TEST___zone';
        $host->store($db);
    }

    public function testHandlesUnmodifiedProperties()
    {
        $this->markTestSkipped('Currently broken, needs to be fixed');

        if ($this->skipForMissingDb()) {
            return;
        }

        $db = $this->getDb();
        $host = $this->host();
        $host->store($db);

        $parent = $this->newObject('host', '___TEST___parent');
        $parent->store($db);
        $host->imports = '___TEST___parent';

        $host->store($db);

        $plain = $host->getPlainUnmodifiedObject();
        $this->assertEquals(
            'string',
            $plain->vars->test1
        );
        $host->vars()->set('test1', 'nada');

        $host->store();

        $plain = $host->getPlainUnmodifiedObject();
        $this->assertEquals(
            'nada',
            $plain->vars->test1
        );

        $host->vars()->set('test1', 'string');
        $plain = $host->getPlainUnmodifiedObject();
        $this->assertEquals(
            'nada',
            $plain->vars->test1
        );

        $plain = $host->getPlainUnmodifiedObject();
        $test = IcingaHost::create((array) $plain);

        $this->assertEquals(
            $this->loadRendered('host3'),
            (string) $test
        );

        $host->delete();
        $parent->delete();
    }

    public function testRendersWithInvalidUnresolvedDependencies()
    {
        $newHost = $this->host();
        $newHost->zone             = 'invalid';
        $newHost->check_command    = 'unknown';
        $newHost->event_command    = 'What event?';
        $newHost->check_period     = 'Not time is a good time @ nite';
        $newHost->command_endpoint = 'nirvana';

        $this->assertEquals(
            (string) $newHost,
            $this->loadRendered('host2')
        );
    }

    /**
     * @expectedException \RuntimeException
     */
    public function testFailsToStoreWithInvalidUnresolvedDependencies()
    {
        if ($this->skipForMissingDb()) {
            return;
        }

        $host = $this->host();
        $host->zone = 'invalid';
        $host->store($this->getDb());
    }

    public function testRendersToTheCorrectZone()
    {
        if ($this->skipForMissingDb()) {
            return;
        }

        $db = $this->getDb();
        $host = $this->host()->setConnection($db);
        $masterzone = $db->getMasterZoneName();

        $config = new IcingaConfig($db);
        $host->renderToConfig($config);
        $this->assertEquals(
            array('zones.d/' . $masterzone . '/hosts.conf'),
            $config->getFileNames()
        );

        $zone = $this->newObject('zone', '___TEST___zone');
        $zone->store($db);

        $config = new IcingaConfig($db);
        $host->zone = '___TEST___zone';
        $host->renderToConfig($config);
        $this->assertEquals(
            array('zones.d/___TEST___zone/hosts.conf'),
            $config->getFileNames()
        );

        $host->has_agent = true;
        $host->master_should_connect = true;
        $host->accept_config = true;

        $config = new IcingaConfig($db);
        $host->renderToConfig($config);
        $this->assertEquals(
            array(
                'zones.d/___TEST___zone/hosts.conf',
                'zones.d/___TEST___zone/agent_endpoints.conf',
                'zones.d/___TEST___zone/agent_zones.conf'
            ),
            $config->getFileNames()
        );

        $host->object_type = 'template';
        $host->zone_id = null;

        $config = new IcingaConfig($db);
        $host->renderToConfig($config);
        $this->assertEquals(
            array('zones.d/director-global/host_templates.conf'),
            $config->getFileNames()
        );
    }

    public function testWhetherTwoHostsCannotBeStoredWithTheSameApiKey()
    {
        if ($this->skipForMissingDb()) {
            return;
        }

        $db = $this->getDb();
        $a = IcingaHost::create(array(
            'object_name' => '___TEST___a',
            'object_type' => 'object',
            'api_key' => 'a'
        ), $db);
        $b = IcingaHost::create(array(
            'object_name' => '___TEST___b',
            'object_type' => 'object',
            'api_key' => 'a'
        ), $db);

        $a->store();
        try {
            $b->store();
        } catch (\RuntimeException $e) {
            $msg = $e->getMessage();
            $matchMysql = strpos(
                $msg,
                "Duplicate entry 'a' for key 'api_key'"
            ) !== false;

            $matchPostgres = strpos(
                $msg,
                'Unique violation'
            ) !== false;

            $this->assertTrue(
                $matchMysql || $matchPostgres,
                'Exception message does not tell about unique constraint violation'
            );
            $a->delete();
        }
    }

    public function testWhetherHostCanBeLoadedWithValidApiKey()
    {
        if ($this->skipForMissingDb()) {
            return;
        }

        $db = $this->getDb();
        $a = IcingaHost::create(array(
            'object_name' => '___TEST___a',
            'object_type' => 'object',
            'api_key' => 'a1a1a1'
        ), $db);
        $b = IcingaHost::create(array(
            'object_name' => '___TEST___b',
            'object_type' => 'object',
            'api_key' => 'b1b1b1'
        ), $db);
        $a->store();
        $b->store();

        $this->assertEquals(
            IcingaHost::loadWithApiKey('b1b1b1', $db)->object_name,
            '___TEST___b'
        );

        $a->delete();
        $b->delete();
    }

    /**
     * @expectedException \Icinga\Exception\NotFoundError
     */
    public function testWhetherInvalidApiKeyThrows404()
    {
        if ($this->skipForMissingDb()) {
            return;
        }

        $db = $this->getDb();
        IcingaHost::loadWithApiKey('No___such___key', $db);
    }

    public function testEnumProperties()
    {
        if ($this->skipForMissingDb()) {
            return;
        }

        $db = $this->getDb();
        $properties = IcingaHost::enumProperties($db);

        $this->assertEquals(
            array(
                'Host properties' => $this->getDefaultHostProperties()
            ),
            $properties
        );
    }

    public function testEnumPropertiesWithCustomVars()
    {
        if ($this->skipForMissingDb()) {
            return;
        }

        $db = $this->getDb();

        $host = $this->host();
        $host->store($db);

        $properties = IcingaHost::enumProperties($db);
        $this->assertEquals(
            array(
                'Host properties' => $this->getDefaultHostProperties(),
                'Custom variables' => array(
                    'vars.test1' => 'test1',
                    'vars.test2' => 'test2',
                    'vars.test3' => 'test3',
                    'vars.test4' => 'test4'
                )
            ),
            $properties
        );
    }

    public function testEnumPropertiesWithPrefix()
    {
        if ($this->skipForMissingDb()) {
            return;
        }

        $db = $this->getDb();

        $host = $this->host();
        $host->store($db);

        $properties = IcingaHost::enumProperties($db, 'host.');
        $this->assertEquals(
            array(
                'Host properties' => $this->getDefaultHostProperties('host.'),
                'Custom variables' => array(
                    'host.vars.test1' => 'test1',
                    'host.vars.test2' => 'test2',
                    'host.vars.test3' => 'test3',
                    'host.vars.test4' => 'test4'
                )
            ),
            $properties
        );
    }

    public function testEnumPropertiesWithFilter()
    {
        if ($this->skipForMissingDb()) {
            return;
        }

        $db = $this->getDb();

        DirectorDatafield::create(array(
            'varname'       => $this->testDatafieldName,
            'caption'       => 'Blah',
            'description'   => '',
            'datatype'      => 'Icinga\Module\Director\DataType\DataTypeArray',
            'format'        => 'json'
        ))->store($db);

        $host = $this->host();
        $host->{'vars.test5'} = array('a', '1');
        $host->store($db);

        $properties = IcingaHost::enumProperties($db, '', new CustomVariablesFilter());
        $this->assertEquals(
            array(
                'Custom variables' => array(
                    'vars.test1' => 'test1',
                    'vars.test2' => 'test2',
                    'vars.test3' => 'test3',
                    'vars.test4' => 'test4',
                    'vars.test5' => 'test5 (Blah)'
                )
            ),
            $properties
        );
    }

    public function testEnumPropertiesWithArrayFilter()
    {
        if ($this->skipForMissingDb()) {
            return;
        }

        $db = $this->getDb();

        DirectorDatafield::create(array(
            'varname'       => $this->testDatafieldName,
            'caption'       => 'Blah',
            'description'   => '',
            'datatype'      => 'Icinga\Module\Director\DataType\DataTypeArray',
            'format'        => 'json'
        ))->store($db);

        $host = $this->host();
        $host->{'vars.test5'} = array('a', '1');
        $host->store($db);

        $properties = IcingaHost::enumProperties($db, '', new ArrayCustomVariablesFilter());
        $this->assertEquals(
            array(
                'Custom variables' => array(
                    'vars.test5' => 'test5 (Blah)'
                )
            ),
            $properties
        );
    }

    public function testMergingObjectKeepsGroupsIfNotGiven()
    {
        $one = IcingaHostGroup::create([
            'object_name' => 'one',
            'object_type' => 'object',
        ]);
        $two = IcingaHostGroup::create([
            'object_name' => 'two',
            'object_type' => 'object',
        ]);
        $a = IcingaHost::create([
            'object_name' => 'one',
            'object_type' => 'object',
            'imports'     => [],
            'address'     => '127.0.0.2',
            'groups'      => [$one, $two]
        ]);

        $b = IcingaHost::create([
            'object_name' => 'one',
            'object_type' => 'object',
            'imports'     => [],
            'address'     => '127.0.0.42',
        ]);

        $a->merge($b);
        $this->assertEquals(
            '127.0.0.42',
            $a->get('address')
        );
        $this->assertEquals(
            ['one', 'two'],
            $a->getGroups()
        );
    }

    protected function getDummyRelatedProperties()
    {
        return array(
            'zone'             => 'invalid',
            'check_command'    => 'unknown',
            'event_command'    => 'What event?',
            'check_period'     => 'Not time is a good time @ nite',
            'command_endpoint' => 'nirvana',
        );
    }

    protected function host()
    {
        return IcingaHost::create(array(
            'object_name'  => $this->testHostName,
            'object_type'  => 'object',
            'address'      => '127.0.0.127',
            'display_name' => 'Whatever',
            'vars'         => array(
                'test1' => 'string',
                'test2' => 17,
                'test3' => false,
                'test4' => (object) array(
                    'this' => 'is',
                    'a' => array(
                        'dict',
                        'ionary'
                    )
                )
            )
        ), $this->getDb());
    }

    protected function getDefaultHostProperties($prefix = '')
    {
        return array(
            "{$prefix}name" => "name",
            "{$prefix}action_url" => "action_url",
            "{$prefix}address" => "address",
            "{$prefix}address6" => "address6",
            "{$prefix}api_key" => "api_key",
            "{$prefix}check_command" => "check_command",
            "{$prefix}check_interval" => "check_interval",
            "{$prefix}check_period" => "check_period",
            "{$prefix}check_timeout" => "check_timeout",
            "{$prefix}command_endpoint" => "command_endpoint",
            "{$prefix}display_name" => "display_name",
            "{$prefix}enable_active_checks" => "enable_active_checks",
            "{$prefix}enable_event_handler" => "enable_event_handler",
            "{$prefix}enable_flapping" => "enable_flapping",
            "{$prefix}enable_notifications" => "enable_notifications",
            "{$prefix}enable_passive_checks" => "enable_passive_checks",
            "{$prefix}enable_perfdata" => "enable_perfdata",
            "{$prefix}event_command" => "event_command",
            "{$prefix}flapping_threshold_high" => "flapping_threshold_high",
            "{$prefix}flapping_threshold_low" => "flapping_threshold_low",
            "{$prefix}icon_image" => "icon_image",
            "{$prefix}icon_image_alt" => "icon_image_alt",
            "{$prefix}max_check_attempts" => "max_check_attempts",
            "{$prefix}notes" => "notes",
            "{$prefix}notes_url" => "notes_url",
            "{$prefix}retry_interval" => "retry_interval",
            "{$prefix}volatile" => "volatile",
            "{$prefix}zone" => "zone",
            "{$prefix}groups" => "Groups",
            "{$prefix}templates" => "templates"
        );
    }
    protected function loadRendered($name)
    {
        return file_get_contents(__DIR__ . '/rendered/' . $name . '.out');
    }

    public function tearDown(): void
    {
        if ($this->hasDb()) {
            $db = $this->getDb();
            $kill = array($this->testHostName, '___TEST___parent', '___TEST___a', '___TEST___b');
            foreach ($kill as $name) {
                if (IcingaHost::exists($name, $db)) {
                    IcingaHost::load($name, $db)->delete();
                }
            }

            $kill = array('___TEST___zone');
            foreach ($kill as $name) {
                if (IcingaZone::exists($name, $db)) {
                    IcingaZone::load($name, $db)->delete();
                }
            }

            $this->deleteDatafields();
        }
    }

    protected function deleteDatafields()
    {
        $db = $this->getDb();
        $dbAdapter = $db->getDbAdapter();
        $kill = array($this->testDatafieldName);

        foreach ($kill as $name) {
            $query = $dbAdapter->select()
                ->from('director_datafield')
                ->where('varname = ?', $name);
            foreach (DirectorDatafield::loadAll($db, $query, 'id') as $datafield) {
                $datafield->delete();
            }
        }
    }
}

<?php

namespace Tests\Icinga\Module\Director\Objects;

use Icinga\Exception\NotFoundError;
use Icinga\Module\Director\Data\Db\DbObjectTypeRegistry;
use Icinga\Module\Director\Exception\DuplicateKeyException;
use Icinga\Module\Director\Objects\HostGroupMembershipResolver;
use Icinga\Module\Director\Objects\IcingaObject;
use Icinga\Module\Director\Repository\IcingaTemplateRepository;
use Icinga\Module\Director\Test\BaseTestCase;

class HostGroupMembershipResolverTest extends BaseTestCase
{
    const PREFIX = '__groupmembership';
    const TYPE = 'host';

    public function setUp()
    {
        IcingaTemplateRepository::clear();
    }

    public static function cleanArtifacts()
    {
        $db = static::getDb()->getDbAdapter();

        $where = sprintf("object_name LIKE '%s%%'", self::PREFIX);

        $db->delete('icinga_' . self::TYPE . 'group', $where);

        $db->delete('icinga_' . self::TYPE, $where . " AND object_type = 'object'");
        $db->delete('icinga_' . self::TYPE, $where);
    }

    public static function setUpBeforeClass()
    {
        static::cleanArtifacts();
    }

    public static function tearDownAfterClass()
    {
        static::cleanArtifacts();
    }

    /**
     * @param string $type
     * @param string $name
     * @param array  $props
     *
     * @return IcingaObject
     * @throws DuplicateKeyException
     * @throws \Icinga\Exception\ConfigurationError
     */
    protected function object($type, $name, $props = [])
    {
        $db = $this->getDb();
        $fullName = self::PREFIX . $name;
        $object = null;

        try {
            $object = IcingaObject::loadByType($type, $fullName, $db);

            foreach ($props as $k => $v) {
                $object->set($k, $v);
            }

            $object->store();
        } catch (NotFoundError $e) {
            $object = null;
        }

        if ($object === null) {
            $object = IcingaObject::createByType($type, array_merge([
                'object_name' => $fullName,
                'object_type' => 'object',
            ], $props), $this->getDb());

            $object->store();
        }

        return $object;
    }

    protected function objects($type)
    {
        /** @var IcingaObject $class */
        $class = DbObjectTypeRegistry::classByType($type);

        /** @var IcingaObject $dummy */
        $dummy = $class::create();

        $table = $dummy->getTableName();
        $query = $this->getDb()->getDbAdapter()->select()
            ->from($table)
            ->where('object_name LIKE ?', self::PREFIX . '%');

        $objects = [];
        $l = strlen(self::PREFIX);

        foreach ($class::loadAll($this->getDb(), $query) as $object) {
            $key = substr($object->getObjectName(), $l);
            $objects[$key] = $object;
        }

        return $objects;
    }

    protected function resolved()
    {
        $db = $this->getDb()->getDbAdapter();

        $select = $db->select()
            ->from(
                ['r' => 'icinga_' . self::TYPE . 'group_' . self::TYPE . '_resolved'],
                []
            )->join(
                ['o' => 'icinga_' . self::TYPE],
                'o.id = r.' . self::TYPE . '_id',
                ['object' => 'object_name']
            )->join(
                ['g' => 'icinga_' . self::TYPE . 'group'],
                'g.id = r.' . self::TYPE . 'group_id',
                ['groupname' => 'object_name']
            );

        $map = [];
        $l = strlen(self::PREFIX);

        foreach ($db->fetchAll($select) as $row) {
            $o = $row->object;
            $g = $row->groupname;

            if (! substr($o, 0, $l) === self::PREFIX) {
                continue;
            }
            $o = substr($o, $l);

            if (! substr($g, 0, $l) === self::PREFIX) {
                continue;
            }
            $g = substr($g, $l);

            if (! array_key_exists($o, $map)) {
                $map[$o] = [];
            }

            $map[$o][] = $g;
        }

        return $map;
    }

    /**
     * Creates:
     *
     * - 1 template
     * - 10 hosts importing the template with a var match_var=magic
     *
     * @throws DuplicateKeyException
     * @throws \Icinga\Exception\ConfigurationError
     */
    public function testCreateHosts()
    {
        // template that sets a group later
        $template = $this->object('host', 'template', [
            'object_type' => 'template',
        ]);
        $this->assertTrue($template->hasBeenLoadedFromDb());

        // hosts to assign groups
        for ($i = 1; $i <= 10; $i++) {
            $host = $this->object('host', $i, [
                'imports' => self::PREFIX . 'template',
                'vars.match_var' => 'magic'
            ]);
            $this->assertTrue($host->hasBeenLoadedFromDb());
        }
    }

    /**
     * Creates:
     *
     * - 10 hostgroups applying on hosts with match_var=magic
     * - 2 static hostgroups
     *
     * @throws DuplicateKeyException
     * @throws \Icinga\Exception\ConfigurationError
     */
    public function testCreateHostgroups()
    {
        $filter = 'host.vars.match_var=%22magic%22';
        for ($i = 1; $i <= 10; $i++) {
            $hostgroup = $this->object('hostgroup', 'apply' . $i, [
                'assign_filter' => $filter
            ]);
            $this->assertTrue($hostgroup->hasBeenLoadedFromDb());
        }

        // static groups
        for ($i = 1; $i <= 2; $i++) {
            $hostgroup = $this->object('hostgroup', 'static' . $i);
            $this->assertTrue($hostgroup->hasBeenLoadedFromDb());
        }
    }

    /**
     * Assigns static groups to:
     *
     * - the template
     * - the first host
     *
     * @throws DuplicateKeyException
     * @throws \Icinga\Exception\ConfigurationError
     *
     * @depends testCreateHosts
     * @depends testCreateHostgroups
     */
    public function testAddStaticGroups()
    {
        // add group to template
        $template = $this->object('host', 'template');
        $template->setGroups(self::PREFIX . 'static1');
        $template->store();
        $this->assertFalse($template->hasBeenModified());

        // add group to first host
        $host = $this->object('host', 1);
        $host->setGroups(self::PREFIX . 'static2');
        $host->store();
        $this->assertFalse($host->hasBeenModified());
    }

    /**
     * Asserts that static groups are resolved for hosts:
     *
     * - all but first should have static1
     * - first should have static2
     *
     * @depends testAddStaticGroups
     */
    public function testStaticResolvedMappings()
    {
        $resolved = $this->resolved();

        $this->assertArrayHasKey(
            1,
            $resolved,
            'Host 1 must have groups resolved'
        );

        $this->assertContains(
            'static2',
            $resolved[1],
            'Host template must have static group 1'
        );

        $hosts = $this->objects('host');
        $this->assertNotEmpty($hosts, 'Must have hosts found in DB');

        foreach ($hosts as $name => $host) {
            if ($host->object_type === 'template') {
                continue;
            }

            $this->assertArrayHasKey(
                $name,
                $resolved,
                'All hosts must have groups resolved'
            );

            if ($name === 1) {
                $this->assertNotContains(
                    'static1',
                    $resolved[$name],
                    'First host must not have static group 1'
                );
            } else {
                $this->assertContains(
                    'static1',
                    $resolved[$name],
                    'All hosts but the first must have static group 1'
                );
            }
        }
    }

    /**
     * @depends testCreateHostgroups
     */
    public function testApplyResolvedMappings()
    {
        $resolved = $this->resolved();

        $hosts = $this->objects('host');
        $this->assertNotEmpty($hosts, 'Must have hosts found in DB');

        foreach ($hosts as $name => $host) {
            if ($host->object_type === 'template') {
                continue;
            }

            $this->assertArrayHasKey($name, $resolved, 'Host must have groups resolved');

            for ($i = 1; $i <= 10; $i++) {
                $this->assertContains(
                    'apply' . $i,
                    $resolved[$name],
                    'All Host objects must have all applied groups'
                );
            }
        }
    }

    /**
     * @throws DuplicateKeyException
     * @throws \Icinga\Exception\ConfigurationError
     *
     * @depends testAddStaticGroups
     */
    public function testChangeAppliedGroupsAfterStatic()
    {
        $filter = 'host.vars.match_var=%22magic*%22';

        $hostgroup = $this->object('hostgroup', 'apply1', [
            'assign_filter' => $filter
        ]);
        $this->assertTrue($hostgroup->hasBeenLoadedFromDb());
        $this->assertFalse($hostgroup->hasBeenModified());

        $resolved = $this->resolved();

        for ($i = 1; $i <= 10; $i++) {
            $this->assertContains(
                'apply1',
                $resolved[$i],
                'All Host objects must have apply1 group'
            );
        }
    }

    /**
     * @throws \Icinga\Exception\ConfigurationError
     * @throws \Zend_Db_Adapter_Exception
     *
     * @depends testStaticResolvedMappings
     * @depends testApplyResolvedMappings
     * @depends testChangeAppliedGroupsAfterStatic
     */
    public function testFullRecheck()
    {
        $resolver = new HostGroupMembershipResolver($this->getDb());

        $resolver->checkDb();
        $this->assertEmpty($resolver->getNewMappings(), 'There should not be any new mappings');
        $this->assertEmpty($resolver->getOutdatedMappings(), 'There should not be any outdated mappings');
    }
}

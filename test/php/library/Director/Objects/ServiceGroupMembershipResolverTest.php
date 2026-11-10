<?php

// SPDX-FileCopyrightText: 2026 Icinga GmbH <https://icinga.com>
// SPDX-License-Identifier: GPL-3.0-or-later

namespace Tests\Icinga\Module\Director\Objects;

use Icinga\Module\Director\Db\Cache\PrefetchCache;
use Icinga\Module\Director\Objects\IcingaObject;
use Icinga\Module\Director\Objects\IcingaService;
use Icinga\Module\Director\Objects\IcingaServiceGroup;
use Icinga\Module\Director\Objects\ServiceGroupMembershipResolver;
use Icinga\Module\Director\Repository\IcingaTemplateRepository;
use Icinga\Module\Director\Test\BaseTestCase;
use RuntimeException;
use Zend_Db_Profiler;

class ServiceGroupMembershipResolverTest extends BaseTestCase
{
    /** @var string */
    protected const PREFIX = '__service_membership_';

    protected IcingaObject $host;

    protected IcingaServiceGroup $group;

    /** @var list<IcingaObject> */
    protected array $services = [];

    public function setUp(): void
    {
        parent::setUp();
        $this->skipForMissingDb();
        $this->cleanArtifacts();
        PrefetchCache::forget();
        IcingaTemplateRepository::clear();

        $this->host = $this->createObject('host', 'host');
        $this->createObject('servicegroup', 'static');
        $this->createObject('servicegroup', 'inherited');
        $template = $this->createObject('service', 'template', [
            'object_type' => 'template',
            'groups'      => [static::PREFIX . 'inherited'],
        ]);
        $this->getDb()->getDbAdapter()->insert('icinga_service_var', [
            'service_id' => $template->get('id'),
            'varname'    => 'membership',
            'varvalue'   => 'abc',
            'format'     => 'string',
        ]);
        IcingaTemplateRepository::clear();

        for ($i = 0; $i < 8; $i++) {
            $properties = [
                'host_id' => $this->host->get('id'),
                'imports' => [static::PREFIX . 'template'],
            ];
            if ($i === 0) {
                $properties['groups'] = [static::PREFIX . 'static'];
                $properties['vars.membership'] = 'abcd';
            }
            $this->services[] = $this->createObject('service', 'service' . $i, $properties);
        }

        $this->createObject('servicegroup', 'unrelated', [
            'assign_filter' => 'service.name=%22' . static::PREFIX . 'service*%22',
        ]);
        $this->group = $this->createObject('servicegroup', 'dynamic', [
            'assign_filter' => 'service.vars.membership=%22abc%22',
        ]);
        PrefetchCache::forget();
        IcingaTemplateRepository::clear();
    }

    public function tearDown(): void
    {
        PrefetchCache::forget();
        IcingaTemplateRepository::clear();
        if ($this->hasDb()) {
            $this->cleanArtifacts();
        }
        parent::tearDown();
    }

    public function testFilterChangesPersistAndPreserveOtherMemberships(): void
    {
        $this->assertMemberships('abc');
        $db = $this->getDb()->getDbAdapter();
        $logQuery = $db->select()->from('director_activity_log', ['COUNT(*)'])
            ->where('object_name = ?', $this->group->getObjectName());
        $before = (int) $db->fetchOne($logQuery);

        $this->group->set('assign_filter', 'service.vars.membership=%22abcd%22')->store();

        $stored = IcingaServiceGroup::load($this->group->getObjectName(), $this->getDb());
        $this->assertSame('service.vars.membership=%22abcd%22', $stored->get('assign_filter'));
        $this->assertSame($before + 1, (int) $db->fetchOne($logQuery));
        $log = $db->fetchRow($db->select()->from('director_activity_log')
            ->where('object_name = ?', $this->group->getObjectName())->order('id DESC')->limit(1));
        $this->assertSame('service.vars.membership=%22abcd%22', json_decode($log->new_properties)->assign_filter);
        $this->assertMemberships('abcd');
        $this->assertFalse(PrefetchCache::shouldBeUsed());

        $this->group->set('assign_filter', 'service.vars.membership=%22abc%22')->store();
        $this->assertMemberships('abc');
    }

    public function testFullRecheckBoundsRelatedObjectReads(): void
    {
        $db = $this->getDb()->getDbAdapter();
        $previousProfiler = $db->getProfiler();
        $profiler = new Zend_Db_Profiler(true);
        $db->setProfiler($profiler);
        try {
            $resolver = new ServiceGroupMembershipResolver($this->getDb());
            $resolver->checkDb();
            $this->assertEmpty($resolver->getNewMappings());
            $this->assertEmpty($resolver->getOutdatedMappings());
        } finally {
            $db->setProfiler($previousProfiler);
        }

        foreach (['icinga_host', 'icinga_service_var', 'icinga_servicegroup_service'] as $table) {
            $queries = [];
            foreach ($profiler->getQueryProfiles(Zend_Db_Profiler::SELECT) ?: [] as $query) {
                $sql = str_replace(['"', '`'], '', $query->getQuery());
                if (preg_match('/\b(?:FROM|JOIN)\s+' . $table . '\b/i', $sql)) {
                    $queries[] = $sql;
                }
            }
            $this->assertNotEmpty($queries, $table);
            $this->assertLessThanOrEqual(2, count($queries), implode("\n", $queries));
        }
        $this->assertFalse(PrefetchCache::shouldBeUsed());
    }

    public function testFullRecheckPreservesCallerCache(): void
    {
        PrefetchCache::initialize($this->getDb());
        $cache = PrefetchCache::instance();
        (new ServiceGroupMembershipResolver($this->getDb()))->checkDb();
        $this->assertSame($cache, PrefetchCache::instance());
    }

    public function testLoadingFailureReleasesOwnedCache(): void
    {
        $resolver = $this->failingResolver();
        try {
            $resolver->checkDb();
            $this->fail('Loading must throw');
        } catch (RuntimeException $e) {
            $this->assertSame('Loading failed', $e->getMessage());
        }
        $this->assertFalse(PrefetchCache::shouldBeUsed());
    }

    public function testLoadingFailurePreservesCallerCache(): void
    {
        PrefetchCache::initialize($this->getDb());
        $cache = PrefetchCache::instance();
        try {
            $this->failingResolver()->checkDb();
            $this->fail('Loading must throw');
        } catch (RuntimeException $e) {
            $this->assertSame('Loading failed', $e->getMessage());
        }
        $this->assertSame($cache, PrefetchCache::instance());
    }

    public function testSuppliedObjectUsesUnsavedChanges(): void
    {
        $service = IcingaService::load([
            'host_id'     => $this->host->get('id'),
            'object_name' => static::PREFIX . 'service0',
        ], $this->getDb());
        $service->set('vars.membership', 'abc');
        $resolver = new ServiceGroupMembershipResolver($this->getDb());
        $resolver->setGroup($this->group)->setObject($service)->checkDb();
        $this->assertEquals([[
            'servicegroup_id' => $this->group->get('id'),
            'service_id'      => $service->get('id'),
        ]], $resolver->getNewMappings());
        $this->assertFalse(PrefetchCache::shouldBeUsed());
    }

    protected function failingResolver(): ServiceGroupMembershipResolver
    {
        return new class ($this->getDb()) extends ServiceGroupMembershipResolver {
            protected function fetchAllObjects(): never
            {
                if (! PrefetchCache::shouldBeUsed()) {
                    throw new RuntimeException('Cache missing during loading');
                }
                throw new RuntimeException('Loading failed');
            }
        };
    }

    /**
     * @param string $type
     * @param string $name
     * @param array<string, int|string|list<string>> $properties
     *
     * @return ($type is 'servicegroup' ? IcingaServiceGroup : IcingaObject)
     */
    protected function createObject(string $type, string $name, array $properties = []): IcingaObject
    {
        $object = $this->newObject($type, static::PREFIX . $name, $properties);
        $object->store();

        return $object;
    }

    protected function assertMemberships(string $value): void
    {
        $db = $this->getDb()->getDbAdapter();
        foreach ($this->services as $i => $service) {
            $actual = $db->fetchCol($db->select()->from(['r' => 'icinga_servicegroup_service_resolved'], [])
                ->join(['g' => 'icinga_servicegroup'], 'g.id = r.servicegroup_id', ['object_name'])
                ->where('r.service_id = ?', $service->get('id')));
            $expected = [static::PREFIX . ($i === 0 ? 'static' : 'inherited'), static::PREFIX . 'unrelated'];
            if (($i === 0 ? 'abcd' : 'abc') === $value) {
                $expected[] = static::PREFIX . 'dynamic';
            }
            sort($actual);
            sort($expected);
            $this->assertSame($expected, $actual, $service->getObjectName());
        }
    }

    protected function cleanArtifacts(): void
    {
        $db = $this->getDb()->getDbAdapter();
        $where = $db->quoteInto('object_name LIKE ?', static::PREFIX . '%');
        $db->delete('icinga_servicegroup', $where);
        $db->delete('icinga_service', $where . " AND object_type = 'object'");
        $db->delete('icinga_service', $where);
        $db->delete('icinga_host', $where);
        $db->delete('director_activity_log', $where);
    }
}

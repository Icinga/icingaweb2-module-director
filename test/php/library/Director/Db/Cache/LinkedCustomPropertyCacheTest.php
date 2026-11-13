<?php

// SPDX-FileCopyrightText: 2026 Icinga GmbH <https://icinga.com>
// SPDX-License-Identifier: GPL-3.0-or-later

namespace Tests\Icinga\Module\Director\Db\Cache;

use Icinga\Module\Director\Db\Cache\PrefetchCache;
use Icinga\Module\Director\Db\DbUtil;
use Icinga\Module\Director\Objects\DirectorProperty;
use Icinga\Module\Director\Objects\IcingaObject;
use Icinga\Module\Director\Repository\IcingaTemplateRepository;
use Icinga\Module\Director\Test\BaseTestCase;
use Ramsey\Uuid\Uuid;
use RuntimeException;
use Zend_Db_Profiler;

class LinkedCustomPropertyCacheTest extends BaseTestCase
{
    /** @var string */
    protected const PREFIX = '__linked_property_cache_';

    protected ?IcingaObject $serviceHost = null;

    public function setUp(): void
    {
        parent::setUp();
        $this->skipForMissingDb();
        PrefetchCache::forget();
        IcingaTemplateRepository::clear();
        $this->cleanArtifacts();
        $this->serviceHost = $this->storeObject('host', 'service_host');
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

    public function testServicesShareEmptyLinkedPropertyResults(): void
    {
        $template = $this->storeObject('service', 'legacy_template', [
            'object_type'     => 'template',
            'vars.membership' => 'matching',
        ]);
        $services = [];
        for ($i = 0; $i < 8; $i++) {
            $service = $this->storeObject('service', 'service' . $i, [
                'imports' => [$template->getObjectName()],
            ]);
            $services[] = $this->reload($service);
        }

        PrefetchCache::initialize($this->getDb());
        $db = $this->getDb()->getDbAdapter();
        $previousProfiler = $db->getProfiler();
        $profiler = new Zend_Db_Profiler(true);
        $db->setProfiler($profiler);
        try {
            foreach ($services as $service) {
                $this->assertSame('matching', $service->getResolvedVars()->membership);
                $this->assertSame([], $service->fetchAllLinkedCustomProperties());
            }
        } finally {
            $db->setProfiler($previousProfiler);
        }

        $this->assertLinkedPropertyQueryCount(1, $profiler);
    }

    public function testDictionaryMergeKeepsTemplatePrecedence(): void
    {
        $key = static::PREFIX . 'dictionary';
        $property = $this->storeProperty('dictionary', 'dynamic-dictionary');
        $objects = [];
        foreach (['host', 'service'] as $type) {
            $a = $this->storeObject($type, 'a', [
                'object_type'  => 'template',
                'vars.' . $key => (object) ['from_a' => 'a', 'shared' => 'a'],
            ]);
            $b = $this->storeObject($type, 'b', [
                'object_type'  => 'template',
                'vars.' . $key => (object) ['from_b' => 'b', 'shared' => 'b'],
            ]);
            $this->linkProperty($property, $a);
            $this->linkProperty($property, $b);
            foreach (['ab', 'ab_twin', 'ba'] as $name) {
                $object = $this->storeObject($type, $name, [
                    'imports' => $name === 'ba'
                        ? [$b->getObjectName(), $a->getObjectName()]
                        : [$a->getObjectName(), $b->getObjectName()],
                    'vars.' . $key => (object) ['own' => $name],
                ]);
                $objects[$type][$name] = $this->reload($object);
            }
        }

        PrefetchCache::initialize($this->getDb());
        $db = $this->getDb()->getDbAdapter();
        $previousProfiler = $db->getProfiler();
        $profiler = new Zend_Db_Profiler(true);
        $db->setProfiler($profiler);
        try {
            foreach ($objects as $byName) {
                foreach ($byName as $name => $object) {
                    $expected = $name === 'ba'
                        ? ['from_b' => 'b', 'shared' => 'a', 'from_a' => 'a', 'own' => $name]
                        : ['from_a' => 'a', 'shared' => 'b', 'from_b' => 'b', 'own' => $name];
                    $this->assertSame($expected, (array) $object->getResolvedVars()->$key);
                    $metadata = $object->fetchAllLinkedCustomProperties();
                    $this->assertSame([$key], array_keys($metadata));
                    $this->assertSame('dynamic-dictionary', $metadata[$key]->value_type);
                    $this->assertSame($property->get('uuid'), DbUtil::binaryResult($metadata[$key]->uuid));
                }
            }
        } finally {
            $db->setProfiler($previousProfiler);
        }

        $this->assertLinkedPropertyQueryCount(4, $profiler);
    }

    public function testObjectTypeAndChangedImportsSelectTheirOwnMetadata(): void
    {
        $hostProperty = $this->storeProperty('host_property', 'string');
        $serviceProperty = $this->storeProperty('service_property', 'string');
        $extraProperty = $this->storeProperty('extra_property', 'string');
        $hostTemplate = $this->storeObject('host', 'shared_template', ['object_type' => 'template']);
        $serviceTemplate = $this->storeObject('service', 'shared_template', ['object_type' => 'template']);
        $extraTemplate = $this->storeObject('host', 'extra_template', ['object_type' => 'template']);
        $this->linkProperty($hostProperty, $hostTemplate);
        $this->linkProperty($serviceProperty, $serviceTemplate);
        $this->linkProperty($extraProperty, $extraTemplate);
        $host = $this->reload($this->storeObject('host', 'host', [
            'imports' => [$hostTemplate->getObjectName()],
        ]));
        $service = $this->reload($this->storeObject('service', 'service', [
            'imports' => [$serviceTemplate->getObjectName()],
        ]));

        PrefetchCache::initialize($this->getDb());
        $this->assertSame(
            [static::PREFIX . 'host_property'],
            array_keys($host->fetchAllLinkedCustomProperties())
        );
        $this->assertSame(
            [static::PREFIX . 'service_property'],
            array_keys($service->fetchAllLinkedCustomProperties())
        );

        $host->set('imports', [$hostTemplate->getObjectName(), $extraTemplate->getObjectName()]);
        $metadata = $host->fetchAllLinkedCustomProperties();
        $this->assertCount(2, $metadata);
        $this->assertArrayHasKey(static::PREFIX . 'host_property', $metadata);
        $this->assertArrayHasKey(static::PREFIX . 'extra_property', $metadata);
        $host->set('imports', []);
        $this->assertSame([], $host->fetchAllLinkedCustomProperties());
        $host->set('imports', [$hostTemplate->getObjectName()]);
        $this->assertSame(
            [static::PREFIX . 'host_property'],
            array_keys($host->fetchAllLinkedCustomProperties())
        );
    }

    public function testMetadataStaysFreshOutsideThePrefetchLifetime(): void
    {
        $template = $this->storeObject('host', 'template', ['object_type' => 'template']);
        $host = $this->reload($this->storeObject('host', 'host', ['imports' => [$template->getObjectName()]]));
        $property = $this->storeProperty('dictionary', 'dynamic-dictionary');
        $key = $property->get('key_name');

        $this->assertSame([], $host->fetchAllLinkedCustomProperties());
        $this->linkProperty($property, $template);
        $this->assertSame('dynamic-dictionary', $host->fetchAllLinkedCustomProperties()[$key]->value_type);
        $this->unlinkProperty($property, $template);

        PrefetchCache::initialize($this->getDb());
        $this->assertSame([], $host->fetchAllLinkedCustomProperties());
        $this->linkProperty($property, $template);
        $this->assertSame([], $host->fetchAllLinkedCustomProperties());
        PrefetchCache::forget();
        $this->assertSame('dynamic-dictionary', $host->fetchAllLinkedCustomProperties()[$key]->value_type);

        PrefetchCache::initialize($this->getDb());
        $first = $host->fetchAllLinkedCustomProperties();
        $first[$key]->value_type = 'fixed-dictionary';
        $second = $host->fetchAllLinkedCustomProperties();
        $this->assertSame('dynamic-dictionary', $second[$key]->value_type);
        $second[$key]->value_type = 'fixed-array';
        $this->assertSame('dynamic-dictionary', $host->fetchAllLinkedCustomProperties()[$key]->value_type);
        $property->set('value_type', 'string')->store();
        $this->assertSame('dynamic-dictionary', $host->fetchAllLinkedCustomProperties()[$key]->value_type);
        PrefetchCache::forget();
        $this->assertSame('string', $host->fetchAllLinkedCustomProperties()[$key]->value_type);
        PrefetchCache::initialize($this->getDb());
        $this->assertSame('string', $host->fetchAllLinkedCustomProperties()[$key]->value_type);
    }

    public function testFailedLookupCanBeRetried(): void
    {
        PrefetchCache::initialize($this->getDb());
        $attempts = 0;
        $load = function () use (&$attempts): array {
            if (++$attempts === 1) {
                throw new RuntimeException('Metadata loading failed');
            }

            return [];
        };

        try {
            PrefetchCache::instance()->linkedCustomProperties('service', ['template'], $load);
            $this->fail('Metadata loading must throw');
        } catch (RuntimeException $e) {
            $this->assertSame('Metadata loading failed', $e->getMessage());
        }

        $this->assertSame([], PrefetchCache::instance()->linkedCustomProperties('service', ['template'], $load));
        $this->assertSame([], PrefetchCache::instance()->linkedCustomProperties('service', ['template'], $load));
        $this->assertSame(2, $attempts);
    }

    /**
     * @param string $type
     * @param string $name
     * @param array<string, string|list<string>|\stdClass> $properties
     *
     * @return IcingaObject
     */
    protected function storeObject(string $type, string $name, array $properties = []): IcingaObject
    {
        if ($type === 'service' && ($properties['object_type'] ?? 'object') === 'object') {
            $properties['host_id'] = $this->serviceHost->get('id');
        }
        $object = $this->newObject($type, static::PREFIX . $name, $properties);
        $object->store();

        return $object;
    }

    protected function reload(IcingaObject $object): IcingaObject
    {
        return $object::load($object->getId(), $this->getDb());
    }

    protected function storeProperty(string $name, string $type): DirectorProperty
    {
        $property = DirectorProperty::create([
            'uuid'       => Uuid::uuid4()->getBytes(),
            'key_name'   => static::PREFIX . $name,
            'value_type' => $type,
            'label'      => $name,
        ], $this->getDb());
        $property->store();

        return $property;
    }

    protected function linkProperty(DirectorProperty $property, IcingaObject $template): void
    {
        $db = $this->getDb()->getDbAdapter();
        $this->getDb()->insert('icinga_' . $template->getShortTableName() . '_property', [
            'property_uuid'                          => DbUtil::quoteBinaryCompat($property->get('uuid'), $db),
            $template->getShortTableName() . '_uuid' => DbUtil::quoteBinaryCompat($template->get('uuid'), $db),
        ]);
    }

    protected function unlinkProperty(DirectorProperty $property, IcingaObject $template): void
    {
        $db = $this->getDb()->getDbAdapter();
        $db->delete(
            'icinga_' . $template->getShortTableName() . '_property',
            $db->quoteInto('property_uuid = ?', DbUtil::quoteBinaryCompat($property->get('uuid'), $db))
        );
    }

    protected function assertLinkedPropertyQueryCount(int $expected, Zend_Db_Profiler $profiler): void
    {
        $queries = [];
        foreach ($profiler->getQueryProfiles(Zend_Db_Profiler::SELECT) ?: [] as $query) {
            $sql = str_replace(['"', '`'], '', $query->getQuery());
            if (preg_match('/\bFROM\s+director_property\b/i', $sql)) {
                $queries[] = $sql;
            }
        }
        $this->assertCount($expected, $queries, implode("\n", $queries));
    }

    protected function cleanArtifacts(): void
    {
        $db = $this->getDb()->getDbAdapter();
        $where = $db->quoteInto('object_name LIKE ?', static::PREFIX . '%');
        foreach (['icinga_service', 'icinga_host'] as $table) {
            $db->delete($table, $where . " AND object_type = 'object'");
            $db->delete($table, $where);
        }
        $db->delete('director_property', $db->quoteInto('key_name LIKE ?', static::PREFIX . '%'));
        $db->delete('director_activity_log', $where);
    }
}

<?php

namespace Tests\Icinga\Module\Director\ProvidedHook\Icingadb;

use Icinga\Application\Config;
use Icinga\Module\Director\CustomVariable\CustomVariable;
use Icinga\Module\Director\Db\DbUtil;
use Icinga\Module\Director\Objects\DirectorDatafieldCategory;
use Icinga\Module\Director\Objects\DirectorProperty;
use Icinga\Module\Director\Objects\IcingaHost;
use Icinga\Module\Director\ProvidedHook\Icingadb\CustomVarRenderer;
use Icinga\Module\Director\Test\BaseTestCase;
use ReflectionMethod;
use Ramsey\Uuid\Uuid;

class CustomVarRendererTest extends BaseTestCase
{
    private const PREFIX = '___TEST___';
    private const TEMPLATE_NAME = self::PREFIX . 'webserver-template';
    private const PROPERTY_KEY = self::PREFIX . 'ssh_credentials';
    private const CATEGORY_NAME = self::PREFIX . 'monitoring_team';

    public function setUp(): void
    {
        parent::setUp();

        // CustomVarRenderer::db() resolves its own db connection via
        // Config::module('director')->get('db', 'resource'), independently of
        // BaseTestCase's own db handling. Point it at the very same resources.ini
        // entry BaseTestCase uses, so both sides talk to the same test database.
        if ($this->hasDb()) {
            Config::module('director')->setSection('db', ['resource' => static::getDbResourceName()]);
        }
    }

    public function testGetObjectCustomPropertiesQueryRunsWithACategoryJoined(): void
    {
        if ($this->skipForMissingDb()) {
            return;
        }

        $db = $this->getDb();
        $dba = $db->getDbAdapter();

        if (IcingaHost::exists(self::TEMPLATE_NAME, $db)) {
            IcingaHost::load(self::TEMPLATE_NAME, $db)->delete();
        }

        $host = IcingaHost::create([
            'object_name' => self::TEMPLATE_NAME,
            'object_type' => 'template',
        ]);
        $host->store($db);

        $category = DirectorDatafieldCategory::create([
            'category_name' => self::CATEGORY_NAME,
        ], $db);
        $category->store();

        $property = DirectorProperty::create([
            'uuid'        => Uuid::uuid4()->getBytes(),
            'key_name'    => self::PROPERTY_KEY,
            'value_type'  => 'string',
            'label'       => 'SSH credentials',
            'category_id' => $category->get('id'),
        ], $db);
        $property->store();

        $dba->insert('icinga_host_property', [
            'property_uuid' => DbUtil::quoteBinaryCompat($property->get('uuid'), $dba),
            'host_uuid'     => DbUtil::quoteBinaryCompat($host->get('uuid'), $dba),
        ]);

        $renderer = new CustomVarRenderer();
        $method = new ReflectionMethod($renderer, 'getObjectCustomProperties');
        $method->setAccessible(true);

        // This must not throw. Under PostgreSQL (and MySQL with ONLY_FULL_GROUP_BY),
        // selecting cpc.category_name / iop.host_uuid without grouping or aggregating
        // them raises a SQL error instead of returning a result.
        $result = $method->invoke($renderer, $host);

        $this->assertArrayHasKey(self::PROPERTY_KEY, $result);
        $this->assertEquals(self::CATEGORY_NAME, $result[self::PROPERTY_KEY]['category']);
    }

    public function testSameNamedChildrenInDifferentDictionariesDoNotShareVisibility(): void
    {
        $renderer = new TestableCustomVarRenderer();

        // Two unrelated dictionaries each have a "password" child. Only the database
        // connection's password is meant to be masked - the monitoring API key's
        // "password" child (a plain webhook token field) must render in the clear.
        $renderer->seedDictionaryChild('database_connection', 'password', [
            'label' => 'Database password',
            'visibility' => 'hidden',
        ]);
        $renderer->seedDictionaryChild('monitoring_api_key', 'password', [
            'label' => 'Webhook token',
        ]);
        $renderer->seedDictionaryName('database_connection');
        $renderer->seedDictionaryName('monitoring_api_key');

        $sensitiveRendered = $renderer->renderCustomVarValue('password', 's3cr3t-db-pass', 'database_connection');
        $plainRendered = $renderer->renderCustomVarValue('password', 'webhook-token-abc123', 'monitoring_api_key');

        $this->assertEquals('***', $sensitiveRendered);
        // A plain value with nothing special to render returns null by design, letting
        // the caller fall back to the raw value (see the "?? $value" call sites in
        // renderDictionaryVal()). The point of this assertion is that it must NOT be
        // masked just because an unrelated dictionary has a sensitive child sharing the
        // same key_name.
        $this->assertNotEquals(
            '***',
            $plainRendered,
            'A non-sensitive dictionary child must not be masked due to a same-named '
            . 'sensitive child in a different dictionary'
        );
    }

    public function testSameNamedNestedDictionariesUnderDifferentRootsDoNotShareVisibility(): void
    {
        $renderer = new TestableCustomVarRenderer();

        // Two top level dictionaries each nest a "credentials" dictionary with a
        // password child. Only server_a's is sensitive, server_b's must stay in the clear.
        $renderer->seedDictionaryChild('credentials', 'password', [
            'label' => 'Database password',
            'visibility' => 'hidden',
        ], 'server_a');
        $renderer->seedDictionaryChild('credentials', 'password', [
            'label' => 'API token',
        ], 'server_b');

        $sensitiveRendered = $renderer->renderCustomVarValue('password', 's3cr3t', 'credentials', 'server_a');
        $plainRendered = $renderer->renderCustomVarValue('password', 'plain-token', 'credentials', 'server_b');

        $this->assertEquals('***', $sensitiveRendered);
        $this->assertNotEquals(
            '***',
            $plainRendered,
            'A non-sensitive child must not be masked by a same-named sensitive child under an unrelated root'
        );
    }

    public function testSameNamedArrayItemsUnderDifferentDictionariesDoNotShareMasking(): void
    {
        $renderer = new TestableCustomVarRenderer();

        // Two dictionaries each nest a fixed-array named "targets". Only network_a's
        // second item is sensitive, network_b's matching position must stay in the clear.
        $renderer->seedDictionaryChild('network_a', 'targets', ['label' => 'Targets']);
        $renderer->seedDictionaryChild('network_b', 'targets', ['label' => 'Targets']);
        $renderer->seedSensitiveArrayItem('targets', '1', 'network_a');

        $maskedValue = $renderer->renderCustomVarValue('targets', ['host1', 'secret-host2'], 'network_a');
        $plainValue = $renderer->renderCustomVarValue('targets', ['host1', 'host2'], 'network_b');

        $this->assertEquals('***', $maskedValue[1]);
        $this->assertNotEquals(
            '***',
            $plainValue[1],
            'An array item must not be masked by a same-named sensitive item in an unrelated dictionary'
        );
    }

    public function testDynamicDictionaryEntriesMaskSensitiveChildrenAcrossEveryEntry(): void
    {
        $renderer = new TestableCustomVarRenderer();

        // "servers" is a dynamic-dictionary: end users add arbitrary entries
        // ("primary", "backup", ...), each a sub-dictionary with a schema-declared
        // "password" child. The schema child is scoped directly under "servers",
        // not under the runtime entry key - rendering must look it up the same way,
        // or the sensitive value slips through in the clear for every entry.
        $renderer->seedDictionaryName('servers');
        $renderer->seedPropertyValueType('servers', 'dynamic-dictionary');
        $renderer->seedDictionaryChild('servers', 'password', [
            'label'      => 'Password',
            'visibility' => 'hidden',
        ]);

        $html = $renderer->renderDictionaryValForTest('servers', [
            'primary' => ['password' => 'hunter2'],
            'backup'  => ['password' => 'hunter3'],
        ])->render();

        $this->assertStringNotContainsString('hunter2', $html);
        $this->assertStringNotContainsString('hunter3', $html);
        $this->assertStringContainsString('***', $html);
    }

    public function testNestedFixedDictionaryChildStillMasksUnderItsOwnScope(): void
    {
        $renderer = new TestableCustomVarRenderer();

        // "config" is a fixed-dictionary whose "db" child is itself a nested
        // fixed-dictionary with a "password" child. Unlike a dynamic-dictionary,
        // "db" is a real declared schema child of "config", so the existing
        // scope chain (child under db under config) must still apply unchanged.
        $renderer->seedDictionaryName('config');
        $renderer->seedPropertyValueType('config', 'fixed-dictionary');
        $renderer->seedDictionaryChild('db', 'password', [
            'label'      => 'Database password',
            'visibility' => 'hidden',
        ], 'config');

        $html = $renderer->renderDictionaryValForTest('config', [
            'db' => ['password' => 'super-secret'],
        ])->render();

        $this->assertStringNotContainsString('super-secret', $html);
        $this->assertStringContainsString('***', $html);
    }

    public function testDynamicDictionaryChildSharingParentNameStillMasksWithoutCrashing(): void
    {
        $renderer = new TestableCustomVarRenderer();

        // "credentials" is a dynamic-dictionary keyed by service name (grafana,
        // elasticsearch, ...), and each entry's one field happens to be named
        // "credentials" too, since that's just what the value itself is. dictionaryNames
        // is a flat list of dictionary property names, so a naive check on the child key
        // alone wrongly matches the unrelated top-level "credentials" dictionary and
        // tries to treat the masked string as an array, which used to crash instead of
        // just rendering "***".
        $renderer->seedDictionaryName('credentials');
        $renderer->seedPropertyValueType('credentials', 'dynamic-dictionary');
        $renderer->seedDictionaryChild('credentials', 'credentials', [
            'label'      => 'Credentials',
            'visibility' => 'hidden',
        ], null, 'sensitive');

        $html = $renderer->renderDictionaryValForTest('credentials', [
            'grafana'       => ['credentials' => 'glsa_9f8e7d6c5b4a'],
            'elasticsearch' => ['credentials' => 'esa_1a2b3c4d5e6f'],
        ])->render();

        $this->assertStringNotContainsString('glsa_9f8e7d6c5b4a', $html);
        $this->assertStringNotContainsString('esa_1a2b3c4d5e6f', $html);
        $this->assertStringContainsString('***', $html);
    }

    public function testSensitiveFixedArrayItemMasksInFullyRenderedHtml(): void
    {
        $renderer = new TestableCustomVarRenderer();

        // Unlike testSameNamedArrayItemsUnderDifferentDictionariesDoNotShareMasking,
        // this renders the full HTML table, not just the array return value. Masking
        // an array item used to build a raw "***" string straight into an HtmlElement,
        // which requires a ValidHtml, not a plain string, and crashed.
        $renderer->seedDictionaryName('backup');
        $renderer->seedDictionaryChild('backup', 'targets', ['label' => 'Targets']);
        $renderer->seedSensitiveArrayItem('targets', '0', 'backup');

        $html = $renderer->renderDictionaryValForTest('backup', [
            'targets' => ['rsync://backup:s3cr3t@backup.example.com/data'],
        ])->render();

        $this->assertStringNotContainsString('rsync://backup:s3cr3t@backup.example.com/data', $html);
        $this->assertStringContainsString('***', $html);
    }

    public function testNestedArrayChildPassedAsObjectRendersWithArraySuffix(): void
    {
        $renderer = new TestableCustomVarRenderer();

        // Real custom var values come from JSON and often arrive as stdClass rather
        // than arrays, so the array/dictionary recursion check must accept objects
        // too, not just arrays.
        $renderer->seedDictionaryName('clusters');
        $renderer->seedPropertyValueType('clusters', 'fixed-dictionary');
        $renderer->seedDictionaryChild('us_east', 'endpoints', ['label' => 'Endpoints'], 'clusters');

        $html = $renderer->renderDictionaryValForTest('clusters', [
            'us_east' => (object) ['endpoints' => (object) ['metrics' => 'https://prometheus.us-east.example.com']],
        ])->render();

        $this->assertStringContainsString('prometheus.us-east.example.com', $html);
        $this->assertStringContainsString('(Array)', $html);
    }

    public function testNestedDictionaryTypedChildRendersWithDictionarySuffix(): void
    {
        $renderer = new TestableCustomVarRenderer();

        // "endpoints" is itself declared as a fixed-dictionary in the schema, scoped
        // under "us_east" under "clusters". The Array/Dictionary label must come from
        // that scoped schema lookup, not from a flat, unscoped name check.
        $renderer->seedDictionaryName('clusters');
        $renderer->seedPropertyValueType('clusters', 'fixed-dictionary');
        $renderer->seedDictionaryChild(
            'us_east',
            'endpoints',
            ['label' => 'Endpoints'],
            'clusters',
            'fixed-dictionary'
        );

        $html = $renderer->renderDictionaryValForTest('clusters', [
            'us_east' => ['endpoints' => ['metrics' => 'https://prometheus.us-east.example.com']],
        ])->render();

        $this->assertStringContainsString('(Dictionary)', $html);
    }

    public function testAppliedForArrayUsesItsValuesAsNameSuffix(): void
    {
        $renderer = new CustomVarRenderer();
        $method = new ReflectionMethod($renderer, 'isGeneratedApplyForServiceName');
        $method->setAccessible(true);

        $hostVar = CustomVariable::create('datacenters', ['fra', 'ams']);

        $this->assertTrue($method->invoke($renderer, $hostVar, 'vhost-', 'vhost-fra'));
        $this->assertFalse($method->invoke($renderer, $hostVar, 'vhost-', 'vhost-lhr'));
    }

    public function testAppliedForDictionaryUsesItsKeysNotValuesAsNameSuffix(): void
    {
        $renderer = new CustomVarRenderer();
        $method = new ReflectionMethod($renderer, 'isGeneratedApplyForServiceName');
        $method->setAccessible(true);

        // Each value is itself an array, which broke the old by-value lookup since
        // it tried to concatenate an array into a string key.
        $hostVar = CustomVariable::create('vhosts', [
            'shop.example.com' => ['port' => 443, 'tls' => true],
            'blog.example.com' => ['port' => 80, 'tls' => false],
        ]);

        $this->assertTrue($method->invoke($renderer, $hostVar, 'vhost-', 'vhost-shop.example.com'));
        $this->assertFalse($method->invoke($renderer, $hostVar, 'vhost-', 'vhost-status.example.com'));
    }

    protected function tearDown(): void
    {
        $db = $this->hasDb() ? $this->getDb() : null;

        if ($db && IcingaHost::exists(self::TEMPLATE_NAME, $db)) {
            IcingaHost::load(self::TEMPLATE_NAME, $db)->delete();
        }

        if ($db && DirectorProperty::exists(self::PROPERTY_KEY, $db)) {
            DirectorProperty::load(self::PROPERTY_KEY, $db)->delete();
        }

        if ($db && DirectorDatafieldCategory::exists(self::CATEGORY_NAME, $db)) {
            DirectorDatafieldCategory::load(self::CATEGORY_NAME, $db)->delete();
        }

        parent::tearDown();
    }
}

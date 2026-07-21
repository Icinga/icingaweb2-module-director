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

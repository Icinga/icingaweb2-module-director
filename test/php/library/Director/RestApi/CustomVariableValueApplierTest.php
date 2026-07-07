<?php

namespace Tests\Icinga\Module\Director\RestApi;

use Icinga\Module\Director\Db\DbUtil;
use Icinga\Module\Director\Objects\DirectorProperty;
use Icinga\Module\Director\Objects\IcingaHost;
use Icinga\Module\Director\RestApi\CustomVariableValueApplier;
use Icinga\Module\Director\Test\BaseTestCase;
use InvalidArgumentException;
use Ramsey\Uuid\Uuid;

class CustomVariableValueApplierTest extends BaseTestCase
{
    private const PREFIX = '___TEST___';
    private const TEMPLATE_NAME = self::PREFIX . 'applier-host';
    private const ENV_KEY = self::PREFIX . 'applier_env';
    private const MYSQL_KEY = self::PREFIX . 'applier_mysql';

    public function testNullValueForUnsetVariableDoesNotCrash(): void
    {
        if ($this->skipForMissingDb()) {
            return;
        }

        $db = $this->getDb();
        $host = $this->createTemplate($db);

        (new CustomVariableValueApplier($db))->apply(
            $host,
            [self::PREFIX . 'never_set' => null],
            'variables',
            'POST',
            false
        );

        $reloaded = IcingaHost::load(self::TEMPLATE_NAME, $db);
        $this->assertNull($reloaded->vars()->get(self::PREFIX . 'never_set'));
    }

    public function testFailedValidationRollsBackFullReplace(): void
    {
        if ($this->skipForMissingDb()) {
            return;
        }

        $db = $this->getDb();
        $host = $this->createTemplate($db);
        $this->attachProperties($host, $db);

        (new CustomVariableValueApplier($db))->apply(
            $host,
            [self::ENV_KEY => 'production'],
            'variables',
            'PUT',
            false
        );

        $host = IcingaHost::load(self::TEMPLATE_NAME, $db);
        $this->assertEquals('production', $host->vars()->get(self::ENV_KEY)->getValue());

        try {
            (new CustomVariableValueApplier($db))->apply(
                $host,
                [self::MYSQL_KEY => ['not', 'a', 'dictionary']],
                'variables',
                'PUT',
                false
            );
            $this->fail('Expected an InvalidArgumentException for a mismatched value shape');
        } catch (InvalidArgumentException $e) {
            // expected, checked below via a fresh load
        }

        $host = IcingaHost::load(self::TEMPLATE_NAME, $db);
        $this->assertNotNull(
            $host->vars()->get(self::ENV_KEY),
            'A failed PUT must not lose the variables that existed before it started'
        );
        $this->assertEquals('production', $host->vars()->get(self::ENV_KEY)->getValue());
    }

    public function testReplaceAllRemovesUnmentionedVariablesOnPost(): void
    {
        if ($this->skipForMissingDb()) {
            return;
        }

        $db = $this->getDb();
        $host = $this->createTemplate($db);
        $this->attachProperties($host, $db);

        (new CustomVariableValueApplier($db))->apply(
            $host,
            [self::ENV_KEY => 'production'],
            'variables',
            'PUT',
            false
        );

        $host = IcingaHost::load(self::TEMPLATE_NAME, $db);

        (new CustomVariableValueApplier($db))->apply(
            $host,
            [self::MYSQL_KEY => (object) ['host' => 'db-primary']],
            'index',
            'POST',
            true
        );

        $host = IcingaHost::load(self::TEMPLATE_NAME, $db);
        $this->assertNull(
            $host->vars()->get(self::ENV_KEY),
            'A full vars dictionary replace must drop variables that were not mentioned'
        );
        $this->assertNotNull($host->vars()->get(self::MYSQL_KEY));
    }

    public function testReplaceAllWithNoOverridesClearsEveryVariable(): void
    {
        if ($this->skipForMissingDb()) {
            return;
        }

        $db = $this->getDb();
        $host = $this->createTemplate($db);
        $this->attachProperties($host, $db);

        (new CustomVariableValueApplier($db))->apply(
            $host,
            [self::ENV_KEY => 'production'],
            'variables',
            'PUT',
            false
        );

        $host = IcingaHost::load(self::TEMPLATE_NAME, $db);

        // This is the base endpoint equivalent of a POST body of {"vars": {}},
        // an explicit but empty full vars dictionary must still clear everything.
        (new CustomVariableValueApplier($db))->apply(
            $host,
            [],
            'index',
            'POST',
            true
        );

        $host = IcingaHost::load(self::TEMPLATE_NAME, $db);
        $this->assertNull(
            $host->vars()->get(self::ENV_KEY),
            'An explicit empty vars dictionary must clear existing variables, not no op'
        );
    }

    private function createTemplate($db): IcingaHost
    {
        if (IcingaHost::exists(self::TEMPLATE_NAME, $db)) {
            IcingaHost::load(self::TEMPLATE_NAME, $db)->delete();
        }

        $host = IcingaHost::create([
            'object_name' => self::TEMPLATE_NAME,
            'object_type' => 'template',
        ]);
        $host->store($db);

        return $host;
    }

    private function attachProperties(IcingaHost $host, $db): void
    {
        $dba = $db->getDbAdapter();

        $stringProperty = DirectorProperty::create([
            'uuid'       => Uuid::uuid4()->getBytes(),
            'key_name'   => self::ENV_KEY,
            'value_type' => 'string',
            'label'      => 'Environment',
        ], $db);
        $stringProperty->store();

        $dictProperty = DirectorProperty::create([
            'uuid'       => Uuid::uuid4()->getBytes(),
            'key_name'   => self::MYSQL_KEY,
            'value_type' => 'fixed-dictionary',
            'label'      => 'MySQL settings',
        ], $db);
        $dictProperty->store();

        foreach ([$stringProperty, $dictProperty] as $property) {
            $dba->insert('icinga_host_property', [
                'property_uuid' => DbUtil::quoteBinaryCompat($property->get('uuid'), $dba),
                'host_uuid'     => DbUtil::quoteBinaryCompat($host->get('uuid'), $dba),
            ]);
        }
    }

    protected function tearDown(): void
    {
        if ($this->hasDb()) {
            $db = $this->getDb();
            $dba = $db->getDbAdapter();

            if (IcingaHost::exists(self::TEMPLATE_NAME, $db)) {
                $host = IcingaHost::load(self::TEMPLATE_NAME, $db);
                $dba->delete(
                    'icinga_host_property',
                    $dba->quoteInto(
                        'host_uuid = ?',
                        DbUtil::quoteBinaryCompat(DbUtil::binaryResult($host->get('uuid')), $dba)
                    )
                );
                $host->delete();
            }

            $dba->delete('director_property', $dba->quoteInto('key_name IN (?)', [self::ENV_KEY, self::MYSQL_KEY]));
        }

        parent::tearDown();
    }
}

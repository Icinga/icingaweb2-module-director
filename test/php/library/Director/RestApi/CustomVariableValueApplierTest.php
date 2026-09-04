<?php

namespace Tests\Icinga\Module\Director\RestApi;

use Icinga\Exception\NotFoundError;
use Icinga\Module\Director\Db\DbUtil;
use Icinga\Module\Director\Objects\DirectorProperty;
use Icinga\Module\Director\Objects\IcingaHost;
use Icinga\Module\Director\RestApi\CustomVarApplyRequest;
use Icinga\Module\Director\RestApi\CustomVariableValueApplier;
use Icinga\Module\Director\Test\BaseTestCase;
use InvalidArgumentException;
use Ramsey\Uuid\Uuid;
use Throwable;

class CustomVariableValueApplierTest extends BaseTestCase
{
    private const PREFIX = '___TEST___';
    private const TEMPLATE_NAME = self::PREFIX . 'applier-host';
    private const CONCRETE_HOST_NAME = self::PREFIX . 'applier-host-concrete';
    private const ENV_KEY = self::PREFIX . 'applier_env';
    private const MYSQL_KEY = self::PREFIX . 'applier_mysql';

    public function testNullValueForAnAlreadyReachablePropertyIsAQuietNoOp(): void
    {
        if ($this->skipForMissingDb()) {
            return;
        }

        $db = $this->getDb();
        $host = $this->createTemplate($db);
        $this->attachProperties($host, $db);

        (new CustomVariableValueApplier($db))->apply(new CustomVarApplyRequest(
            $host,
            [self::ENV_KEY => null],
            'variables',
            'POST',
            false
        ));

        $reloaded = IcingaHost::load(self::TEMPLATE_NAME, $db);
        $this->assertNull($reloaded->vars()->get(self::ENV_KEY));
    }

    public function testNullValueForAnUnknownKeyOnVariablesEndpointIsStillRejected(): void
    {
        if ($this->skipForMissingDb()) {
            return;
        }

        $db = $this->getDb();
        $host = $this->createTemplate($db);

        // a null value used to short circuit before the variables endpoint's
        // own "not configured" check ever ran, letting an unknown key through
        $this->expectException(NotFoundError::class);
        (new CustomVariableValueApplier($db))->apply(new CustomVarApplyRequest(
            $host,
            [self::PREFIX . 'never_set' => null],
            'variables',
            'PUT',
            false
        ));
    }

    public function testNullValueForARealButUnattachedPropertyStaysAQuietNoOp(): void
    {
        if ($this->skipForMissingDb()) {
            return;
        }

        $db = $this->getDb();
        $host = $this->createTemplate($db);

        // MYSQL_KEY is a real, configured property, just never attached to
        // this template, a null value must not attach it either
        DirectorProperty::create([
            'uuid'       => Uuid::uuid4()->getBytes(),
            'key_name'   => self::MYSQL_KEY,
            'value_type' => 'fixed-dictionary',
            'label'      => 'MySQL settings',
        ], $db)->store();

        (new CustomVariableValueApplier($db))->apply(new CustomVarApplyRequest(
            $host,
            [self::MYSQL_KEY => null],
            'variables',
            'PUT',
            false
        ));

        $host = IcingaHost::load(self::TEMPLATE_NAME, $db);
        $this->assertNull($host->vars()->get(self::MYSQL_KEY));

        $dba = $db->getDbAdapter();
        $count = $dba->fetchOne(
            $dba->select()
                ->from('icinga_host_property', ['COUNT(*)'])
                ->where(
                    'host_uuid = ?',
                    DbUtil::quoteBinaryCompat($host->get('uuid'), $dba)
                )
        );
        $this->assertEquals(0, (int) $count, 'a null value must not attach a property that was never attached');
    }

    public function testPutRollsBackTheFullWipeWhenAnUnknownNullKeyIsMixedIn(): void
    {
        if ($this->skipForMissingDb()) {
            return;
        }

        $db = $this->getDb();
        $host = $this->createTemplate($db);
        $this->attachProperties($host, $db);

        // resubmit both keys, a PUT drops anything left out, and the next PUT
        // below needs both attachments still there to prove they survive it
        (new CustomVariableValueApplier($db))->apply(new CustomVarApplyRequest(
            $host,
            [self::ENV_KEY => 'production', self::MYSQL_KEY => (object) ['host' => 'db-primary']],
            'variables',
            'PUT',
            false
        ));

        $host = IcingaHost::load(self::TEMPLATE_NAME, $db);

        // a PUT wipes every value and attachment up front, a null value for
        // the unknown key must not let that wipe slip through uncaught
        try {
            (new CustomVariableValueApplier($db))->apply(new CustomVarApplyRequest(
                $host,
                [
                    self::ENV_KEY => 'staging',
                    self::MYSQL_KEY => (object) ['host' => 'db-replica'],
                    self::PREFIX . 'never_set' => null,
                ],
                'variables',
                'PUT',
                false
            ));
            $this->fail('Expected a NotFoundError for the unknown key');
        } catch (NotFoundError $e) {
            // expected, checked below via a fresh load
        }

        $host = IcingaHost::load(self::TEMPLATE_NAME, $db);
        $this->assertEquals(
            'production',
            $host->vars()->get(self::ENV_KEY)->getValue(),
            'a rejected PUT must not leave the earlier wipe applied'
        );

        $dba = $db->getDbAdapter();
        $count = $dba->fetchOne(
            $dba->select()
                ->from('icinga_host_property', ['COUNT(*)'])
                ->where(
                    'host_uuid = ?',
                    DbUtil::quoteBinaryCompat($host->get('uuid'), $dba)
                )
        );
        $this->assertEquals(
            2,
            (int) $count,
            'a rejected PUT must not leave the property attachments wiped either'
        );
    }

    public function testFailedValidationRollsBackFullReplace(): void
    {
        if ($this->skipForMissingDb()) {
            return;
        }

        $db = $this->getDb();
        $host = $this->createTemplate($db);
        $this->attachProperties($host, $db);

        (new CustomVariableValueApplier($db))->apply(new CustomVarApplyRequest(
            $host,
            [self::ENV_KEY => 'production'],
            'variables',
            'PUT',
            false
        ));

        $host = IcingaHost::load(self::TEMPLATE_NAME, $db);
        $this->assertEquals('production', $host->vars()->get(self::ENV_KEY)->getValue());

        try {
            (new CustomVariableValueApplier($db))->apply(new CustomVarApplyRequest(
                $host,
                [self::MYSQL_KEY => ['not', 'a', 'dictionary']],
                'variables',
                'PUT',
                false
            ));
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

        (new CustomVariableValueApplier($db))->apply(new CustomVarApplyRequest(
            $host,
            [self::ENV_KEY => 'production'],
            'variables',
            'PUT',
            false
        ));

        $host = IcingaHost::load(self::TEMPLATE_NAME, $db);

        (new CustomVariableValueApplier($db))->apply(new CustomVarApplyRequest(
            $host,
            [self::MYSQL_KEY => (object) ['host' => 'db-primary']],
            'index',
            'POST',
            true
        ));

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

        (new CustomVariableValueApplier($db))->apply(new CustomVarApplyRequest(
            $host,
            [self::ENV_KEY => 'production'],
            'variables',
            'PUT',
            false
        ));

        $host = IcingaHost::load(self::TEMPLATE_NAME, $db);

        // This is the base endpoint equivalent of a POST body of {"vars": {}},
        // an explicit but empty full vars dictionary must still clear everything.
        (new CustomVariableValueApplier($db))->apply(new CustomVarApplyRequest(
            $host,
            [],
            'index',
            'POST',
            true
        ));

        $host = IcingaHost::load(self::TEMPLATE_NAME, $db);
        $this->assertNull(
            $host->vars()->get(self::ENV_KEY),
            'An explicit empty vars dictionary must clear existing variables, not no op'
        );
    }

    public function testApplyDoesNotCommitOrRollBackWhenAlreadyInsideATransaction(): void
    {
        if ($this->skipForMissingDb()) {
            return;
        }

        $db = $this->getDb();
        $host = $this->createTemplate($db);
        $this->attachProperties($host, $db);

        $dbAdapter = $db->getDbAdapter();
        $dbAdapter->beginTransaction();

        try {
            (new CustomVariableValueApplier($db))->apply(new CustomVarApplyRequest(
                $host,
                [self::ENV_KEY => 'production'],
                'variables',
                'PUT',
                false
            ));
        } catch (Throwable $e) {
            $dbAdapter->rollBack();
            $this->fail(
                'apply() must not manage its own transaction when the caller already opened one: '
                . get_class($e) . ': ' . $e->getMessage()
            );
        }

        // The write must be visible within the still-open outer transaction (same
        // connection, so this is a read of its own uncommitted write) - this proves
        // apply() actually performed the write rather than silently no-op'ing.
        $reloadedWithinTransaction = IcingaHost::load(self::TEMPLATE_NAME, $db);
        $this->assertEquals(
            'production',
            $reloadedWithinTransaction->vars()->get(self::ENV_KEY)->getValue(),
            'apply() must still perform its writes even when it does not own the transaction'
        );

        // Simulate a caller (e.g. IcingaObjectHandler) that persists other changes in the
        // same outer transaction and fails afterward - the whole thing must roll back together.
        $dbAdapter->rollBack();

        $host = IcingaHost::load(self::TEMPLATE_NAME, $db);
        $this->assertNull(
            $host->vars()->get(self::ENV_KEY),
            'apply() must not commit its own writes when called inside an existing transaction, '
            . 'so a failure later in the same caller-owned transaction can still undo everything'
        );
    }

    public function testBaseObjectPutKeepsPropertyAttachmentsIntact(): void
    {
        if ($this->skipForMissingDb()) {
            return;
        }

        $db = $this->getDb();
        $host = $this->createTemplate($db);
        // Attaches both ENV_KEY and MYSQL_KEY directly, without going through a
        // "variables" PUT first: that endpoint's own full-replace semantics would
        // otherwise already drop the attachment this test wants to see preserved.
        $this->attachProperties($host, $db);

        // A PUT on the base object endpoint (actionName 'index', not 'variables') that
        // happens to carry a partial vars map must replace values, not drop the
        // property attachments set up above.
        (new CustomVariableValueApplier($db))->apply(new CustomVarApplyRequest(
            $host,
            [self::ENV_KEY => 'staging'],
            'index',
            'PUT',
            false
        ));

        $host = IcingaHost::load(self::TEMPLATE_NAME, $db);
        $this->assertEquals('staging', $host->vars()->get(self::ENV_KEY)->getValue());

        $dba = $db->getDbAdapter();
        $count = $dba->fetchOne(
            $dba->select()
                ->from('icinga_host_property', ['COUNT(*)'])
                ->where(
                    'host_uuid = ?',
                    DbUtil::quoteBinaryCompat($host->get('uuid'), $dba)
                )
        );
        $this->assertEquals(
            2,
            (int) $count,
            'A base-object PUT must not remove the property attachments set up before it'
        );
    }

    public function testVariablesPutPreservesRequiredFlagOnReattachment(): void
    {
        if ($this->skipForMissingDb()) {
            return;
        }

        $db = $this->getDb();
        $host = $this->createTemplate($db);
        $this->attachProperties($host, $db, [self::ENV_KEY]);

        $this->assertEquals(
            'y',
            $this->fetchRequiredFlag($host, $db, self::ENV_KEY),
            'attachProperties() must have set up the required flag this test relies on'
        );

        // A PUT on the "variables" endpoint wipes and recreates every direct property
        // attachment; a still-present property must not lose its required flag.
        (new CustomVariableValueApplier($db))->apply(new CustomVarApplyRequest(
            $host,
            [self::ENV_KEY => 'production', self::MYSQL_KEY => (object) ['host' => 'db-primary']],
            'variables',
            'PUT',
            false
        ));

        $host = IcingaHost::load(self::TEMPLATE_NAME, $db);
        $this->assertEquals(
            'y',
            $this->fetchRequiredFlag($host, $db, self::ENV_KEY),
            'A PUT that replaces values must not clear the required flag of a still-present property'
        );
        $this->assertEquals(
            'n',
            $this->fetchRequiredFlag($host, $db, self::MYSQL_KEY),
            'A property that was never required must not become required as a side effect'
        );
    }

    public function testConcreteObjectPutReplacesADirectlyAttachedProperty(): void
    {
        if ($this->skipForMissingDb()) {
            return;
        }

        $db = $this->getDb();
        $host = $this->createConcreteHost($db);
        $this->attachProperties($host, $db);

        // both attached directly, a concrete PUT replaces values only, never attachments
        (new CustomVariableValueApplier($db))->apply(new CustomVarApplyRequest(
            $host,
            [self::ENV_KEY => 'production'],
            'variables',
            'PUT',
            false
        ));

        $host = IcingaHost::load(self::CONCRETE_HOST_NAME, $db);
        $this->assertEquals('production', $host->vars()->get(self::ENV_KEY)->getValue());
        $this->assertNull(
            $host->vars()->get(self::MYSQL_KEY),
            'a value not resubmitted in a PUT must still be cleared'
        );

        $dba = $db->getDbAdapter();
        $count = $dba->fetchOne(
            $dba->select()
                ->from('icinga_host_property', ['COUNT(*)'])
                ->where(
                    'host_uuid = ?',
                    DbUtil::quoteBinaryCompat($host->get('uuid'), $dba)
                )
        );
        $this->assertEquals(
            2,
            (int) $count,
            'a concrete object PUT must never detach a property left out of the request body'
        );
    }

    public function testConcreteObjectPutStillRejectsABrandNewAttachment(): void
    {
        if ($this->skipForMissingDb()) {
            return;
        }

        $db = $this->getDb();
        $host = $this->createConcreteHost($db);

        // MYSQL_KEY is registered but never attached to this host at all, a
        // concrete object still cannot create a brand new attachment via PUT.
        DirectorProperty::create([
            'uuid'       => Uuid::uuid4()->getBytes(),
            'key_name'   => self::MYSQL_KEY,
            'value_type' => 'fixed-dictionary',
            'label'      => 'MySQL settings',
        ], $db)->store();

        $this->expectException(NotFoundError::class);
        (new CustomVariableValueApplier($db))->apply(new CustomVarApplyRequest(
            $host,
            [self::MYSQL_KEY => (object) ['host' => 'db-primary']],
            'variables',
            'PUT',
            false
        ));
    }

    public function testDetachingATemplatePropertyClearsAnImportingHostsLocalOverride(): void
    {
        if ($this->skipForMissingDb()) {
            return;
        }

        $db = $this->getDb();
        $template = $this->createTemplate($db);
        $this->attachProperties($template, $db);

        $concreteHost = $this->createConcreteHost($db);
        $concreteHost->setImports(self::TEMPLATE_NAME);
        $concreteHost->store($db);
        $concreteHost = IcingaHost::load(self::CONCRETE_HOST_NAME, $db);

        // the host inherited ENV_KEY from the template, then saved its own
        // value for it instead of just using the inherited one
        (new CustomVariableValueApplier($db))->apply(new CustomVarApplyRequest(
            $concreteHost,
            [self::ENV_KEY => 'staging'],
            'index',
            'PUT',
            false
        ));

        $concreteHost = IcingaHost::load(self::CONCRETE_HOST_NAME, $db);
        $this->assertEquals('staging', $concreteHost->vars()->get(self::ENV_KEY)->getValue());

        // now detach ENV_KEY from the template, leaving only MYSQL_KEY in place
        (new CustomVariableValueApplier($db))->apply(new CustomVarApplyRequest(
            $template,
            [self::MYSQL_KEY => (object) ['host' => 'db-primary']],
            'variables',
            'PUT',
            false
        ));

        $concreteHost = IcingaHost::load(self::CONCRETE_HOST_NAME, $db);
        $this->assertNull(
            $concreteHost->vars()->get(self::ENV_KEY),
            'a value saved locally for a property that just got detached from the '
            . 'template it came from must not keep sitting around on the importing host'
        );

        $dba = $db->getDbAdapter();
        $latestEntry = $dba->fetchRow(
            $dba->select()
                ->from('director_activity_log', ['new_properties'])
                ->where('object_name = ?', self::CONCRETE_HOST_NAME)
                ->order('id DESC')
                ->limit(1)
        );
        $this->assertNotNull(
            $latestEntry,
            'clearing a stale value on an importing host must leave a trace in the activity log'
        );
        $this->assertStringNotContainsString(
            self::ENV_KEY,
            $latestEntry->new_properties,
            'the activity log entry for the importing host must show the value actually gone'
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

    private function createConcreteHost($db): IcingaHost
    {
        if (IcingaHost::exists(self::CONCRETE_HOST_NAME, $db)) {
            IcingaHost::load(self::CONCRETE_HOST_NAME, $db)->delete();
        }

        $host = IcingaHost::create([
            'object_name' => self::CONCRETE_HOST_NAME,
            'object_type' => 'object',
        ]);
        $host->store($db);

        return $host;
    }

    private function attachProperties(IcingaHost $host, $db, array $requiredKeys = []): void
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

        foreach ([self::ENV_KEY => $stringProperty, self::MYSQL_KEY => $dictProperty] as $key => $property) {
            $dba->insert('icinga_host_property', [
                'property_uuid' => DbUtil::quoteBinaryCompat($property->get('uuid'), $dba),
                'host_uuid'     => DbUtil::quoteBinaryCompat($host->get('uuid'), $dba),
                'required'      => in_array($key, $requiredKeys, true) ? 'y' : 'n',
            ]);
        }
    }

    private function fetchRequiredFlag(IcingaHost $host, $db, string $key): string
    {
        $dba = $db->getDbAdapter();

        return $dba->fetchOne(
            $dba->select()
                ->from(['iop' => 'icinga_host_property'], ['required'])
                ->join(['dp' => 'director_property'], 'dp.uuid = iop.property_uuid', [])
                ->where('dp.key_name = ?', $key)
                ->where(
                    'iop.host_uuid = ?',
                    DbUtil::quoteBinaryCompat($host->get('uuid'), $dba)
                )
        );
    }

    protected function tearDown(): void
    {
        if ($this->hasDb()) {
            $db = $this->getDb();
            $dba = $db->getDbAdapter();

            // the concrete host may import the template, so it has to go first
            // or the template's own delete gets rejected as still in use
            foreach ([self::CONCRETE_HOST_NAME, self::TEMPLATE_NAME] as $hostName) {
                if (IcingaHost::exists($hostName, $db)) {
                    $host = IcingaHost::load($hostName, $db);
                    $dba->delete(
                        'icinga_host_property',
                        $dba->quoteInto(
                            'host_uuid = ?',
                            DbUtil::quoteBinaryCompat(DbUtil::binaryResult($host->get('uuid')), $dba)
                        )
                    );
                    $host->delete();
                }
            }

            $dba->delete('director_property', $dba->quoteInto('key_name IN (?)', [self::ENV_KEY, self::MYSQL_KEY]));
        }

        parent::tearDown();
    }
}

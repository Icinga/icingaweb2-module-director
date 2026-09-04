<?php

namespace Tests\Icinga\Module\Director\CustomVariable;

use Icinga\Module\Director\CustomVariable\PropertyDetachmentCleaner;
use Icinga\Module\Director\Db\DbUtil;
use Icinga\Module\Director\Objects\DirectorProperty;
use Icinga\Module\Director\Objects\IcingaHost;
use Icinga\Module\Director\Objects\IcingaService;
use Icinga\Module\Director\RestApi\CustomVarApplyRequest;
use Icinga\Module\Director\RestApi\CustomVariableValueApplier;
use Icinga\Module\Director\Test\BaseTestCase;
use Ramsey\Uuid\Uuid;

class PropertyDetachmentCleanerTest extends BaseTestCase
{
    private const PREFIX = '___TEST___';
    private const TEMPLATE_ONE_NAME = self::PREFIX . 'import-cleanup-network';
    private const TEMPLATE_TWO_NAME = self::PREFIX . 'import-cleanup-storage';
    private const MID_TEMPLATE_NAME = self::PREFIX . 'import-cleanup-datacenter';
    private const LEAF_HOST_NAME = self::PREFIX . 'import-cleanup-webserver';
    private const REGION_KEY = self::PREFIX . 'import_cleanup_region';
    private const SERVICE_TEMPLATE_ONE_NAME = self::PREFIX . 'import-cleanup-svc-network';
    private const SERVICE_TEMPLATE_TWO_NAME = self::PREFIX . 'import-cleanup-svc-storage';
    private const SERVICE_LEAF_NAME = self::PREFIX . 'import-cleanup-svc-leaf';

    public function testValueSurvivesWhenAnotherRemainingImportStillProvidesTheProperty(): void
    {
        if ($this->skipForMissingDb()) {
            return;
        }

        $db = $this->getDb();
        $property = $this->createProperty($db);
        $templateOne = $this->createTemplate(self::TEMPLATE_ONE_NAME, $db);
        $templateTwo = $this->createTemplate(self::TEMPLATE_TWO_NAME, $db);
        $this->attachProperty($property, $templateOne, $db);
        $this->attachProperty($property, $templateTwo, $db);

        $leaf = $this->createLeafHost($db);
        $leaf->setImports([self::TEMPLATE_ONE_NAME, self::TEMPLATE_TWO_NAME]);
        $leaf->store($db);
        $leaf = IcingaHost::load(self::LEAF_HOST_NAME, $db);

        (new CustomVariableValueApplier($db))->apply(new CustomVarApplyRequest(
            $leaf,
            [self::REGION_KEY => 'eu-west'],
            'index',
            'POST',
            false
        ));

        $leaf->setImports([self::TEMPLATE_TWO_NAME]);
        $leaf->store($db);

        PropertyDetachmentCleaner::removeValuesLostToRemovedImports($leaf, [self::TEMPLATE_ONE_NAME], $db);

        $leaf = IcingaHost::load(self::LEAF_HOST_NAME, $db);
        $this->assertEquals(
            'eu-west',
            $leaf->vars()->get(self::REGION_KEY)->getValue(),
            'a property still reachable through the other remaining import must keep its local value'
        );
    }

    /**
     * IcingaService uses a composite key, so looking up an import by name goes
     * through a different branch than the plain single-key IcingaHost case
     * every other test here uses.
     */
    public function testCompositeKeyServiceTemplateSurvivesViaOtherImport(): void
    {
        if ($this->skipForMissingDb()) {
            return;
        }

        $db = $this->getDb();
        $property = $this->createProperty($db);
        $templateOne = $this->createServiceTemplate(self::SERVICE_TEMPLATE_ONE_NAME, $db);
        $templateTwo = $this->createServiceTemplate(self::SERVICE_TEMPLATE_TWO_NAME, $db);
        $this->attachServiceProperty($property, $templateOne, $db);
        $this->attachServiceProperty($property, $templateTwo, $db);

        $leaf = $this->createServiceTemplate(self::SERVICE_LEAF_NAME, $db);
        $leaf->setImports([self::SERVICE_TEMPLATE_ONE_NAME, self::SERVICE_TEMPLATE_TWO_NAME]);
        $leaf->store($db);
        $leaf = $this->loadServiceTemplate(self::SERVICE_LEAF_NAME, $db);

        (new CustomVariableValueApplier($db))->apply(new CustomVarApplyRequest(
            $leaf,
            [self::REGION_KEY => 'eu-west'],
            'index',
            'POST',
            false
        ));

        $leaf->setImports([self::SERVICE_TEMPLATE_TWO_NAME]);
        $leaf->store($db);

        PropertyDetachmentCleaner::removeValuesLostToRemovedImports($leaf, [self::SERVICE_TEMPLATE_ONE_NAME], $db);

        $leaf = $this->loadServiceTemplate(self::SERVICE_LEAF_NAME, $db);
        $this->assertEquals(
            'eu-west',
            $leaf->vars()->get(self::REGION_KEY)->getValue(),
            'a service template must keep a value still reachable through another imported template'
        );
    }

    public function testValueIsRemovedAndLoggedWhenItsOnlyProviderIsRemoved(): void
    {
        if ($this->skipForMissingDb()) {
            return;
        }

        $db = $this->getDb();
        $property = $this->createProperty($db);
        $templateOne = $this->createTemplate(self::TEMPLATE_ONE_NAME, $db);
        $this->attachProperty($property, $templateOne, $db);

        $leaf = $this->createLeafHost($db);
        $leaf->setImports([self::TEMPLATE_ONE_NAME]);
        $leaf->store($db);
        $leaf = IcingaHost::load(self::LEAF_HOST_NAME, $db);

        (new CustomVariableValueApplier($db))->apply(new CustomVarApplyRequest(
            $leaf,
            [self::REGION_KEY => 'eu-west'],
            'index',
            'POST',
            false
        ));

        $leaf->setImports([]);
        $leaf->store($db);

        PropertyDetachmentCleaner::removeValuesLostToRemovedImports($leaf, [self::TEMPLATE_ONE_NAME], $db);

        $leaf = IcingaHost::load(self::LEAF_HOST_NAME, $db);
        $this->assertNull(
            $leaf->vars()->get(self::REGION_KEY),
            'a value that only made sense through the removed import must not stick around'
        );

        $dba = $db->getDbAdapter();
        $latestEntry = $dba->fetchRow(
            $dba->select()
                ->from('director_activity_log', ['new_properties'])
                ->where('object_name = ?', self::LEAF_HOST_NAME)
                ->order('id DESC')
                ->limit(1)
        );
        $this->assertNotNull(
            $latestEntry,
            'losing a value to a removed import must leave a trace in the activity log'
        );
        $this->assertStringNotContainsString(self::REGION_KEY, $latestEntry->new_properties);
    }

    public function testValueOnADescendantIsRemovedWhenAMiddleTemplateDropsTheImport(): void
    {
        if ($this->skipForMissingDb()) {
            return;
        }

        $db = $this->getDb();
        $property = $this->createProperty($db);
        $templateOne = $this->createTemplate(self::TEMPLATE_ONE_NAME, $db);
        $this->attachProperty($property, $templateOne, $db);

        $mid = $this->createTemplate(self::MID_TEMPLATE_NAME, $db);
        $mid->setImports([self::TEMPLATE_ONE_NAME]);
        $mid->store($db);

        $leaf = $this->createLeafHost($db);
        $leaf->setImports([self::MID_TEMPLATE_NAME]);
        $leaf->store($db);
        $leaf = IcingaHost::load(self::LEAF_HOST_NAME, $db);

        // the leaf host inherits REGION_KEY through mid, then saves its own
        // value for it instead of just using the inherited one
        (new CustomVariableValueApplier($db))->apply(new CustomVarApplyRequest(
            $leaf,
            [self::REGION_KEY => 'eu-west'],
            'index',
            'POST',
            false
        ));

        $mid = IcingaHost::load(self::MID_TEMPLATE_NAME, $db);
        $mid->setImports([]);
        $mid->store($db);

        PropertyDetachmentCleaner::removeValuesLostToRemovedImports($mid, [self::TEMPLATE_ONE_NAME], $db);

        $leaf = IcingaHost::load(self::LEAF_HOST_NAME, $db);
        $this->assertNull(
            $leaf->vars()->get(self::REGION_KEY),
            'a descendant that inherited the property through the middle template must lose its override too'
        );
    }

    public function testPreviewFlagsAValueThatWouldLoseItsOnlyProvider(): void
    {
        if ($this->skipForMissingDb()) {
            return;
        }

        $db = $this->getDb();
        $property = $this->createProperty($db);
        $templateOne = $this->createTemplate(self::TEMPLATE_ONE_NAME, $db);
        $this->attachProperty($property, $templateOne, $db);

        $leaf = $this->createLeafHost($db);
        $leaf->setImports([self::TEMPLATE_ONE_NAME]);
        $leaf->store($db);
        $leaf = IcingaHost::load(self::LEAF_HOST_NAME, $db);

        (new CustomVariableValueApplier($db))->apply(new CustomVarApplyRequest(
            $leaf,
            [self::REGION_KEY => 'eu-west'],
            'index',
            'POST',
            false
        ));
        $leaf = IcingaHost::load(self::LEAF_HOST_NAME, $db);

        $atRisk = PropertyDetachmentCleaner::previewCustomVarsLostIfImportsRemoved($leaf, []);

        $this->assertEquals(
            [self::REGION_KEY],
            $atRisk,
            'dropping the only import providing a property must flag its value as at risk'
        );
    }

    public function testPreviewStaysEmptyWhenAnotherPendingImportStillProvidesTheProperty(): void
    {
        if ($this->skipForMissingDb()) {
            return;
        }

        $db = $this->getDb();
        $property = $this->createProperty($db);
        $templateOne = $this->createTemplate(self::TEMPLATE_ONE_NAME, $db);
        $templateTwo = $this->createTemplate(self::TEMPLATE_TWO_NAME, $db);
        $this->attachProperty($property, $templateOne, $db);
        $this->attachProperty($property, $templateTwo, $db);

        $leaf = $this->createLeafHost($db);
        $leaf->setImports([self::TEMPLATE_ONE_NAME, self::TEMPLATE_TWO_NAME]);
        $leaf->store($db);
        $leaf = IcingaHost::load(self::LEAF_HOST_NAME, $db);

        (new CustomVariableValueApplier($db))->apply(new CustomVarApplyRequest(
            $leaf,
            [self::REGION_KEY => 'eu-west'],
            'index',
            'POST',
            false
        ));
        $leaf = IcingaHost::load(self::LEAF_HOST_NAME, $db);

        $atRisk = PropertyDetachmentCleaner::previewCustomVarsLostIfImportsRemoved(
            $leaf,
            [self::TEMPLATE_TWO_NAME]
        );

        $this->assertEquals(
            [],
            $atRisk,
            'a property still reachable through another pending import must not be flagged'
        );
    }

    /**
     * A host that was never stored has nothing to compare imports against, the
     * preview must return empty right away instead of querying anything.
     */
    public function testPreviewStaysEmptyForAHostThatWasNeverStored(): void
    {
        if ($this->skipForMissingDb()) {
            return;
        }

        $host = IcingaHost::create([
            'object_name' => self::LEAF_HOST_NAME,
            'object_type' => 'object',
        ]);

        $atRisk = PropertyDetachmentCleaner::previewCustomVarsLostIfImportsRemoved($host, []);

        $this->assertEquals([], $atRisk);
    }

    /**
     * A host can import two templates that both attach the same property. Removing
     * the attachment from just one of them must not touch the host's value, the
     * other template still provides it.
     */
    public function testDirectDetachSurvivesViaOtherTemplate(): void
    {
        if ($this->skipForMissingDb()) {
            return;
        }

        $db = $this->getDb();
        $property = $this->createProperty($db);
        $templateOne = $this->createTemplate(self::TEMPLATE_ONE_NAME, $db);
        $templateTwo = $this->createTemplate(self::TEMPLATE_TWO_NAME, $db);
        $this->attachProperty($property, $templateOne, $db);
        $this->attachProperty($property, $templateTwo, $db);

        $leaf = $this->createLeafHost($db);
        $leaf->setImports([self::TEMPLATE_ONE_NAME, self::TEMPLATE_TWO_NAME]);
        $leaf->store($db);
        $leaf = IcingaHost::load(self::LEAF_HOST_NAME, $db);

        (new CustomVariableValueApplier($db))->apply(new CustomVarApplyRequest(
            $leaf,
            [self::REGION_KEY => 'eu-west'],
            'index',
            'POST',
            false
        ));

        // same order the real callers use, delete the attachment first, then let
        // the cleaner work out what's still reachable some other way
        $this->detachProperty($property, $templateOne, $db);
        PropertyDetachmentCleaner::removeStaleValues(
            $templateOne,
            [DbUtil::quoteBinaryCompat($property->get('uuid'), $db->getDbAdapter())],
            $db
        );

        $leaf = IcingaHost::load(self::LEAF_HOST_NAME, $db);
        $this->assertEquals(
            'eu-west',
            $leaf->vars()->get(self::REGION_KEY)->getValue(),
            'a value must survive a detach from one template when another imported template still provides it'
        );
    }

    /**
     * When the detached template was the only one providing the property, the
     * value still has to go.
     */
    public function testDirectDetachRemovesLastProvider(): void
    {
        if ($this->skipForMissingDb()) {
            return;
        }

        $db = $this->getDb();
        $property = $this->createProperty($db);
        $templateOne = $this->createTemplate(self::TEMPLATE_ONE_NAME, $db);
        $this->attachProperty($property, $templateOne, $db);

        $leaf = $this->createLeafHost($db);
        $leaf->setImports([self::TEMPLATE_ONE_NAME]);
        $leaf->store($db);
        $leaf = IcingaHost::load(self::LEAF_HOST_NAME, $db);

        (new CustomVariableValueApplier($db))->apply(new CustomVarApplyRequest(
            $leaf,
            [self::REGION_KEY => 'eu-west'],
            'index',
            'POST',
            false
        ));

        $this->detachProperty($property, $templateOne, $db);
        PropertyDetachmentCleaner::removeStaleValues(
            $templateOne,
            [DbUtil::quoteBinaryCompat($property->get('uuid'), $db->getDbAdapter())],
            $db
        );

        $leaf = IcingaHost::load(self::LEAF_HOST_NAME, $db);
        $this->assertNull(
            $leaf->vars()->get(self::REGION_KEY),
            'a value must be removed once its only providing template loses the property'
        );
    }

    /**
     * A template losing its own direct attachment can still reach the property
     * through one of its own imports, its value must survive too, same as it
     * would for a descendant.
     */
    public function testDirectDetachSurvivesViaOwnImport(): void
    {
        if ($this->skipForMissingDb()) {
            return;
        }

        $db = $this->getDb();
        $property = $this->createProperty($db);
        $templateTwo = $this->createTemplate(self::TEMPLATE_TWO_NAME, $db);
        $templateOne = $this->createTemplate(self::TEMPLATE_ONE_NAME, $db);
        $this->attachProperty($property, $templateOne, $db);
        $this->attachProperty($property, $templateTwo, $db);

        $templateOne->setImports([self::TEMPLATE_TWO_NAME]);
        $templateOne->store($db);
        $templateOne = IcingaHost::load(self::TEMPLATE_ONE_NAME, $db);

        (new CustomVariableValueApplier($db))->apply(new CustomVarApplyRequest(
            $templateOne,
            [self::REGION_KEY => 'eu-west'],
            'index',
            'POST',
            false
        ));

        $this->detachProperty($property, $templateOne, $db);
        PropertyDetachmentCleaner::removeStaleValues(
            $templateOne,
            [DbUtil::quoteBinaryCompat($property->get('uuid'), $db->getDbAdapter())],
            $db
        );

        $templateOne = IcingaHost::load(self::TEMPLATE_ONE_NAME, $db);
        $this->assertEquals(
            'eu-west',
            $templateOne->vars()->get(self::REGION_KEY)->getValue(),
            'losing its own direct attachment must not wipe the value if it still imports another'
            . ' template providing the same property'
        );
    }

    private function createProperty($db): DirectorProperty
    {
        $property = DirectorProperty::create([
            'uuid'       => Uuid::uuid4()->getBytes(),
            'key_name'   => self::REGION_KEY,
            'value_type' => 'string',
            'label'      => 'Region',
        ], $db);
        $property->store();

        return $property;
    }

    private function createTemplate(string $name, $db): IcingaHost
    {
        if (IcingaHost::exists($name, $db)) {
            IcingaHost::load($name, $db)->delete();
        }

        $host = IcingaHost::create([
            'object_name' => $name,
            'object_type' => 'template',
        ]);
        $host->store($db);

        return $host;
    }

    private function createLeafHost($db): IcingaHost
    {
        if (IcingaHost::exists(self::LEAF_HOST_NAME, $db)) {
            IcingaHost::load(self::LEAF_HOST_NAME, $db)->delete();
        }

        $host = IcingaHost::create([
            'object_name' => self::LEAF_HOST_NAME,
            'object_type' => 'object',
        ]);
        $host->store($db);

        return $host;
    }

    private function attachProperty(DirectorProperty $property, IcingaHost $template, $db): void
    {
        $dba = $db->getDbAdapter();
        $dba->insert('icinga_host_property', [
            'property_uuid' => DbUtil::quoteBinaryCompat($property->get('uuid'), $dba),
            'host_uuid'     => DbUtil::quoteBinaryCompat($template->get('uuid'), $dba),
            'required'      => 'n',
        ]);
    }

    private function detachProperty(DirectorProperty $property, IcingaHost $template, $db): void
    {
        $dba = $db->getDbAdapter();
        $dba->delete(
            'icinga_host_property',
            $dba->quoteInto('property_uuid = ?', DbUtil::quoteBinaryCompat($property->get('uuid'), $dba))
            . ' AND ' . $dba->quoteInto('host_uuid = ?', DbUtil::quoteBinaryCompat($template->get('uuid'), $dba))
        );
    }

    private function createServiceTemplate(string $name, $db): IcingaService
    {
        if ($this->serviceTemplateExists($name, $db)) {
            $this->loadServiceTemplate($name, $db)->delete();
        }

        $service = IcingaService::create([
            'object_name' => $name,
            'object_type' => 'template',
        ]);
        $service->store($db);

        return $service;
    }

    private function loadServiceTemplate(string $name, $db): IcingaService
    {
        return IcingaService::load(['object_name' => $name, 'object_type' => 'template'], $db);
    }

    private function serviceTemplateExists(string $name, $db): bool
    {
        return IcingaService::exists(['object_name' => $name, 'object_type' => 'template'], $db);
    }

    private function attachServiceProperty(DirectorProperty $property, IcingaService $template, $db): void
    {
        $dba = $db->getDbAdapter();
        $dba->insert('icinga_service_property', [
            'property_uuid' => DbUtil::quoteBinaryCompat($property->get('uuid'), $dba),
            'service_uuid'  => DbUtil::quoteBinaryCompat($template->get('uuid'), $dba),
            'required'      => 'n',
        ]);
    }

    protected function tearDown(): void
    {
        if ($this->hasDb()) {
            $db = $this->getDb();
            $dba = $db->getDbAdapter();

            // leaf imports mid, mid imports template one, so they have to go
            // in that order or a delete gets rejected as still in use
            $names = [
                self::LEAF_HOST_NAME,
                self::MID_TEMPLATE_NAME,
                self::TEMPLATE_ONE_NAME,
                self::TEMPLATE_TWO_NAME,
            ];

            foreach ($names as $hostName) {
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

            // leaf imports both, so it has to go first
            $serviceNames = [
                self::SERVICE_LEAF_NAME,
                self::SERVICE_TEMPLATE_ONE_NAME,
                self::SERVICE_TEMPLATE_TWO_NAME,
            ];

            foreach ($serviceNames as $serviceName) {
                if ($this->serviceTemplateExists($serviceName, $db)) {
                    $service = $this->loadServiceTemplate($serviceName, $db);
                    $dba->delete(
                        'icinga_service_property',
                        $dba->quoteInto(
                            'service_uuid = ?',
                            DbUtil::quoteBinaryCompat(DbUtil::binaryResult($service->get('uuid')), $dba)
                        )
                    );
                    $service->delete();
                }
            }

            $dba->delete('director_property', $dba->quoteInto('key_name = ?', self::REGION_KEY));
        }

        parent::tearDown();
    }
}

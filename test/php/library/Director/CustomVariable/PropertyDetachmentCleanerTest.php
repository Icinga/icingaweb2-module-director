<?php

namespace Tests\Icinga\Module\Director\CustomVariable;

use Icinga\Module\Director\CustomVariable\PropertyDetachmentCleaner;
use Icinga\Module\Director\Db\DbUtil;
use Icinga\Module\Director\Objects\DirectorProperty;
use Icinga\Module\Director\Objects\IcingaHost;
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

        $atRisk = PropertyDetachmentCleaner::previewValueNamesAtRiskIfImportsBecome($leaf, []);

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

        $atRisk = PropertyDetachmentCleaner::previewValueNamesAtRiskIfImportsBecome(
            $leaf,
            [self::TEMPLATE_TWO_NAME]
        );

        $this->assertEquals(
            [],
            $atRisk,
            'a property still reachable through another pending import must not be flagged'
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

            $dba->delete('director_property', $dba->quoteInto('key_name = ?', self::REGION_KEY));
        }

        parent::tearDown();
    }
}

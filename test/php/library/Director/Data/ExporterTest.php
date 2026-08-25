<?php

namespace Tests\Icinga\Module\Director\Data;

use Icinga\Module\Director\Data\Exporter;
use Icinga\Module\Director\Objects\IcingaHost;
use Icinga\Module\Director\Test\BaseTestCase;

class ExporterTest extends BaseTestCase
{
    private const PREFIX = '___TEST___';
    private const HOST_NAME = self::PREFIX . 'exporter-properties-filter';

    public function testFilteredPropertiesDropUuidUnlessAskedFor(): void
    {
        if ($this->skipForMissingDb()) {
            return;
        }

        $db = $this->getDb();
        $host = $this->createHost($db);

        $plain = (new Exporter($db))->filterProperties(['vars'])->export($host);

        $this->assertTrue(property_exists($plain, 'vars'), 'the requested vars property must be there');
        $this->assertFalse(property_exists($plain, 'uuid'), 'uuid was not requested, it should not show up');
    }

    public function testFilteredPropertiesKeepUuidWhenAskedFor(): void
    {
        if ($this->skipForMissingDb()) {
            return;
        }

        $db = $this->getDb();
        $host = $this->createHost($db);

        $plain = (new Exporter($db))->filterProperties(['object_name', 'uuid'])->export($host);

        $this->assertTrue(property_exists($plain, 'object_name'));
        $this->assertTrue(property_exists($plain, 'uuid'), 'uuid was explicitly requested, it must be there');
    }

    public function testUnfilteredExportStillKeepsUuid(): void
    {
        if ($this->skipForMissingDb()) {
            return;
        }

        $db = $this->getDb();
        $host = $this->createHost($db);

        $plain = (new Exporter($db))->export($host);

        $this->assertTrue(property_exists($plain, 'uuid'), 'no filter was set, uuid must stay in the default export');
    }

    private function createHost($db): IcingaHost
    {
        if (IcingaHost::exists(self::HOST_NAME, $db)) {
            IcingaHost::load(self::HOST_NAME, $db)->delete();
        }

        $host = IcingaHost::create([
            'object_name' => self::HOST_NAME,
            'object_type' => 'object',
            'address'     => '127.0.0.1',
        ]);
        $host->store($db);
        $host->vars()->set(self::PREFIX . 'region', 'eu-west');
        $host->store($db);

        return IcingaHost::load(self::HOST_NAME, $db);
    }

    protected function tearDown(): void
    {
        if ($this->hasDb()) {
            $db = $this->getDb();
            if (IcingaHost::exists(self::HOST_NAME, $db)) {
                IcingaHost::load(self::HOST_NAME, $db)->delete();
            }
        }

        parent::tearDown();
    }
}

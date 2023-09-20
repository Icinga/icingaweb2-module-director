<?php

namespace Tests\Icinga\Module\Director\Objects;

use Icinga\Module\Director\Db;
use Icinga\Module\Director\Objects\IcingaHost;
use Icinga\Module\Director\Objects\IcingaService;
use Icinga\Module\Director\Resolver\TemplateTree;
use Icinga\Module\Director\Test\BaseTestCase;

class TemplateTreeTest extends BaseTestCase
{
    protected $applyId;

    protected function prepareHosts(Db $db)
    {
        $o1 = IcingaHost::create([
            'object_name' => 'o1',
            'object_type' => 'template'
        ], $db);
        $o2 = IcingaHost::create([
            'object_name' => 'o2',
            'object_type' => 'template'
        ], $db);
        $o3 = IcingaHost::create([
            'object_name' => 'o3',
            'object_type' => 'template'
        ], $db);
        $o4 = IcingaHost::create([
            'object_name' => 'o4',
            'object_type' => 'template',
            'imports'     => ['o2', 'o1'],
        ], $db);
        $o5 = IcingaHost::create([
            'object_name' => 'o5',
            'object_type' => 'template',
            'imports'     => ['o4'],
        ], $db);
        $o6 = IcingaHost::create([
            'object_name' => 'o6',
            'object_type' => 'template',
            'imports'     => ['o4', 'o2'],
        ], $db);
        $o7 = IcingaHost::create([
            'object_name' => 'o7',
            'object_type' => 'object',
            'imports'     => ['o4', 'o2'],
        ], $db);

        $o1->store();
        $o2->store();
        $o3->store();
        $o4->store();
        $o5->store();
        $o6->store();
        $o7->store();

        return (object) [
            'o1' => $o1,
            'o2' => $o2,
            'o3' => $o3,
            'o4' => $o4,
            'o5' => $o5,
            'o6' => $o6,
            'o7' => $o7,
        ];
    }

    public function testHostWithoutParentGivesAnEmptyArray()
    {
        $db = $this->getDb();
        $hosts = $this->prepareHosts($db);
        $tree = new TemplateTree('host', $db);
        $this->assertEquals([], $tree->getParentsFor($hosts->o2));
        $this->assertEquals([], $tree->getAncestorsFor($hosts->o2));
        $this->assertEquals([], $tree->listAncestorIdsFor($hosts->o2));
    }

    public function testSimpleInheritanceWithMultipleParentsGivesOrderedResult()
    {
        $db = $this->getDb();
        $hosts = $this->prepareHosts($db);
        $tree = new TemplateTree('host', $db);
        $this->assertArrayEqualsWithKeys([
            $hosts->o2->id => 'o2',
            $hosts->o1->id => 'o1',
        ], $tree->getParentsFor($hosts->o4));
        $this->assertArrayEqualsWithKeys([
            (int) $hosts->o2->id,
            (int) $hosts->o1->id,
        ], $tree->listParentIdsFor($hosts->o4));
    }

    public function testMultiInheritanceIsResolved()
    {
        $db = $this->getDb();
        $hosts = $this->prepareHosts($db);
        $tree = new TemplateTree('host', $db);
        $this->assertArrayEqualsWithKeys([
            $hosts->o2->id => 'o2',
            $hosts->o1->id => 'o1',
            $hosts->o4->id => 'o4'
        ], $tree->getAncestorsFor($hosts->o5));
        $this->assertArrayEqualsWithKeys([
            (int) $hosts->o2->get('id'),
            (int) $hosts->o1->getProperty('id'),
            $hosts->o4->getAutoincId(),
        ], $tree->listAncestorIdsFor($hosts->o5));
    }

    public function testTemplateOrderIsCorrectWhenInheritingSameTemplateMultipleTimes()
    {
        $db = $this->getDb();
        $hosts = $this->prepareHosts($db);
        $tree = new TemplateTree('host', $db);
        $this->assertArrayEqualsWithKeys([
            $hosts->o1->id => 'o1',
            $hosts->o4->id => 'o4',
            $hosts->o2->id => 'o2'
        ], $tree->getAncestorsFor($hosts->o6));
        $this->assertArrayEqualsWithKeys([
            $hosts->o1->getAutoincId(),
            $hosts->o4->getAutoincId(),
            $hosts->o2->getAutoincId(),
        ], $tree->listAncestorIdsFor($hosts->o6));
    }

    protected function assertArrayEqualsWithKeys($expected, $actual)
    {
        $message = sprintf(
            'Failed asserting that %s equals %s',
            json_encode($actual),
            json_encode($expected)
        );

        $this->assertTrue(
            $expected === $actual,
            $message
        );
    }

    protected function assertSameArrayValues($expected, $actual)
    {
        $message = sprintf(
            'Failed asserting that %s has the same values as %s',
            json_encode($actual),
            json_encode($expected)
        );

        sort($expected);
        sort($actual);
        $this->assertTrue(
            $expected === $actual,
            $message
        );
    }

    public function testChildrenAreResolvedCorrectlyOverMultipleLevels()
    {
        $db = $this->getDb();
        $o1 = IcingaService::create([
            'object_name' => 'o1',
            'object_type' => 'template'
        ], $db);
        $o2 = IcingaService::create([
            'object_name' => 'o2',
            'object_type' => 'template'
        ], $db);
        $o3 = IcingaService::create([
            'object_name' => 'o3',
            'object_type' => 'template'
        ], $db);
        $o4 = IcingaService::create([
            'object_name' => 'o4',
            'object_type' => 'template',
            'imports'     => ['o2', 'o1'],
        ], $db);
        $o5 = IcingaService::create([
            'object_name' => 'o5',
            'object_type' => 'template',
            'imports'     => ['o4'],
        ], $db);
        $o6 = IcingaService::create([
            'object_name' => 'o6',
            'object_type' => 'template',
            'imports'     => ['o4', 'o2'],
        ], $db);
        $o7 = IcingaService::create([
            'object_name' => 'o7',
            'object_type' => 'apply',
            'imports'     => ['o4', 'o2'],
        ], $db);
        $o1->store();
        $o2->store();
        $o3->store();
        $o4->store();
        $o5->store();
        $o6->store();
        $o7->store();
        $this->applyId = (int) $o7->get('id');

        $tree = new TemplateTree('service', $db);
        $this->assertEquals([
            $o4->id => 'o4',
            $o5->id => 'o5',
            $o6->id => 'o6',
        ], $tree->getDescendantsFor($o2));
        $this->assertSameArrayValues([
            $o4->getAutoincId(),
            (int) $o5->id,
            (int) $o6->getProperty('id'),
        ], $tree->listDescendantIdsFor($o2));
        $this->assertEquals([
            $o5->id => 'o5',
            $o6->id => 'o6',
        ], $tree->getChildrenFor($o4));
        $this->assertEquals([], $tree->getChildrenFor($o5));
    }

    protected function removeHosts(Db $db)
    {
        $kill = array('o7', 'o6', 'o5', 'o4', 'o3', 'o2', 'o1');
        foreach ($kill as $name) {
            if (IcingaHost::exists($name, $db)) {
                IcingaHost::load($name, $db)->delete();
            }
        }
    }

    protected function removeServices(Db $db)
    {
        if ($this->applyId) {
            $key = ['id' => $this->applyId];
            if (IcingaService::exists($key, $db)) {
                IcingaService::load($key, $db)->delete();
            }
        }

        $kill = array('o6', 'o5', 'o4', 'o3', 'o2', 'o1');
        foreach ($kill as $name) {
            $key = [
                'object_name' => $name,
                'object_type' => 'template',
            ];
            if (IcingaService::exists($key, $db)) {
                IcingaService::load($key, $db)->delete();
            }
        }
    }

    public function tearDown(): void
    {
        if ($this->hasDb()) {
            $db = $this->getDb();
            $this->removeHosts($db);
            $this->removeServices($db);
        }
    }
}

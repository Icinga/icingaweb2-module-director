<?php

namespace Tests\Icinga\Module\Director\Objects;

use Icinga\Module\Director\Objects\IcingaHost;
use Icinga\Module\Director\Test\BaseTestCase;

class IcingaTemplateResolverTest extends BaseTestCase
{
    /** @var IcingaHost[] */
    private $scenario;

    private $prefix = '__TEST_i1_';

    public function testParentNamesCanBeRetrieved()
    {
        $this->assertEquals(
            array(
                $this->prefixed('t6'),
                $this->prefixed('t5'),
                $this->prefixed('t2')
            ),
            $this->getHost('t1')->templateResolver()->listParentNames()
        );
    }

    public function testFullInhertancePathIdsAreCorrect()
    {
        $this->assertEquals(
            $this->getIds(array('t5', 't6', 't5', 't5', 't4', 't3', 't5', 't6', 't2', 't1')),
            $this->getHost('t1')->templateResolver()->listFullInheritancePathIds()
        );
    }

    public function testInhertancePathIdsAreCorrect()
    {
        $this->assertEquals(
            $this->getIds(array('t4', 't3', 't5', 't6', 't2', 't1')),
            $this->getHost('t1')->templateResolver()->listInheritancePathIds()
        );
    }

    protected function getHost($name)
    {
        $hosts = $this->getScenario();
        return $hosts[$name];
    }

    protected function getId($name)
    {
        $hosts = $this->getScenario();
        return $hosts[$name]->id;
    }

    protected function getIds($names)
    {
        $ids = array();
        foreach ($names as $name) {
            $ids[] = $this->getId($name);
        }

        return $ids;
    }

    protected function prefixed($name)
    {
        return $this->prefix . $name;
    }

    /**
     * @return IcingaHost[]
     */
    protected function getScenario()
    {
        if ($this->scenario === null) {
            $this->scenario = $this->createScenario();
        }

        return $this->scenario;
    }

    /**
     * @return IcingaHost[]
     */
    protected function createScenario()
    {
        // Defionition imports (object -> imported)
        // t1 -> t6, t5, t2
        // t6 -> t5
        // t3 -> t4
        // t2 -> t3, t6
        // t4 -> t5

        // 5, 6, 5, 5, 4, 3, 5, 6, 2, 1

        $t1 = $this->create('t1');
        $t4 = $this->create('t4');
        $t3 = $this->create('t3');
        $t2 = $this->create('t2');
        $t5 = $this->create('t5');
        $t6 = $this->create('t6');

        // TODO: Must work without this:
        $t1->templateResolver()->clearCache();
        $t1->set('imports', array($t6, $t5, $t2));
        $t6->set('imports', array($t5));
        $t3->set('imports', array($t4));
        $t2->set('imports', array($t3, $t6));
        $t4->set('imports', array($t5));

        $t5->store();
        $t4->store();
        $t3->store();
        $t6->store();
        $t2->store();
        $t1->store();

        // TODO: Must work without this:
        $t1->templateResolver()->clearCache();
        return array(
            't1' => $t1,
            't2' => $t2,
            't3' => $t3,
            't4' => $t4,
            't5' => $t5,
            't6' => $t6,
        );
    }

    /**
     * @param $name
     * @return IcingaHost
     */
    protected function create($name)
    {
        $host = IcingaHost::create(
            array(
                'object_name' => $this->prefixed($name),
                'object_type' => 'template'
            )
        );

        $host->store($this->getDb());
        return $host;
    }

    public function tearDown(): void
    {
        $db = $this->getDb();
        $kill = array('t1', 't2', 't6', 't3', 't4', 't5');
        foreach ($kill as $short) {
            $name = $this->prefixed($short);
            if (IcingaHost::exists($name, $db)) {
                IcingaHost::load($name, $db)->delete();
            }
        }

        parent::tearDown();
    }
}

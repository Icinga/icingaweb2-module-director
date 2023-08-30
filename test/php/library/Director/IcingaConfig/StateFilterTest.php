<?php

namespace Tests\Icinga\Module\Director\IcingaConfig;

use Icinga\Module\Director\IcingaConfig\StateFilterSet;
use Icinga\Module\Director\Objects\IcingaUser;
use Icinga\Module\Director\Test\BaseTestCase;

class StateFilterSetTest extends BaseTestCase
{
    protected $testUserName1 = '__testuser2';

    protected $testUserName2 = '__testuser2';

    public function testIsEmptyForAnUnstoredUser()
    {
        $this->assertEquals(
            array(),
            StateFilterSet::forIcingaObject(
                IcingaUser::create(),
                'states'
            )->getResolvedValues()
        );
    }

    /**
     * @expectedException \Icinga\Exception\InvalidPropertyException
     */
    public function testFailsForInvalidProperties()
    {
        $set = new StateFilterSet('bla');
    }

    /**
     * @expectedException \Icinga\Exception\ProgrammingError
     */
    public function testCannotBeStoredForAnUnstoredUser()
    {
        StateFilterSet::forIcingaObject(
            $this->user1(),
            'states'
        )->override(
            array('OK', 'Down')
        )->store();
    }

    public function testCanBeStored()
    {
        if ($this->skipForMissingDb()) {
            return;
        }

        $states = $this->simpleUnstoredSetForStoredUser();

        $this->assertTrue($states->store());
        $states->getObject()->delete();
    }

    public function testWillNotBeStoredTwice()
    {
        if ($this->skipForMissingDb()) {
            return;
        }

        $states = $this->simpleUnstoredSetForStoredUser();

        $this->assertTrue($states->store());
        $this->assertFalse($states->store());
        $this->assertFalse($states->store());
        $states->getObject()->delete();
    }

    public function testComplexDefinitionsCanBeStored()
    {
        if ($this->skipForMissingDb()) {
            return;
        }

        $states = $this->complexUnstoredSetForStoredUser();

        $this->assertTrue($states->store());
        $states->getObject()->delete();
    }

    public function testComplexDefinitionsCanBeLoadedAndRenderCorrectly()
    {
        if ($this->skipForMissingDb()) {
            return;
        }

        $states = $this->complexUnstoredSetForStoredUser();
        $user = $states->getObject();

        $this->assertTrue($states->store());

        $states = StateFilterSet::forIcingaObject($user, 'states');
        $expected = '    states = [ Down, OK, Up ]' . "\n"
                  . '    states += [ Warning ]' . "\n"
                  . '    states -= [ Up ]' . "\n";

        $this->assertEquals(
            $expected,
            $states->renderAs('states')
        );

        $states->getObject()->delete();
    }

    protected function simpleUnstoredSetForStoredUser()
    {
        $user = $this->user1();
        $user->store($this->getDb());

        $states = StateFilterSet::forIcingaObject(
            $user,
            'states'
        )->override(
            array('OK', 'Down')
        );

        return $states;
    }

    protected function complexUnstoredSetForStoredUser()
    {
        $user = $this->user2();
        $user->store($this->getDb());

        $states = StateFilterSet::forIcingaObject(
            $user,
            'states'
        )->override(
            array('OK', 'Down', 'Up')
        )->blacklist('Up')->extend('Warning');

        return $states;
    }

    protected function user1()
    {
        return IcingaUser::create(array(
            'object_type' => 'object',
            'object_name' => $this->testUserName1
        ));
    }

    protected function user2()
    {
        return IcingaUser::create(array(
            'object_type' => 'object',
            'object_name' => $this->testUserName2
        ));
    }

    public function tearDown(): void
    {
        if ($this->hasDb()) {
            $users = array(
                $this->testUserName1,
                $this->testUserName2
            );

            $db = $this->getDb();
            foreach ($users as $user) {
                if (IcingaUser::exists($user, $db)) {
                    IcingaUser::load($user, $db)->delete();
                }
            }
        }
    }
}

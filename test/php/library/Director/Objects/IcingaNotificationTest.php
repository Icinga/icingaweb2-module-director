<?php

namespace Tests\Icinga\Module\Director\Objects;

use Icinga\Module\Director\Objects\IcingaHost;
use Icinga\Module\Director\Objects\IcingaService;
use Icinga\Module\Director\Objects\IcingaNotification;
use Icinga\Module\Director\Objects\IcingaUser;
use Icinga\Module\Director\Objects\IcingaUsergroup;
use Icinga\Module\Director\Test\BaseTestCase;

class IcingaNotificationTest extends BaseTestCase
{
    protected $testUserName1 = '___TEST___user1';

    protected $testUserName2 = '___TEST___user2';

    protected $testNotificationName = '___TEST___notification';

    public function testPropertiesCanBeSet()
    {
        $n = $this->notification();
        $n->notification_interval = '10m';
        $this->assertEquals(
            $n->notification_interval,
            600
        );
    }

    public function testCanBeStoredAndDeletedWithRelatedUserPassedAsString()
    {
        if ($this->skipForMissingDb()) {
            return;
        }
        $db = $this->getDb();

        $user = $this->user1();
        $user->store($db);

        $n = $this->notification();
        $n->users = $user->object_name;
        $this->assertTrue($n->store($db));
        $this->assertTrue($n->delete());
        $user->delete();
    }

    public function testCanBeStoredAndDeletedWithMultipleRelatedUsers()
    {
        if ($this->skipForMissingDb()) {
            return;
        }
        $db = $this->getDb();

        $user1 = $this->user1();
        $user1->store($db);

        $user2 = $this->user2();
        $user2->store($db);

        $n = $this->notification();
        $n->users = array($user1->object_name, $user2->object_name);
        $this->assertTrue($n->store($db));
        $this->assertTrue($n->delete());
        $user1->delete();
        $user2->delete();
    }

    public function testGivesPlainObjectWithRelatedUsers()
    {
        if ($this->skipForMissingDb()) {
            return;
        }
        $db = $this->getDb();

        $user1 = $this->user1();
        $user1->store($db);

        $user2 = $this->user2();
        $user2->store($db);

        $n = $this->notification();
        $n->users = array($user1->object_name, $user2->object_name);
        $n->store($db);
        $this->assertEquals(
            (object) array(
                'object_name' => $this->testNotificationName,
                'object_type' => 'object',
                'users' => array(
                    $user1->object_name,
                    $user2->object_name
                )
            ),
            $n->toPlainObject(false, true)
        );

        $n = IcingaNotification::load($n->object_name, $db);
        $this->assertEquals(
            (object) array(
                'object_name' => $this->testNotificationName,
                'object_type' => 'object',
                'users' => array(
                    $user1->object_name,
                    $user2->object_name
                )
            ),
            $n->toPlainObject(false, true)
        );
        $this->assertEquals(
            array(),
            $n->toPlainObject()->user_groups
        );
        $n->delete();

        $user1->delete();
        $user2->delete();
    }

    public function testHandlesChangesForStoredRelations()
    {
        if ($this->skipForMissingDb()) {
            return;
        }
        $db = $this->getDb();

        $user1 = $this->user1();
        $user1->store($db);

        $user2 = $this->user2();
        $user2->store($db);

        $n = $this->notification();
        $n->users = array($user1->object_name, $user2->object_name);
        $n->store($db);

        $n = IcingaNotification::load($n->object_name, $db);
        $this->assertFalse($n->hasBeenModified());

        $n->users = array($user2->object_name);
        $this->assertTrue($n->hasBeenModified());

        $n->store();

        $n = IcingaNotification::load($n->object_name, $db);
        $this->assertEquals(
            array($user2->object_name),
            $n->users
        );

        $n->users = array();
        $n->store();

        $n = IcingaNotification::load($n->object_name, $db);
        $this->assertEquals(
            array(),
            $n->users
        );

        // Should be fixed with lazy loading:
        // $n->users = array($user1->object_name, $user2->object_name);
        // $this->assertFalse($n->hasBeenModified());

        $n->delete();

        $user1->delete();
        $user2->delete();
    }

    public function testRendersConfigurationWithRelatedUsers()
    {
        if ($this->skipForMissingDb()) {
            return;
        }
        $db = $this->getDb();

        $user1 = $this->user1();
        $user1->store($db);

        $user2 = $this->user2();
        $user2->store($db);

        $n = $this->notification();
        $n->users = array($user1->object_name, $user2->object_name);

        $this->assertEquals(
            $this->loadRendered('notification1'),
            (string) $n
        );
    }

    public function testLazyUsersCanBeSet()
    {
        $this->markTestSkipped('Setting lazy properties not yet completed');

        $n = $this->notification();
        $n->users = 'bla';
    }

    protected function user1()
    {
        return IcingaUser::create(array(
            'object_name' => $this->testUserName1,
            'object_type' => 'object',
            'email'       => 'nowhere@example.com',
        ), $this->getDb());
    }

    protected function user2()
    {
        return IcingaUser::create(array(
            'object_name' => $this->testUserName2,
            'object_type' => 'object',
            'email'       => 'nowhere.else@example.com',
        ), $this->getDb());
    }

    protected function notification()
    {
        return IcingaNotification::create(array(
            'object_name' => $this->testNotificationName,
            'object_type' => 'object',
        ), $this->getDb());
    }

    protected function loadRendered($name)
    {
        return file_get_contents(__DIR__ . '/rendered/' . $name . '.out');
    }

    public function tearDown(): void
    {
        if ($this->hasDb()) {
            $db = $this->getDb();
            $kill = array($this->testNotificationName);
            foreach ($kill as $name) {
                if (IcingaNotification::exists($name, $db)) {
                    IcingaNotification::load($name, $db)->delete();
                }
            }

            $kill = array($this->testUserName1, $this->testUserName2);
            foreach ($kill as $name) {
                if (IcingaUser::exists($name, $db)) {
                    IcingaUser::load($name, $db)->delete();
                }
            }
        }
    }
}

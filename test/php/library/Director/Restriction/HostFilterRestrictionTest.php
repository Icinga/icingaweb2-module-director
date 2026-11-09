<?php

// SPDX-FileCopyrightText: 2026 Icinga GmbH <https://icinga.com>
// SPDX-License-Identifier: GPL-3.0-or-later

namespace Tests\Icinga\Module\Director\Restriction;

use Icinga\Authentication\Auth;
use Icinga\Module\Director\Auth\Restriction;
use Icinga\Module\Director\Db\Cache\PrefetchCache;
use Icinga\Module\Director\Objects\IcingaHost;
use Icinga\Module\Director\Objects\IcingaHostGroup;
use Icinga\Module\Director\Objects\IcingaService;
use Icinga\Module\Director\Restriction\HostFilterMatches;
use Icinga\Module\Director\Restriction\HostFilterRestriction;
use Icinga\Module\Director\Test\BaseTestCase;
use Icinga\Module\Director\Web\Table\ObjectsTable;
use Icinga\Module\Director\Web\Table\ObjectsTableService;
use Icinga\User;
use ReflectionProperty;

class HostFilterRestrictionTest extends BaseTestCase
{
    private const PREFIX = '___TEST___hostfilter_';

    public static function setUpBeforeClass(): void
    {
        if (static::getDbResourceName() === null) {
            return;
        }

        static::cleanArtifacts();
        $db = static::getDb();

        // Groups first, so the hosts below get resolved into them when being stored
        IcingaHostGroup::create([
            'object_name'   => self::PREFIX . 'applied',
            'object_type'   => 'object',
            'assign_filter' => 'host.vars.team=%22Team_B%22'
        ], $db)->store();
        IcingaHostGroup::create([
            'object_name' => self::PREFIX . 'static',
            'object_type' => 'object'
        ], $db)->store();

        IcingaHost::create([
            'object_name' => self::PREFIX . 'tpl',
            'object_type' => 'template',
            'vars.team'   => ['Team_A']
        ], $db)->store();

        $hosts = [
            'a'    => ['vars.team' => ['Team_A'], 'vars.port' => 22],
            'b'    => ['vars.team' => ['Team_B']],
            'ab'   => ['vars.team' => ['Team_A', 'Team_B']],
            'str'  => ['vars.team' => 'Team_A'],
            'none' => [],
            'inh'  => ['imports' => [self::PREFIX . 'tpl']],
        ];
        foreach ($hosts as $name => $properties) {
            $host = IcingaHost::create(array_merge([
                'object_name' => self::PREFIX . $name,
                'object_type' => 'object',
            ], $properties), $db);
            if ($name === 'none') {
                $host->setGroups(self::PREFIX . 'static');
            }

            $host->store();
        }

        foreach (['a', 'b', 'tpl'] as $name) {
            IcingaService::create([
                'object_name' => self::PREFIX . 'svc',
                'object_type' => 'object',
                'host'        => self::PREFIX . $name,
            ], $db)->store();
        }
    }

    public static function tearDownAfterClass(): void
    {
        if (static::getDbResourceName() !== null) {
            static::cleanArtifacts();
        }
    }

    protected static function cleanArtifacts(): void
    {
        $dba = static::getDb()->getDbAdapter();
        $where = $dba->quoteInto('object_name LIKE ?', self::PREFIX . '%');
        $dba->delete('icinga_host', $where . " AND object_type = 'object'");
        $dba->delete('icinga_host', $where);
        $dba->delete('icinga_hostgroup', $where);
    }

    public function setUp(): void
    {
        parent::setUp();
        HostFilterMatches::clearCache();
    }

    protected function tearDown(): void
    {
        // don't leak the fake user into other tests
        $user = new ReflectionProperty(Auth::class, 'user');
        $user->setAccessible(true);
        $user->setValue(Auth::getInstance(), null);

        parent::tearDown();
    }

    public function testUnrestrictedUserIsAllowedEverything(): void
    {
        if ($this->skipForMissingDb()) {
            return;
        }

        $restriction = $this->restrictionFor([]);

        $this->assertFalse($restriction->isRestricted());
        $this->assertTrue($restriction->allows($this->host('none')));
        $this->assertEquals(['a', 'ab', 'b', 'inh', 'none', 'str'], $this->listAllowed($restriction));
    }

    public function testMatchesArraysAndStrings(): void
    {
        $this->assertAllowedHosts(['a', 'ab', 'inh', 'str'], ['host.vars.team=Team_A']);
    }

    public function testArrayMarkerMatchesLikeWithout(): void
    {
        $this->assertAllowedHosts(['a', 'ab', 'inh', 'str'], ['host.vars.team[]=Team_A']);
        $this->assertAllowedHosts(['a', 'ab', 'inh', 'str'], ['host.vars.team[*]=Team_A']);
    }

    public function testMatchesWildcards(): void
    {
        $this->assertAllowedHosts(['a', 'ab', 'b', 'inh', 'str'], ['host.vars.team=Team_*']);
    }

    public function testNegationIncludesHostsWithoutTheVariable(): void
    {
        $this->assertAllowedHosts(['a', 'inh', 'none', 'str'], ['!(host.vars.team=Team_B)']);
    }

    public function testMatchesNumbers(): void
    {
        $this->assertAllowedHosts(['a'], ['host.vars.port=22']);
    }

    public function testMatchesStaticHostgroups(): void
    {
        $this->assertAllowedHosts(['none'], ['hostgroup.name=' . self::PREFIX . 'static']);
    }

    public function testMatchesHostgroupsAssignedByFilter(): void
    {
        $this->assertAllowedHosts(['ab', 'b'], ['hostgroup.name=' . self::PREFIX . 'applied']);
    }

    public function testMatchesHostNames(): void
    {
        $this->assertAllowedHosts(['ab', 'b'], ['host.name=' . self::PREFIX . '*b']);
    }

    public function testRestrictionsOfMultipleRolesAreCombinedWithOr(): void
    {
        $this->assertAllowedHosts(
            ['ab', 'b', 'none'],
            ['host.vars.team=Team_B', 'host.name=' . self::PREFIX . 'none']
        );
    }

    public function testListingLeavesNoPrefetchCacheBehind(): void
    {
        if ($this->skipForMissingDb()) {
            return;
        }

        $this->listAllowed($this->restrictionFor(['host.vars.team=Team_A']));

        $this->assertFalse(PrefetchCache::shouldBeUsed());
    }

    public function testTemplatesAreNeverAllowed(): void
    {
        if ($this->skipForMissingDb()) {
            return;
        }

        $restriction = $this->restrictionFor(['host.vars.team=Team_A']);
        $template = IcingaHost::load(self::PREFIX . 'tpl', $this->getDb());

        $this->assertFalse($restriction->allows($template));
    }

    public function testHostsTableShowsAllowedHostsOnly(): void
    {
        if ($this->skipForMissingDb()) {
            return;
        }

        $this->restrictionFor(['host.vars.team=Team_B']);
        $table = ObjectsTable::create('host', $this->getDb(), Auth::getInstance());

        $this->assertEquals(['ab', 'b'], $this->fetchTestObjectNames($table, 'o.object_name'));
    }

    public function testServicesTableShowsServicesOfAllowedHostsOnly(): void
    {
        if ($this->skipForMissingDb()) {
            return;
        }

        $this->restrictionFor(['host.vars.team=Team_B']);
        $table = ObjectsTable::create('service', $this->getDb(), Auth::getInstance());

        $this->assertEquals(['b'], $this->fetchTestObjectNames($table, 'h.object_name'));
    }

    public function testInheritedServicesDependOnTheDisplayedHost(): void
    {
        if ($this->skipForMissingDb()) {
            return;
        }

        $db = $this->getDb();
        $this->restrictionFor(['host.name=' . self::PREFIX . 'inh']);
        $template = IcingaHost::load(self::PREFIX . 'tpl', $db);

        $allowed = (new ObjectsTableService($db, Auth::getInstance()))
            ->setHost($template)
            ->setInheritedBy($this->host('inh'));
        $this->assertCount(1, $db->getDbAdapter()->fetchAll($allowed->getQuery()));

        $this->restrictionFor(['host.name=' . self::PREFIX . 'a']);
        $denied = (new ObjectsTableService($db, Auth::getInstance()))
            ->setHost($template)
            ->setInheritedBy($this->host('inh'));
        $this->assertCount(0, $db->getDbAdapter()->fetchAll($denied->getQuery()));
    }

    /**
     * Get the names of this test's objects shown by the given table
     *
     * @param ObjectsTable $table
     * @param string $nameColumn The column holding the host name
     *
     * @return string[] Host names without prefix, sorted
     */
    private function fetchTestObjectNames(ObjectsTable $table, string $nameColumn): array
    {
        $query = $table->getQuery()
            ->where("$nameColumn LIKE ?", self::PREFIX . '%')
            ->reset('order')
            ->order($nameColumn);

        $names = [];
        foreach ($this->getDb()->getDbAdapter()->fetchAll($query) as $row) {
            $names[] = substr($row->host ?? $row->object_name, strlen(self::PREFIX));
        }

        return $names;
    }

    /**
     * Assert the allowed hosts, both for the lists and for single hosts
     *
     * @param string[] $expected Host names without prefix, sorted
     * @param string[] $restrictions
     */
    private function assertAllowedHosts(array $expected, array $restrictions): void
    {
        if ($this->skipForMissingDb()) {
            return;
        }

        $restriction = $this->restrictionFor($restrictions);
        $this->assertEquals($expected, $this->listAllowed($restriction), 'Hosts allowed in lists');

        $allowedOneByOne = [];
        foreach (['a', 'ab', 'b', 'inh', 'none', 'str'] as $name) {
            if ($restriction->allows($this->host($name))) {
                $allowedOneByOne[] = $name;
            }
        }

        $this->assertEquals($expected, $allowedOneByOne, 'Hosts allowed when opened directly');
    }

    /**
     * Get the hosts of this test the given restriction allows in a list
     *
     * @return string[] Host names without prefix, sorted
     */
    private function listAllowed(HostFilterRestriction $restriction): array
    {
        $dba = $this->getDb()->getDbAdapter();
        $query = $dba->select()
            ->from(['o' => 'icinga_host'], ['object_name'])
            ->where('o.object_name LIKE ?', self::PREFIX . '%')
            ->where("o.object_type = 'object'")
            ->order('o.object_name');
        $restriction->applyToQuery($query);

        return array_map(function ($name) {
            return substr($name, strlen(self::PREFIX));
        }, $dba->fetchCol($query));
    }

    private function host(string $name): IcingaHost
    {
        return IcingaHost::load(self::PREFIX . $name, $this->getDb());
    }

    /**
     * @param string[] $restrictions director/filter/hosts, one per role
     */
    private function restrictionFor(array $restrictions): HostFilterRestriction
    {
        $user = new User(self::PREFIX . 'user');
        $user->setRestrictions(empty($restrictions) ? [] : [Restriction::FILTER_HOSTS => $restrictions]);
        Auth::getInstance()->setUser($user);

        return new HostFilterRestriction($this->getDb(), Auth::getInstance());
    }
}

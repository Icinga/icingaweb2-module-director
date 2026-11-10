<?php

// SPDX-FileCopyrightText: 2026 Icinga GmbH <https://icinga.com>
// SPDX-License-Identifier: GPL-3.0-or-later

namespace Tests\Icinga\Module\Director\CustomVariable;

use Icinga\Module\Director\CustomVariable\CustomPropertySource;
use Icinga\Module\Director\CustomVariable\CustomPropertySourceKind;
use Icinga\Module\Director\CustomVariable\CustomPropertySources;
use Icinga\Module\Director\Objects\IcingaHost;
use Icinga\Module\Director\Objects\IcingaService;
use Icinga\Module\Director\Test\BaseTestCase;
use Tests\Icinga\Module\Director\Lib\CustomVariable\CommandVariableFixture;

class CustomPropertySourcesTest extends BaseTestCase
{
    use CommandVariableFixture;

    public function testAHostReachesItsCheckCommandAndEveryCommandItImports(): void
    {
        if ($this->skipForMissingDb()) {
            return;
        }

        $timeout = $this->createFixtureProperty('timeout', 'number');
        $warning = $this->createFixtureProperty('warning');
        $owner = $this->createFixtureProperty('owner');
        $this->createFixtureCommand('plugin-base', 'template', [], [$timeout]);
        $this->createFixtureCommand('check-disk', 'object', ['plugin-base'], [$warning]);
        $this->createFixtureHost('linux', 'template', [], 'check-disk', [$owner]);
        $host = $this->createFixtureHost('web01', 'object', ['linux']);

        $sources = (new CustomPropertySources($this->getDb()))->getSources($host);

        $this->assertSame(
            [
                [CustomPropertySourceKind::Own, $this->fx('web01')],
                [CustomPropertySourceKind::Template, $this->fx('linux')],
                [CustomPropertySourceKind::Command, $this->fx('check-disk')],
                [CustomPropertySourceKind::Command, $this->fx('plugin-base')],
            ],
            array_map(fn(CustomPropertySource $source) => [$source->kind, $source->name], $sources)
        );
        $this->assertEqualsCanonicalizing(
            [$this->fx('owner'), $this->fx('warning'), $this->fx('timeout')],
            array_keys((new CustomPropertySources($this->getDb()))->getReachableProperties($host))
        );
    }

    public function testTheOwnCheckCommandWinsOverTheInheritedOne(): void
    {
        if ($this->skipForMissingDb()) {
            return;
        }

        $diskVar = $this->createFixtureProperty('disk');
        $loadVar = $this->createFixtureProperty('load');
        $this->createFixtureCommand('check-disk', 'object', [], [$diskVar]);
        $this->createFixtureCommand('check-load', 'object', [], [$loadVar]);
        $this->createFixtureService('generic', 'template', [], 'check-disk');
        $service = $this->createFixtureService('load', 'template', ['generic'], 'check-load');

        $reachable = (new CustomPropertySources($this->getDb()))->getReachableProperties($service);

        $this->assertSame([$this->fx('load')], array_keys($reachable));
    }

    public function testTheLastImportProvidesTheCheckCommandDepthFirst(): void
    {
        if ($this->skipForMissingDb()) {
            return;
        }

        $first = $this->createFixtureProperty('first');
        $deep = $this->createFixtureProperty('deep');
        $this->createFixtureCommand('first-command', 'object', [], [$first]);
        $this->createFixtureCommand('deep-command', 'object', [], [$deep]);
        $this->createFixtureHost('with-command', 'template', [], 'first-command');
        $this->createFixtureHost('grand-parent', 'template', [], 'deep-command');
        $this->createFixtureHost('without-command', 'template', ['grand-parent']);
        $host = $this->createFixtureHost('web01', 'object', ['with-command', 'without-command']);

        $sources = new CustomPropertySources($this->getDb());

        $this->assertSame([$this->fx('deep')], array_keys($sources->getReachableProperties($host)));
        $this->assertSame(
            [$this->fx('deep') => $deep->get('uuid')],
            $sources->getReachablePropertyUuids('host', [(int) $host->get('id')])[(int) $host->get('id')],
            'stored objects resolve their check command the same way'
        );
    }

    public function testPendingSourcesApplyToTheObjectsBelow(): void
    {
        if ($this->skipForMissingDb()) {
            return;
        }

        $disk = $this->createFixtureProperty('disk');
        $owner = $this->createFixtureProperty('owner');
        $this->createFixtureCommand('check-disk', 'object', [], [$disk]);
        $this->createFixtureHost('owners', 'template', [], null, [$owner]);
        $template = $this->createFixtureHost('linux', 'template', ['owners'], 'check-disk');
        $host = $this->createFixtureHost('web01', 'object', ['linux']);
        $hostId = (int) $host->get('id');

        $sources = (new CustomPropertySources($this->getDb()))
            ->setPendingCheckCommand($template, null)
            ->setPendingImports($template, []);

        $this->assertSame([], $sources->getReachablePropertyUuids('host', [$hostId])[$hostId]);
        $owners = IcingaHost::load($this->fx('owners'), $this->getDb());
        $this->assertSame([$hostId], $sources->listDescendantIds('host', (int) $template->get('id')));
        $this->assertSame(
            [],
            $sources->listDescendantIds('host', (int) $owners->get('id')),
            'the pending imports of the template cut the host off from the old parent'
        );
    }

    public function testDescendantsIncludeTemplatesObjectsAndServicesOfAllKinds(): void
    {
        if ($this->skipForMissingDb()) {
            return;
        }

        $base = $this->createFixtureService('base', 'template');
        $middle = $this->createFixtureService('middle', 'template', ['base']);
        $this->createFixtureService('other', 'template');
        $this->createFixtureHost('web01');
        $apply = $this->createFixtureService('disk-apply', 'apply', ['middle']);
        $onHost = $this->createFixtureService('disk', 'object', ['base'], null, 'web01');
        $this->createFixtureService('ping', 'object', ['other'], null, 'web01');

        $descendants = (new CustomPropertySources($this->getDb()))
            ->listDescendantIds('service', (int) $base->get('id'));
        sort($descendants);
        $expected = [(int) $middle->get('id'), (int) $apply->get('id'), (int) $onHost->get('id')];
        sort($expected);

        $this->assertSame($expected, $descendants);
        $this->assertSame(
            [],
            (new CustomPropertySources($this->getDb()))->listDescendantIds('service', (int) $onHost->get('id')),
            'nothing can import a service object'
        );
    }

    public function testFindingDescendantsAndTheirSourcesDoesNotQueryPerObject(): void
    {
        if ($this->skipForMissingDb()) {
            return;
        }

        $template = $this->createFixtureHost('linux', 'template');
        $this->createFixtureHost('web', 'template', ['linux']);
        $this->createFixtureHost('web0', 'object', ['web']);
        $count = fn() => $this->countQueries(function () use ($template) {
            $sources = new CustomPropertySources($this->getDb());
            $ids = $sources->listDescendantIds('host', (int) $template->get('id'));
            $sources->getStoredSourceKeys('host', $ids);
            $sources->getReachablePropertyUuids('host', $ids);
        });

        $queries = $count();
        for ($i = 1; $i < 25; $i++) {
            $this->createFixtureHost('web' . $i, 'object', ['web']);
        }

        $this->assertSame($queries, $count());
    }

    public function testApplyRulesAndServiceObjectsReachTheirCheckCommand(): void
    {
        if ($this->skipForMissingDb()) {
            return;
        }

        $disk = $this->createFixtureProperty('disk');
        $this->createFixtureCommand('check-disk', 'object', [], [$disk]);
        $this->createFixtureService('generic', 'template', [], 'check-disk');
        $this->createFixtureHost('web01');
        $apply = $this->createFixtureService('disk-apply', 'apply', ['generic']);
        $onHost = $this->createFixtureService('disk', 'object', ['generic'], null, 'web01');

        $sources = new CustomPropertySources($this->getDb());
        $reachable = $sources->getReachablePropertyUuids('service', [
            (int) $apply->get('id'),
            (int) $onHost->get('id'),
        ]);

        $this->assertArrayHasKey($this->fx('disk'), $reachable[(int) $apply->get('id')]);
        $this->assertArrayHasKey($this->fx('disk'), $reachable[(int) $onHost->get('id')]);
    }

    public function testTheNumberOfQueriesDoesNotGrowWithTheNumberOfHolders(): void
    {
        if ($this->skipForMissingDb()) {
            return;
        }

        $disk = $this->createFixtureProperty('disk');
        $this->createFixtureCommand('check-disk', 'object', [], [$disk]);
        $this->createFixtureHost('linux', 'template', [], 'check-disk');
        $ids = [];
        for ($i = 0; $i < 25; $i++) {
            $ids[] = (int) $this->createFixtureHost('web' . $i, 'object', ['linux'])->get('id');
        }

        $this->assertSame(
            $this->countQueries(fn() => (new CustomPropertySources($this->getDb()))
                ->getReachablePropertyUuids('host', array_slice($ids, 0, 1))),
            $this->countQueries(fn() => (new CustomPropertySources($this->getDb()))
                ->getReachablePropertyUuids('host', $ids))
        );
    }

    public function testAServiceTemplateWithoutAnyCommandReachesOnlyItsOwnProperties(): void
    {
        if ($this->skipForMissingDb()) {
            return;
        }

        $owner = $this->createFixtureProperty('owner');
        $template = $this->createFixtureService('generic', 'template', [], null, null, [$owner]);
        $new = IcingaService::create(['object_name' => $this->fx('new'), 'object_type' => 'template'], $this->getDb());
        $new->setImports([$this->fx('generic')]);

        $sources = new CustomPropertySources($this->getDb());

        $this->assertSame([$this->fx('owner')], array_keys($sources->getReachableProperties($template)));
        $this->assertSame(
            [$this->fx('owner')],
            array_keys($sources->getReachableProperties($new)),
            'an object that is not stored yet reaches what its imports provide'
        );
    }

    public function tearDown(): void
    {
        $this->removeFixtures();
        parent::tearDown();
    }

    /**
     * Count the queries the given callback runs
     *
     * @param callable $callback
     *
     * @return int
     */
    private function countQueries(callable $callback): int
    {
        $profiler = $this->getDb()->getDbAdapter()->getProfiler();
        $profiler->setEnabled(true);
        $profiler->clear();
        try {
            $callback();

            return $profiler->getTotalNumQueries();
        } finally {
            $profiler->setEnabled(false);
        }
    }
}

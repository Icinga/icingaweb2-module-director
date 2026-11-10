<?php

// SPDX-FileCopyrightText: 2026 Icinga GmbH <https://icinga.com>
// SPDX-License-Identifier: GPL-3.0-or-later

namespace Tests\Icinga\Module\Director\CustomVariable;

use Icinga\Module\Director\CustomVariable\PropertyDetachmentCleaner;
use Icinga\Module\Director\CustomVariable\PropertySourceChange;
use Icinga\Module\Director\Db\DbUtil;
use Icinga\Module\Director\Objects\DirectorProperty;
use Icinga\Module\Director\Objects\IcingaCommand;
use Icinga\Module\Director\Objects\IcingaHost;
use Icinga\Module\Director\Objects\IcingaObject;
use Icinga\Module\Director\Objects\IcingaService;
use Icinga\Module\Director\Test\BaseTestCase;
use Tests\Icinga\Module\Director\Lib\CustomVariable\CommandVariableFixture;

class PropertySourceChangeTest extends BaseTestCase
{
    use CommandVariableFixture;

    public function testRemovingATemplateKeepsAValueTheCheckCommandStillProvides(): void
    {
        if ($this->skipForMissingDb()) {
            return;
        }

        $port = $this->createFixtureProperty('port');
        $this->createFixtureCommand('check-ssh', 'object', [], [$port]);
        $this->createFixtureHost('ssh', 'template', [], null, [$port]);
        $this->createFixtureHost('linux', 'template', [], 'check-ssh');
        $host = $this->createFixtureHost('web01', 'object', ['ssh', 'linux']);
        $this->storeFixtureValue($host, 'port', '22', $port);

        $this->changeAndApply($host, fn(IcingaHost $host) => $host->setImports([$this->fx('linux')]));

        $this->assertSame($port->get('uuid'), $this->fetchFixtureLink($host, 'port'));
    }

    public function testRemovingATemplateRemovesAValueNothingElseProvides(): void
    {
        if ($this->skipForMissingDb()) {
            return;
        }

        $port = $this->createFixtureProperty('port');
        $this->createFixtureCommand('check-ping', 'object');
        $this->createFixtureHost('ssh', 'template', [], null, [$port]);
        $this->createFixtureHost('linux', 'template', [], 'check-ping');
        $host = $this->createFixtureHost('web01', 'object', ['ssh', 'linux']);
        $this->storeFixtureValue($host, 'port', '22', $port);

        $this->changeAndApply($host, fn(IcingaHost $host) => $host->setImports([$this->fx('linux')]));

        $this->assertFalse($this->fetchFixtureLink($host, 'port'));
    }

    public function testSwitchingTheCheckCommandRemovesOnlyWhatTheOldOneProvided(): void
    {
        if ($this->skipForMissingDb()) {
            return;
        }

        $warning = $this->createFixtureProperty('warning');
        $shared = $this->createFixtureProperty('shared');
        $this->createFixtureCommand('check-disk', 'object', [], [$warning, $shared]);
        $this->createFixtureCommand('check-load', 'object', [], [$shared]);
        $this->createFixtureHost('linux', 'template', [], 'check-disk');
        $host = $this->createFixtureHost('web01', 'object', ['linux']);
        $this->storeFixtureValue($host, 'warning', '80%', $warning);
        $this->storeFixtureValue($host, 'shared', 's', $shared);

        $this->changeAndApply($host, fn(IcingaHost $host) => $host->set('check_command', $this->fx('check-load')));

        $this->assertFalse($this->fetchFixtureLink($host, 'warning'));
        $this->assertSame($shared->get('uuid'), $this->fetchFixtureLink($host, 'shared'));
    }

    public function testClearingATemplateCommandCleansTheObjectsBelowAndLogsEachOfThem(): void
    {
        if ($this->skipForMissingDb()) {
            return;
        }

        $warning = $this->createFixtureProperty('warning');
        $this->createFixtureCommand('check-disk', 'object', [], [$warning]);
        $template = $this->createFixtureHost('linux', 'template', [], 'check-disk');
        $host = $this->createFixtureHost('web01', 'object', ['linux']);
        $own = $this->createFixtureHost('db01', 'object', ['linux'], 'check-disk');
        $this->storeFixtureValue($template, 'warning', '90%', $warning);
        $this->storeFixtureValue($host, 'warning', '80%', $warning);
        $this->storeFixtureValue($own, 'warning', '70%', $warning);

        $this->changeAndApply($template, fn(IcingaHost $template) => $template->set('check_command', null));

        $this->assertFalse($this->fetchFixtureLink($template, 'warning'));
        $this->assertFalse($this->fetchFixtureLink($host, 'warning'));
        $this->assertSame(
            $warning->get('uuid'),
            $this->fetchFixtureLink($own, 'warning'),
            'a host with its own check command that provides the property keeps its value'
        );
        $this->assertLatestLogEntryLacks($host, 'warning');
        $this->assertLatestLogEntryLacks($template, 'warning');
    }

    public function testSwitchingAServiceTemplateCommandCleansAppliedAndSetServices(): void
    {
        if ($this->skipForMissingDb()) {
            return;
        }

        $warning = $this->createFixtureProperty('warning');
        $this->createFixtureCommand('check-disk', 'object', [], [$warning]);
        $this->createFixtureCommand('check-ping', 'object');
        $template = $this->createFixtureService('generic', 'template', [], 'check-disk');
        $this->createFixtureHost('web01');
        $apply = $this->createFixtureService('disk-apply', 'apply', ['generic']);
        $onHost = $this->createFixtureService('disk', 'object', ['generic'], null, 'web01');
        $this->storeFixtureValue($apply, 'warning', '80%', $warning);
        $this->storeFixtureValue($onHost, 'warning', '80%', $warning);

        $this->changeAndApply(
            $template,
            fn(IcingaService $template) => $template->set('check_command', $this->fx('check-ping'))
        );

        $this->assertFalse($this->fetchFixtureLink($apply, 'warning'));
        $this->assertFalse($this->fetchFixtureLink($onHost, 'warning'));
    }

    public function testACommandLosingAnImportKeepsWhatAnotherImportStillProvides(): void
    {
        if ($this->skipForMissingDb()) {
            return;
        }

        $mode = $this->createFixtureProperty('mode');
        $this->createFixtureCommand('plugin-y', 'template', [], [$mode]);
        $this->createFixtureCommand('plugin-z', 'template', [], [$mode]);
        $command = $this->createFixtureCommand('check-mode', 'object', ['plugin-y', 'plugin-z']);
        $host = $this->createFixtureHost('web01', 'object', [], 'check-mode');
        $this->storeFixtureValue($host, 'mode', 'fast', $mode);

        $this->changeAndApply($command, fn(IcingaCommand $command) => $command->setImports([$this->fx('plugin-z')]));
        $this->assertSame($mode->get('uuid'), $this->fetchFixtureLink($host, 'mode'));

        $this->changeAndApply($command, fn(IcingaCommand $command) => $command->setImports([]));
        $this->assertFalse($this->fetchFixtureLink($host, 'mode'));
    }

    public function testDetachingFromACommandRemovesHostValuesNothingElseProvides(): void
    {
        if ($this->skipForMissingDb()) {
            return;
        }

        $warning = $this->createFixtureProperty('warning');
        $command = $this->createFixtureCommand('check-disk', 'object', [], [$warning]);
        $this->createFixtureCommand('check-load', 'object');
        $this->createFixtureHost('warnings', 'template', [], null, [$warning]);
        $user = $this->createFixtureHost('web01', 'object', [], 'check-disk');
        $stale = $this->createFixtureHost('web02', 'object', [], 'check-load');
        $kept = $this->createFixtureHost('web03', 'object', ['warnings'], 'check-disk');
        foreach ([$user, $stale, $kept] as $host) {
            $this->storeFixtureValue($host, 'warning', '80%', $warning);
        }

        $this->detachFixtureProperty($command, $warning);
        $dba = $this->getDb()->getDbAdapter();
        PropertyDetachmentCleaner::removeStaleValues(
            $command,
            [DbUtil::quoteBinaryCompat($warning->get('uuid'), $dba)],
            $this->getDb()
        );

        $this->assertFalse($this->fetchFixtureLink($user, 'warning'));
        $this->assertFalse(
            $this->fetchFixtureLink($stale, 'warning'),
            'a host that does not use the command loses a linked value it cannot reach either'
        );
        $this->assertSame($warning->get('uuid'), $this->fetchFixtureLink($kept, 'warning'));
    }

    public function testAChangeLinksValuesBelowThatNowReachAProperty(): void
    {
        if ($this->skipForMissingDb()) {
            return;
        }

        $mode = $this->createFixtureProperty('mode');
        $plain = $this->createFixtureProperty('plain');
        $this->createFixtureCommand('plugin', 'template', [], [$mode]);
        $command = $this->createFixtureCommand('check-mode', 'object');
        $this->createFixtureHost('source', 'template', [], null, [$plain]);
        $template = $this->createFixtureHost('empty', 'template');
        $host = $this->createFixtureHost('web01', 'object', ['empty'], 'check-mode');
        $this->storeFixtureValue($host, 'mode', 'fast');
        $this->storeFixtureValue($host, 'plain', 'value');

        $this->changeAndApply($command, fn(IcingaCommand $command) => $command->setImports([$this->fx('plugin')]));
        $this->changeAndApply($template, fn(IcingaHost $template) => $template->setImports([$this->fx('source')]));

        $this->assertSame($mode->get('uuid'), $this->fetchFixtureLink($host, 'mode'));
        $this->assertSame($plain->get('uuid'), $this->fetchFixtureLink($host, 'plain'));
        $this->assertSame(
            'value',
            IcingaHost::load($this->fx('web01'), $this->getDb())->get('vars.' . $this->fx('plain'))
        );
    }

    public function testUnlinkingInsteadOfRemovingKeepsTheValue(): void
    {
        if ($this->skipForMissingDb()) {
            return;
        }

        $warning = $this->createFixtureProperty('warning');
        $this->createFixtureCommand('check-disk', 'object', [], [$warning]);
        $template = $this->createFixtureHost('linux', 'template', [], 'check-disk');
        $host = $this->createFixtureHost('web01', 'object', ['linux']);
        $this->storeFixtureValue($host, 'warning', '80%', $warning);

        $this->changeAndApply($template, fn(IcingaHost $template) => $template->set('check_command', null), false);

        $this->assertNull($this->fetchFixtureLink($host, 'warning'));
    }

    public function testThePreviewNamesWhatACommandChangeAndAnImportRemovalWouldRemove(): void
    {
        if ($this->skipForMissingDb()) {
            return;
        }

        $warning = $this->createFixtureProperty('warning');
        $port = $this->createFixtureProperty('port');
        $this->createFixtureCommand('check-disk', 'object', [], [$warning]);
        $this->createFixtureCommand('check-ping', 'object');
        $this->createFixtureHost('ssh', 'template', [], null, [$port]);
        $template = $this->createFixtureHost('linux', 'template', ['ssh'], 'check-disk');
        $host = $this->createFixtureHost('web01', 'object', ['linux']);
        $own = $this->createFixtureHost('db01', 'object', ['linux'], 'check-disk');
        $this->storeFixtureValue($host, 'warning', '80%', $warning);
        $this->storeFixtureValue($own, 'warning', '80%', $warning);
        $this->storeFixtureValue($host, 'port', '22', $port);

        $this->assertSame(
            ['imports' => [], 'command' => [$this->fx('warning')]],
            PropertySourceChange::previewLoss($template, [$this->fx('ssh')], true, $this->fx('check-ping'))
        );
        $this->assertSame(
            ['imports' => [$this->fx('port')], 'command' => []],
            PropertySourceChange::previewLoss($template, [])
        );
        $this->assertSame(
            ['imports' => [], 'command' => []],
            PropertySourceChange::previewLoss($template, [$this->fx('ssh')], true, $this->fx('check-disk'))
        );
    }

    public function testClearingATemplateCommandLinksAValueBelowToTheCommandOfAnotherImport(): void
    {
        if ($this->skipForMissingDb()) {
            return;
        }

        $mode = $this->createFixtureProperty('mode');
        $this->createFixtureCommand('check-imp', 'object', [], [$mode]);
        $this->createFixtureCommand('check-base', 'object');
        $this->createFixtureHost('imp', 'template', [], 'check-imp');
        $base = $this->createFixtureHost('base', 'template', [], 'check-base');
        $host = $this->createFixtureHost('web01', 'object', ['imp', 'base']);
        $this->storeFixtureValue($host, 'mode', 'fast');

        $this->changeAndApply($base, fn(IcingaHost $base) => $base->set('check_command', null));

        $this->assertSame(
            $mode->get('uuid'),
            $this->fetchFixtureLink($host, 'mode'),
            'the host uses the check command of its other import now, which provides the property'
        );
    }

    public function testSettingATemplateCommandRemovesAValueBelowThatOnlyTheCommandOfAnotherImportProvided(): void
    {
        if ($this->skipForMissingDb()) {
            return;
        }

        $mode = $this->createFixtureProperty('mode');
        $shared = $this->createFixtureProperty('shared');
        $this->createFixtureCommand('check-imp', 'object', [], [$mode, $shared]);
        $this->createFixtureCommand('check-base', 'object', [], [$shared]);
        $this->createFixtureHost('imp', 'template', [], 'check-imp');
        $base = $this->createFixtureHost('base', 'template');
        $host = $this->createFixtureHost('web01', 'object', ['imp', 'base']);
        $this->storeFixtureValue($host, 'mode', 'fast', $mode);
        $this->storeFixtureValue($host, 'shared', 's', $shared);

        $this->assertSame(
            ['imports' => [], 'command' => [$this->fx('mode')]],
            PropertySourceChange::previewLoss($base, [], true, $this->fx('check-base')),
            'the host below loses the check command of its other import'
        );

        $this->changeAndApply($base, fn(IcingaHost $base) => $base->set('check_command', $this->fx('check-base')));

        $this->assertFalse($this->fetchFixtureLink($host, 'mode'), 'only the replaced command provided it');
        $this->assertSame($shared->get('uuid'), $this->fetchFixtureLink($host, 'shared'));
        $this->assertLatestLogEntryLacks($host, 'mode');
    }

    public function testAddingAnImportWithACommandRemovesAValueBelowThatOnlyTheReplacedCommandProvided(): void
    {
        if ($this->skipForMissingDb()) {
            return;
        }

        $mode = $this->createFixtureProperty('mode');
        $this->createFixtureCommand('check-imp', 'object', [], [$mode]);
        $this->createFixtureCommand('check-base', 'object');
        $this->createFixtureHost('imp', 'template', [], 'check-imp');
        $this->createFixtureHost('pinger', 'template', [], 'check-base');
        $base = $this->createFixtureHost('base', 'template');
        $host = $this->createFixtureHost('web01', 'object', ['imp', 'base']);
        $this->storeFixtureValue($host, 'mode', 'fast', $mode);

        $this->assertSame(
            ['imports' => [], 'command' => [$this->fx('mode')]],
            PropertySourceChange::previewLoss($base, [$this->fx('pinger')])
        );

        $this->changeAndApply($base, fn(IcingaHost $base) => $base->setImports([$this->fx('pinger')]));

        $this->assertFalse($this->fetchFixtureLink($host, 'mode'));
    }

    public function testUnlinkingInsteadOfRemovingAValueBelowThatTheReplacedCommandProvided(): void
    {
        if ($this->skipForMissingDb()) {
            return;
        }

        $mode = $this->createFixtureProperty('mode');
        $this->createFixtureCommand('check-imp', 'object', [], [$mode]);
        $this->createFixtureCommand('check-base', 'object');
        $this->createFixtureHost('imp', 'template', [], 'check-imp');
        $base = $this->createFixtureHost('base', 'template');
        $host = $this->createFixtureHost('web01', 'object', ['imp', 'base']);
        $this->storeFixtureValue($host, 'mode', 'fast', $mode);

        $this->changeAndApply(
            $base,
            fn(IcingaHost $base) => $base->set('check_command', $this->fx('check-base')),
            false
        );

        $this->assertNull($this->fetchFixtureLink($host, 'mode'), 'like sync, the value stays without a link');
    }

    public function testApplyRunsTheSameQueriesForOneOrManyHoldersBelow(): void
    {
        if ($this->skipForMissingDb()) {
            return;
        }

        $mode = $this->createFixtureProperty('mode');
        $this->createFixtureCommand('check-mode', 'object', [], [$mode]);
        $this->createFixtureCommand('check-ping', 'object');
        $queries = [];
        $commandQueries = [];
        foreach (['few' => [1, 0], 'many' => [25, 25]] as $prefix => [$below, $unrelated]) {
            $template = $this->createCommandHolders($prefix, $below, $unrelated, $mode);
            $template = IcingaHost::loadWithAutoIncId((int) $template->get('id'), $this->getDb());
            $template->set('check_command', $this->fx('check-ping'));
            // Storing a template resolves the group memberships of the hosts below, which is not counted
            $profiles = $this->profileQueries(function () use ($template, &$change) {
                $change = PropertySourceChange::capture($template);
            });
            $template->store();
            $profiles = array_merge($profiles, $this->profileQueries(fn() => $change->apply(false)));
            $queries[] = count($profiles);
            $commandQueries[] = count(array_filter(
                $profiles,
                fn($query) => str_contains($query, 'icinga_command_property')
            ));
        }

        $this->assertSame($queries[0], $queries[1]);
        $this->assertSame(
            [2, 2],
            $commandQueries,
            'one lookup of the command attachments for the sources before and one for the sources after'
        );
        foreach ($this->listFixtureHosts('many-host') as $host) {
            $this->assertNull($this->fetchFixtureLink($host, 'mode'), 'every host below lost the link');
        }

        foreach ($this->listFixtureHosts('many-other') as $host) {
            $this->assertSame(
                $mode->get('uuid'),
                $this->fetchFixtureLink($host, 'mode'),
                'hosts that do not import the template keep their link'
            );
        }
    }

    public function testThePreviewRunsTheSameQueriesForOneOrManyHoldersBelow(): void
    {
        if ($this->skipForMissingDb()) {
            return;
        }

        $mode = $this->createFixtureProperty('mode');
        $this->createFixtureCommand('check-mode', 'object', [], [$mode]);
        $this->createFixtureCommand('check-ping', 'object');
        $queries = [];
        foreach (['few' => [1, 0], 'many' => [25, 25]] as $prefix => [$below, $unrelated]) {
            $template = $this->createCommandHolders($prefix, $below, $unrelated, $mode);
            $queries[] = count($this->profileQueries(function () use ($template, &$preview) {
                $preview = PropertySourceChange::previewLoss($template, [], true, $this->fx('check-ping'));
            }));
            $this->assertSame(['imports' => [], 'command' => [$this->fx('mode')]], $preview);
        }

        $this->assertSame($queries[0], $queries[1]);
    }

    public function testDetachingRunsTheSameQueriesForOneOrManyHoldersThatKeepTheirValue(): void
    {
        if ($this->skipForMissingDb()) {
            return;
        }

        $mode = $this->createFixtureProperty('mode');
        $this->createFixtureCommand('check-mode', 'object', [], [$mode]);
        $this->createFixtureHost('keep', 'template', [], null, [$mode]);
        $queries = [];
        foreach (['few' => [1, 0], 'many' => [25, 25]] as $prefix => [$below, $unrelated]) {
            $template = $this->createFixtureHost($prefix, 'template', ['keep'], null, [$mode]);
            for ($i = 0; $i < $below; $i++) {
                $this->storeFixtureValue(
                    $this->createFixtureHost("$prefix-host$i", 'object', [$prefix]),
                    'mode',
                    'fast',
                    $mode
                );
            }

            for ($i = 0; $i < $unrelated; $i++) {
                $this->storeFixtureValue(
                    $this->createFixtureHost("$prefix-other$i", 'object', [], 'check-mode'),
                    'mode',
                    'fast',
                    $mode
                );
            }

            $this->detachFixtureProperty($template, $mode);
            $dba = $this->getDb()->getDbAdapter();
            $queries[] = count($this->profileQueries(fn() => PropertyDetachmentCleaner::removeStaleValues(
                $template,
                [DbUtil::quoteBinaryCompat($mode->get('uuid'), $dba)],
                $this->getDb()
            )));
        }

        $this->assertSame($queries[0], $queries[1]);
        foreach ($this->listFixtureHosts('many-host') as $host) {
            $this->assertSame($mode->get('uuid'), $this->fetchFixtureLink($host, 'mode'));
        }
    }

    public function testThePreviewNamesAValueInBothListsWhenATemplateAndTheCommandProvidedIt(): void
    {
        if ($this->skipForMissingDb()) {
            return;
        }

        $port = $this->createFixtureProperty('port');
        $this->createFixtureCommand('check-ssh', 'object', [], [$port]);
        $this->createFixtureCommand('check-ping', 'object');
        $this->createFixtureHost('ssh', 'template', [], null, [$port]);
        $host = $this->createFixtureHost('web01', 'object', ['ssh'], 'check-ssh');
        $this->storeFixtureValue($host, 'port', '22', $port);

        $this->assertSame(
            ['imports' => [$this->fx('port')], 'command' => [$this->fx('port')]],
            PropertySourceChange::previewLoss($host, [], true, $this->fx('check-ping'))
        );
    }

    public function testCaptureSkipsAnObjectWhoseSourcesAreUnchanged(): void
    {
        if ($this->skipForMissingDb()) {
            return;
        }

        $host = $this->createFixtureHost('web01');
        $host->set('display_name', 'Web 01');

        $this->assertNull(PropertySourceChange::capture($host));
    }

    public function tearDown(): void
    {
        $this->removeFixtures();
        parent::tearDown();
    }

    /**
     * Change a stored object like a form or the REST API does: capture, store, apply
     *
     * @template T of IcingaObject
     *
     * @param T                $object
     * @param callable(T): mixed $change
     * @param bool             $removeLostValues
     *
     * @return void
     */
    private function changeAndApply(IcingaObject $object, callable $change, bool $removeLostValues = true): void
    {
        $object = $object::loadWithAutoIncId((int) $object->get('id'), $this->getDb());
        $change($object);
        $sourceChange = PropertySourceChange::capture($object);
        $this->assertNotNull($sourceChange, 'the change must touch the imports or the check command');
        $object->store();
        $sourceChange->apply($removeLostValues);
    }

    /**
     * Create a host template with a check command and hosts that hold a linked value of the command's property
     *
     * @param string           $prefix    Unprefixed name of the template, and prefix of the host names
     * @param int              $below     Number of hosts that import the template
     * @param int              $unrelated Number of hosts that use the command without the template
     * @param DirectorProperty $property  The property that the command "check-mode" provides
     *
     * @return IcingaHost The template
     */
    private function createCommandHolders(
        string $prefix,
        int $below,
        int $unrelated,
        DirectorProperty $property
    ): IcingaHost {
        $template = $this->createFixtureHost($prefix, 'template', [], 'check-mode');
        for ($i = 0; $i < $below; $i++) {
            $host = $this->createFixtureHost("$prefix-host$i", 'object', [$prefix]);
            $this->storeFixtureValue($host, 'mode', 'fast', $property);
        }

        for ($i = 0; $i < $unrelated; $i++) {
            $host = $this->createFixtureHost("$prefix-other$i", 'object', [], 'check-mode');
            $this->storeFixtureValue($host, 'mode', 'fast', $property);
        }

        return $template;
    }

    /**
     * Load the stored fixture hosts whose names start with the given prefix
     *
     * @param string $prefix Unprefixed start of the names
     *
     * @return IcingaHost[]
     */
    private function listFixtureHosts(string $prefix): array
    {
        $dba = $this->getDb()->getDbAdapter();
        $ids = $dba->fetchCol(
            $dba->select()->from('icinga_host', ['id'])->where('object_name LIKE ?', $this->fx($prefix) . '%')
        );
        $hosts = [];
        foreach ($ids as $id) {
            $hosts[] = IcingaHost::loadWithAutoIncId((int) $id, $this->getDb());
        }

        $this->assertNotEmpty($hosts);

        return $hosts;
    }

    /**
     * Get the queries the given callback runs
     *
     * @param callable $callback
     *
     * @return string[]
     */
    private function profileQueries(callable $callback): array
    {
        $profiler = $this->getDb()->getDbAdapter()->getProfiler();
        $profiler->setEnabled(true);
        $profiler->clear();
        try {
            $callback();

            return array_map(fn($profile) => $profile->getQuery(), $profiler->getQueryProfiles() ?: []);
        } finally {
            // The database connection is shared with the other tests
            $profiler->setEnabled(false);
            $profiler->clear();
        }
    }

    /**
     * Assert that the latest activity log entry of an object no longer lists a variable
     *
     * @param IcingaObject $object
     * @param string       $name   Unprefixed variable name
     *
     * @return void
     */
    private function assertLatestLogEntryLacks(IcingaObject $object, string $name): void
    {
        $dba = $this->getDb()->getDbAdapter();
        $entry = $dba->fetchRow(
            $dba->select()
                ->from('director_activity_log', ['action_name', 'old_properties', 'new_properties'])
                ->where('object_name = ?', $object->getObjectName())
                ->order('id DESC')
                ->limit(1)
        );

        $this->assertNotFalse($entry);
        $this->assertSame('modify', $entry->action_name);
        $this->assertStringContainsString($this->fx($name), $entry->old_properties);
        $this->assertStringNotContainsString($this->fx($name), $entry->new_properties);
    }
}

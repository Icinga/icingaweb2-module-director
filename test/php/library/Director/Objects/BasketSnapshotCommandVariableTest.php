<?php

// SPDX-FileCopyrightText: 2026 Icinga GmbH <https://icinga.com>
// SPDX-License-Identifier: GPL-3.0-or-later

namespace Tests\Icinga\Module\Director\Objects;

use Icinga\Module\Director\Data\Exporter;
use Icinga\Module\Director\DirectorObject\Automation\BasketSnapshot;
use Icinga\Module\Director\Objects\DirectorProperty;
use Icinga\Module\Director\Objects\IcingaObject;
use Icinga\Module\Director\Test\BaseTestCase;
use Ramsey\Uuid\Uuid;
use Tests\Icinga\Module\Director\Lib\CustomVariable\CommandVariableFixture;

class BasketSnapshotCommandVariableTest extends BaseTestCase
{
    use CommandVariableFixture;

    public function testRestoreThatMovesAPropertyFromACommandToAHostTemplateKeepsTheHostValue(): void
    {
        if ($this->skipForMissingDb()) {
            return;
        }

        $mode = $this->createFixtureProperty('mode');
        $command = $this->createFixtureCommand('check-mode', 'object', [], [$mode]);
        $template = $this->createFixtureHost('base', 'template', [], 'check-mode');
        $host = $this->createFixtureHost('web01', 'object', ['base']);
        $this->storeFixtureValue($host, 'mode', 'fast', $mode);

        $this->restoreMove($mode, $command, 'Command', $template, 'HostTemplate');

        $this->assertSame(
            $mode->get('uuid'),
            $this->fetchFixtureLink($host, 'mode'),
            'the host reaches the property through its template once the restore is done'
        );
    }

    public function testRestoreThatMovesAPropertyFromACommandTemplateToTheCommandKeepsTheHostValue(): void
    {
        if ($this->skipForMissingDb()) {
            return;
        }

        $mode = $this->createFixtureProperty('mode');
        $base = $this->createFixtureCommand('plugin', 'template', [], [$mode]);
        $command = $this->createFixtureCommand('check-mode', 'object', ['plugin']);
        $host = $this->createFixtureHost('web01', 'object', [], 'check-mode');
        $this->storeFixtureValue($host, 'mode', 'fast', $mode);

        $this->restoreMove($mode, $base, 'CommandTemplate', $command, 'Command');

        $this->assertSame($mode->get('uuid'), $this->fetchFixtureLink($host, 'mode'));
    }

    public function testRestoreThatDetachesAPropertyFromACommandStillRemovesTheHostValue(): void
    {
        if ($this->skipForMissingDb()) {
            return;
        }

        $mode = $this->createFixtureProperty('mode');
        $command = $this->createFixtureCommand('check-mode', 'object', [], [$mode]);
        $host = $this->createFixtureHost('web01', 'object', [], 'check-mode');
        $this->storeFixtureValue($host, 'mode', 'fast', $mode);

        $this->detachFixtureProperty($command, $mode);
        $json = $this->exportSnapshot($mode, ['Command' => $command]);
        $this->attachFixtureProperty($command, $mode);

        BasketSnapshot::restoreJson($json, $this->getDb());

        $this->assertFalse($this->fetchFixtureLink($host, 'mode'), 'nothing provides the property any longer');
    }

    public function tearDown(): void
    {
        $this->removeFixtures();
        parent::tearDown();
    }

    /**
     * Restore a snapshot in which a property moved from one object to another
     *
     * @param DirectorProperty $property
     * @param IcingaObject     $from     The object that attaches the property now
     * @param string           $fromType Basket type of $from
     * @param IcingaObject     $to       The object that attaches the property in the snapshot
     * @param string           $toType   Basket type of $to
     *
     * @return void
     */
    protected function restoreMove(
        DirectorProperty $property,
        IcingaObject $from,
        string $fromType,
        IcingaObject $to,
        string $toType
    ): void {
        $this->detachFixtureProperty($from, $property);
        $this->attachFixtureProperty($to, $property);
        $json = $this->exportSnapshot($property, [$fromType => $from, $toType => $to]);
        $this->detachFixtureProperty($to, $property);
        $this->attachFixtureProperty($from, $property);

        BasketSnapshot::restoreJson($json, $this->getDb());
    }

    /**
     * Export a snapshot of the given objects and property as they are stored now
     *
     * @param DirectorProperty            $property
     * @param array<string, IcingaObject> $objects  Objects by basket type
     *
     * @return string
     */
    protected function exportSnapshot(DirectorProperty $property, array $objects): string
    {
        $exporter = new Exporter($this->getDb());
        $snapshot = ['CustomVariable' => [Uuid::fromBytes($property->get('uuid'))->toString() => $property->export()]];
        foreach ($objects as $type => $object) {
            $object = $object::loadWithAutoIncId((int) $object->get('id'), $this->getDb());
            $snapshot[$type] = [$object->getObjectName() => $exporter->export($object)];
        }

        return json_encode($snapshot);
    }
}

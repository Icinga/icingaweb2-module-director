<?php

// SPDX-FileCopyrightText: 2026 Icinga GmbH <https://icinga.com>
// SPDX-License-Identifier: GPL-3.0-or-later

namespace Tests\Icinga\Module\Director\Web\Controller;

use Icinga\Application\Config;
use Icinga\Module\Director\Forms\CustomVariablesForm;
use Icinga\Module\Director\Objects\DirectorProperty;
use Icinga\Module\Director\Objects\IcingaCommand;
use Icinga\Module\Director\Objects\IcingaHost;
use Icinga\Module\Director\Test\BaseTestCase;
use Ramsey\Uuid\Uuid;
use Tests\Icinga\Module\Director\Lib\CustomVariable\CommandVariableFixture;
use Tests\Icinga\Module\Director\Lib\Web\TestableHostController;

/**
 * Multipart update that adds a variable to the Custom Variables tab of a template
 */
class ObjectControllerNewVarUpdateTest extends BaseTestCase
{
    use CommandVariableFixture;

    protected ?IcingaHost $template = null;

    /** @var array<string, DirectorProperty> Properties by unprefixed name */
    protected array $properties = [];

    public function setUp(): void
    {
        parent::setUp();
        if (! $this->hasDb()) {
            return;
        }

        // The dictionary items open their own connection, point it at the resource BaseTestCase uses
        Config::module('director')->setSection('db', ['resource' => static::getDbResourceName()]);

        foreach (['os', 'wrta', 'location'] as $name) {
            $this->properties[$name] = $this->createFixtureProperty($name);
        }

        $this->createFixtureHost('linux', 'template', [], null, [$this->properties['os'], $this->properties['wrta']]);
        $this->template = $this->createFixtureHost('web', 'template', ['linux']);
    }

    public function testTakingOverAnInheritedRowReplacesItAndKeepsTheItemCount(): void
    {
        if ($this->skipForMissingDb()) {
            return;
        }

        $parts = $this->sendNewVarUpdate('wrta', 2);

        $this->assertSame(
            [
                'new-var-slot-2',
                'custom-var-row-1',
                'properties-item-count',
                'add-custom-var-button',
                'added-var-uuids',
                'required-var-uuids',
            ],
            array_keys($parts),
            'the inherited row is emptied, the new row goes into the own box'
        );
        $this->assertStringContainsString('name="properties[1][var]"', $parts['new-var-slot-2']);
        $this->assertStringContainsString('id="new-var-slot-3"', $parts['new-var-slot-2']);
        $this->assertSame('', trim($parts['custom-var-row-1']));
        $this->assertStringContainsString('value="2"', $parts['properties-item-count']);
        $this->assertStringContainsString('nextSlotIndex=3', $parts['add-custom-var-button']);
    }

    public function testRowsRemovedOnScreenMoveTheTakenOverRowUp(): void
    {
        if ($this->skipForMissingDb()) {
            return;
        }

        $parts = $this->sendNewVarUpdate('wrta', 2, [$this->fx('os')]);

        $this->assertStringContainsString('name="properties[0][var]"', $parts['new-var-slot-2']);
        $this->assertArrayHasKey('custom-var-row-0', $parts);
        $this->assertStringContainsString('value="1"', $parts['properties-item-count']);
        $this->assertStringContainsString(
            'removedVars=' . rawurlencode($this->fx('os')),
            $parts['add-custom-var-button'],
            'the removed rows travel through the next dialog too'
        );
    }

    public function testAddingAVariableNothingProvidesAppendsItAndRaisesTheItemCount(): void
    {
        if ($this->skipForMissingDb()) {
            return;
        }

        $parts = $this->sendNewVarUpdate('location', 2);

        $this->assertSame(
            [
                'new-var-slot-2',
                'properties-item-count',
                'add-custom-var-button',
                'added-var-uuids',
                'required-var-uuids',
            ],
            array_keys($parts)
        );
        $this->assertStringContainsString('name="properties[2][var]"', $parts['new-var-slot-2']);
        $this->assertStringContainsString('value="3"', $parts['properties-item-count']);
    }

    public function testTheTakenOverRowShowsTheInheritedValueAsPlaceholder(): void
    {
        if ($this->skipForMissingDb()) {
            return;
        }

        $linux = IcingaHost::load($this->fx('linux'), $this->getDb());
        $this->storeFixtureValue($linux, 'os', 'Linux', $this->properties['os']);

        $parts = $this->sendNewVarUpdate('os', 2);

        $this->assertSame(
            1,
            preg_match('/<input[^>]*name="properties\[0]\[var]"[^>]*>/', $parts['new-var-slot-2'], $input)
        );
        $this->assertStringContainsString('placeholder="Linux', $input[0]);
        $this->assertStringNotContainsString('value=', $input[0], 'the template has no own value yet');
    }

    public function testACommandDoesNotRequireARowItAttachesItselfEvenWhenTheRequestAsksForIt(): void
    {
        if ($this->skipForMissingDb()) {
            return;
        }

        $cpl = $this->createFixtureProperty('cpl');
        $this->createFixtureCommand('ping-base', 'template');
        $command = $this->createFixtureCommand('ping-child', 'object', ['ping-base']);
        $this->attachFixtureProperty(IcingaCommand::load($this->fx('ping-base'), $this->getDb()), $cpl, true);

        // A command has no toggle for Required, so a required uuid can only come from a tampered URL
        $row = [
            'key_name'      => $cpl->get('key_name'),
            'uuid'          => $cpl->get('uuid'),
            'value_type'    => 'string',
            'label'         => 'cpl',
            'allow_removal' => true,
            'required'      => true,
            'new'           => true,
            'group'         => 'own',
        ];
        $html = (new CustomVariablesForm($command, [$row['key_name'] => $row]))
            ->setRequiredVarUuids([Uuid::fromBytes($cpl->get('uuid'))->toString()])
            ->prepareNewPropertyRow($row, 0)
            ->render();

        $this->assertSame(1, preg_match('/<input[^>]*name="properties\[0]\[var]"[^>]*>/', $html, $input));
        $this->assertStringNotContainsString('required', $input[0]);
    }

    public function tearDown(): void
    {
        $this->removeFixtures();
        parent::tearDown();
    }

    /**
     * Send the multipart update that adds a variable to the template and get the parts it queues
     *
     * @param string   $name          Unprefixed name of the added property
     * @param int      $nextSlotIndex The number of the placeholder the new row goes into
     * @param string[] $removedVars   Names of the rows removed on screen
     *
     * @return array<string, string> The content of each part, by the id of the container it replaces
     */
    protected function sendNewVarUpdate(string $name, int $nextSlotIndex, array $removedVars = []): array
    {
        $uuid = Uuid::fromBytes($this->properties[$name]->get('uuid'))->toString();
        $template = IcingaHost::load($this->fx('web'), $this->getDb());
        $controller = TestableHostController::create($this->getDb());
        $rows = static::callMethod($controller, 'getObjectCustomProperties', [$template, false, [$uuid]]);
        $form = (new CustomVariablesForm($template, $rows))->setAddedVarUuids([$uuid]);

        static::callMethod(
            $controller,
            'sendNewVarMultipartUpdate',
            [$template, $form, $uuid, $nextSlotIndex, [$uuid], [], $removedVars]
        );

        return $controller->getPartsById();
    }
}

<?php

// SPDX-FileCopyrightText: 2026 Icinga GmbH <https://icinga.com>
// SPDX-License-Identifier: GPL-3.0-or-later

namespace Tests\Icinga\Module\Director\Form;

use Icinga\Application\Config;
use Icinga\Module\Director\Forms\CustomVariablesForm;
use Icinga\Module\Director\Objects\DirectorProperty;
use Icinga\Module\Director\Objects\IcingaObject;
use Icinga\Module\Director\Test\BaseTestCase;
use Tests\Icinga\Module\Director\Lib\CustomVariable\CommandVariableFixture;

/**
 * Renders the real form with rows in boxes by origin
 */
class CustomVariablesFormGroupsTest extends BaseTestCase
{
    use CommandVariableFixture;

    public function setUp(): void
    {
        parent::setUp();

        // The dictionary items open their own connection, point it at the resource BaseTestCase uses
        if ($this->hasDb()) {
            Config::module('director')->setSection('db', ['resource' => static::getDbResourceName()]);
        }
    }

    public function testRowsShowInTheirBoxesAndOnlyTheCommandBoxStartsFolded(): void
    {
        if ($this->skipForMissingDb()) {
            return;
        }

        $owner = $this->createFixtureProperty('owner');
        $warning = $this->createFixtureProperty('warning');
        $template = $this->createFixtureHost('linux', 'template');

        $html = $this->buildForm($template, [
            $this->row($owner, 'host:1'),
            $this->row($warning, 'command'),
        ], [
            $this->group('own', 'Added in this template "linux"', true, true),
            $this->group('host:1', 'Added by template "base"'),
            $this->group('host:2', 'Added by template "empty"'),
            $this->group('command', 'Added by command "check-disk"', false),
        ])->render();

        preg_match_all('/<details([^>]*)>\s*<summary>([^<]*)<\/summary>/', $html, $boxes, PREG_SET_ORDER);
        $this->assertSame(
            [
                'Added in this template &quot;linux&quot;',
                'Added by template &quot;base&quot;',
                'Added by command &quot;check-disk&quot;',
            ],
            array_column($boxes, 2),
            'boxes keep their order, a box without rows does not show'
        );
        $this->assertStringContainsString('own-group', $boxes[0][1]);
        $this->assertStringContainsString(' open', $boxes[1][1]);
        $this->assertStringNotContainsString(' open', $boxes[2][1]);
        $this->assertMatchesRegularExpression(
            '/<details[^>]*own-group[^>]*>.*id="new-var-slot-2".*<\/details>\s*<details/s',
            $html,
            'new rows land in the own box'
        );
    }

    public function testBoxHeadingsEscapeObjectNames(): void
    {
        if ($this->skipForMissingDb()) {
            return;
        }

        $owner = $this->createFixtureProperty('owner');
        $template = $this->createFixtureHost('linux', 'template');

        $html = $this->buildForm(
            $template,
            [$this->row($owner, 'host:1')],
            [$this->group('host:1', 'Added by template "<b>x</b>"')]
        )->render();

        $this->assertStringContainsString('&lt;b&gt;x&lt;/b&gt;', $html);
        $this->assertStringNotContainsString('<b>x</b>', $html);
    }

    public function tearDown(): void
    {
        $this->removeFixtures();
        parent::tearDown();
    }

    /**
     * @param array<int, array<string, mixed>>                                         $rows
     * @param array<int, array{key: string, label: string, open: bool, always: bool}> $groups
     */
    private function buildForm(IcingaObject $object, array $rows, array $groups): CustomVariablesForm
    {
        $keyed = [];
        foreach ($rows as $row) {
            $keyed[$row['key_name']] = $row;
        }

        $form = (new CustomVariablesForm($object, $keyed))->setGroups($groups)->setIsAdmin(true);
        $form->load($rows);

        return $form;
    }

    /**
     * @return array<string, mixed>
     */
    private function row(DirectorProperty $property, string $group, bool $required = false): array
    {
        return [
            'key_name'      => $property->get('key_name'),
            'uuid'          => $property->get('uuid'),
            'value_type'    => 'string',
            'label'         => $property->get('label'),
            'allow_removal' => false,
            'required'      => $required,
            'group'         => $group,
        ];
    }

    /**
     * @return array{key: string, label: string, open: bool, always: bool}
     */
    private function group(string $key, string $label, bool $open = true, bool $always = false): array
    {
        return ['key' => $key, 'label' => $label, 'open' => $open, 'always' => $always];
    }
}

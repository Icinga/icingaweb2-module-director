<?php

// SPDX-FileCopyrightText: 2026 Icinga GmbH <https://icinga.com>
// SPDX-License-Identifier: GPL-3.0-or-later

namespace Tests\Icinga\Module\Director\CustomVariable;

use Icinga\Module\Director\CustomVariable\CustomPropertyPlacement;
use Icinga\Module\Director\CustomVariable\CustomPropertySource;
use Icinga\Module\Director\CustomVariable\CustomPropertySourceKind;
use Icinga\Module\Director\Test\BaseTestCase;

class CustomPropertyPlacementTest extends BaseTestCase
{
    public function testOwnAttachmentWinsAndDecidesRequired(): void
    {
        $placed = CustomPropertyPlacement::place([
            $this->source(CustomPropertySourceKind::Own, 'host', 'linux-child', ['owner' => false]),
            $this->source(CustomPropertySourceKind::Template, 'host', 'linux-base', ['owner' => true]),
        ]);

        $this->assertSame('linux-child', $placed['owner']['source']->name);
        $this->assertTrue($placed['owner']['allow_removal']);
        $this->assertFalse(
            $placed['owner']['required'],
            'the own attachment shows its own flag, not the one of the template'
        );
    }

    public function testATemplateRequirementStaysInForceWithoutAnOwnAttachment(): void
    {
        $placed = CustomPropertyPlacement::place([
            $this->source(CustomPropertySourceKind::Own, 'host', 'web01'),
            $this->source(CustomPropertySourceKind::Template, 'host', 'linux-base', ['owner' => true]),
            $this->source(CustomPropertySourceKind::Template, 'host', 'linux-child', ['owner' => false]),
        ]);

        $this->assertSame('linux-child', $placed['owner']['source']->name, 'the template imported last shows it');
        $this->assertFalse($placed['owner']['allow_removal']);
        $this->assertTrue($placed['owner']['required'], 'a child template cannot relax the requirement');
    }

    public function testTheTemplateGivingTheValueWinsOverTheOneImportedLast(): void
    {
        $placed = CustomPropertyPlacement::place(
            [
                $this->source(CustomPropertySourceKind::Own, 'host', 'web01'),
                $this->source(CustomPropertySourceKind::Template, 'host', 'with-value', ['owner' => false]),
                $this->source(CustomPropertySourceKind::Template, 'host', 'without-value', ['owner' => false]),
            ],
            ['owner' => 'with-value']
        );

        $this->assertSame('with-value', $placed['owner']['source']->name);
    }

    public function testTheCheckCommandOnlyGetsWhatNoTemplateAttaches(): void
    {
        $placed = CustomPropertyPlacement::place([
            $this->source(CustomPropertySourceKind::Own, 'service', 'disk'),
            $this->source(CustomPropertySourceKind::Template, 'service', 'generic-service', ['owner' => false]),
            $this->source(CustomPropertySourceKind::Command, 'command', 'check_disk', [
                'owner'   => false,
                'warning' => true,
            ]),
            $this->source(CustomPropertySourceKind::Command, 'command', 'plugin-base', ['timeout' => true]),
        ]);

        $this->assertSame('generic-service', $placed['owner']['source']->name);
        $this->assertSame('check_disk', $placed['warning']['source']->name);
        $this->assertSame('plugin-base', $placed['timeout']['source']->name);
        $this->assertFalse($placed['warning']['allow_removal']);
        $this->assertFalse($placed['warning']['required'], 'a command never makes a service variable required');
        $this->assertFalse($placed['timeout']['required']);
    }

    public function testACommandTemplateRequirementAppliesToTheCommandUntilItAttachesTheVariable(): void
    {
        $base = $this->source(CustomPropertySourceKind::Template, 'command', 'plugin-base', ['timeout' => true]);

        $inherited = CustomPropertyPlacement::place([
            $this->source(CustomPropertySourceKind::Own, 'command', 'check_disk'),
            $base,
        ]);
        $taken = CustomPropertyPlacement::place([
            $this->source(CustomPropertySourceKind::Own, 'command', 'check_disk', ['timeout' => false]),
            $base,
        ]);

        $this->assertTrue($inherited['timeout']['required']);
        $this->assertFalse($taken['timeout']['required']);
        $this->assertTrue($taken['timeout']['allow_removal']);
    }

    public function testNoSourcesPlaceNothing(): void
    {
        $this->assertSame([], CustomPropertyPlacement::place([]));
    }

    /**
     * @param array<string, bool> $attachments Required flags by property name
     */
    private function source(
        CustomPropertySourceKind $kind,
        string $type,
        string $name,
        array $attachments = []
    ): CustomPropertySource {
        $rows = [];
        foreach ($attachments as $key => $required) {
            $rows[$key] = [
                'uuid'       => md5($key, true),
                'key_name'   => $key,
                'value_type' => 'string',
                'label'      => null,
                'required'   => $required,
            ];
        }

        $isTemplate = $kind !== CustomPropertySourceKind::Own;

        return new CustomPropertySource($kind, $type, crc32($name), $name, $isTemplate, $rows);
    }
}

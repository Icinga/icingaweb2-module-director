<?php

// SPDX-FileCopyrightText: 2026 Daniel Vedovato
// SPDX-License-Identifier: GPL-3.0-or-later

namespace Tests\Icinga\Module\Director\Web\Widget;

use Icinga\Module\Director\Test\BaseTestCase;
use Icinga\Module\Director\Web\Widget\ActivityLogInfo;
use ReflectionClass;

class ActivityLogInfoTest extends BaseTestCase
{
    public function testTemplateChoiceDiffUsesLoggedProperties(): void
    {
        $info = (new ReflectionClass(ActivityLogInfo::class))->newInstanceWithoutConstructor();
        self::setActivityProperties($info, 
            '{"object_name":"test-choice","min_required":0,"members":["host-a"]}',
            '{"object_name":"test-choice","min_required":1,"members":["host-a","host-b"]}'
        );

        $diffs = self::callMethod($info, 'getTemplateChoiceDiffs', ['diff']);

        $this->assertCount(1, $diffs);
        $html = reset($diffs)->render();
        $this->assertStringContainsString('min_required', $html);
        $this->assertStringContainsString('host-a', $html);
        $this->assertStringContainsString('host-b', $html);
        $this->assertStringNotContainsString('Failed to render this object', $html);
    }

    public function testNewAndFormerTabsOnlyShowTheirOwnSnapshot(): void
    {
        $info = (new ReflectionClass(ActivityLogInfo::class))->newInstanceWithoutConstructor();
        self::setActivityProperties($info, 
            '{"description":"former-value"}',
            '{"description":"new-value"}'
        );

        $new = self::callMethod($info, 'getTemplateChoiceDiffs', ['new']);
        $old = self::callMethod($info, 'getTemplateChoiceDiffs', ['old']);

        $this->assertStringContainsString('new-value', reset($new)->render());
        $this->assertStringNotContainsString('former-value', reset($new)->render());
        $this->assertStringContainsString('former-value', reset($old)->render());
        $this->assertStringNotContainsString('new-value', reset($old)->render());
    }

    public function testEmptySnapshotDoesNotRenderAFakeObject(): void
    {
        $info = (new ReflectionClass(ActivityLogInfo::class))->newInstanceWithoutConstructor();
        self::setActivityProperties($info, null, '{"object_name":"new-choice"}');

        $diffs = self::callMethod($info, 'getTemplateChoiceDiffs', ['new']);

        $this->assertCount(1, $diffs);
        $this->assertStringContainsString('new-choice', reset($diffs)->render());
    }
    private static function setActivityProperties(ActivityLogInfo $info, ?string $old, ?string $new): void
    {
        (new ReflectionClass(ActivityLogInfo::class))->getProperty('entry')->setValue(
            $info,
            (object) [
                'old_properties' => $old,
                'new_properties' => $new,
            ]
        );
    }

}

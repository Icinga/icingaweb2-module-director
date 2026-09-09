<?php

// SPDX-FileCopyrightText: 2026 Icinga GmbH <https://icinga.com>
// SPDX-License-Identifier: GPL-3.0-or-later

namespace Tests\Icinga\Module\Director\Web\Controller;

use Icinga\Module\Director\Controllers\HostController;
use Icinga\Module\Director\Test\BaseTestCase;
use ReflectionClass;

class ObjectControllerCustomPropertiesTest extends BaseTestCase
{
    public function testDirectAttachmentWinsOverAnAncestorRequiringIt(): void
    {
        $controller = $this->newController();

        $property = $this->callResolve($controller, [
            ['uuid' => 'object', 'required' => false],
            ['uuid' => 'ancestor', 'required' => true],
        ], 'object');

        $this->assertTrue($property['allow_removal']);
        $this->assertFalse(
            $property['required'],
            'a direct attachment on the object itself must decide required, not an ancestor'
        );
    }

    public function testAnyAncestorRequiringItIsEnoughWithoutADirectAttachment(): void
    {
        $controller = $this->newController();

        $property = $this->callResolve($controller, [
            ['uuid' => 'ancestor-a', 'required' => false],
            ['uuid' => 'ancestor-b', 'required' => true],
        ], 'object');

        $this->assertFalse($property['allow_removal']);
        $this->assertTrue(
            $property['required'],
            'one ancestor requiring it is enough, an object cannot drop a requirement it inherited'
        );
    }

    public function testNeitherRequiredNorRemovableWithoutAnyAttachment(): void
    {
        $controller = $this->newController();

        $property = $this->callResolve($controller, [], 'object');

        $this->assertFalse($property['allow_removal']);
        $this->assertFalse($property['required']);
    }

    private function newController(): HostController
    {
        return (new ReflectionClass(HostController::class))->newInstanceWithoutConstructor();
    }

    /**
     * @param array<int, array{uuid: string, required: bool}> $attachments
     */
    private function callResolve(HostController $controller, array $attachments, string $objectUuid): array
    {
        return self::callMethod($controller, 'resolveEffectiveAttachment', [
            ['key_name' => 'whatever'],
            $attachments,
            $objectUuid,
        ]);
    }
}

<?php

// SPDX-FileCopyrightText: 2026 Icinga GmbH <https://icinga.com>
// SPDX-License-Identifier: GPL-3.0-or-later

namespace Tests\Icinga\Module\Director\Web\Controller;

use Icinga\Module\Director\Controllers\HostController;
use Icinga\Module\Director\Objects\IcingaHost;
use Icinga\Module\Director\Test\BaseTestCase;
use Icinga\Module\Director\Web\Controller\ActionController;
use ReflectionClass;
use ReflectionProperty;

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

    public function testParentTemplatesAreLoadedByTheirId(): void
    {
        if ($this->skipForMissingDb()) {
            return;
        }

        $db = $this->getDb();
        IcingaHost::create([
            'object_name' => '___TEST___generic-linux-template',
            'object_type' => 'template',
        ], $db)->store();

        $host = IcingaHost::create([
            'object_name' => '___TEST___web01.example.com',
            'object_type' => 'object',
            'imports'     => '___TEST___generic-linux-template',
        ], $db);
        $host->store();

        $controller = $this->newController();
        (new ReflectionProperty(ActionController::class, 'db'))->setValue($controller, $db);

        $this->assertSame([], self::callMethod($controller, 'getObjectCustomProperties', [$host]));
    }

    public function tearDown(): void
    {
        if ($this->hasDb()) {
            $db = $this->getDb();
            foreach (['___TEST___web01.example.com', '___TEST___generic-linux-template'] as $name) {
                if (IcingaHost::exists($name, $db)) {
                    IcingaHost::load($name, $db)->delete();
                }
            }
        }

        parent::tearDown();
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

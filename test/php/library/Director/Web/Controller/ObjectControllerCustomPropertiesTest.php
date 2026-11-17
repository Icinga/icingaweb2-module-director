<?php

// SPDX-FileCopyrightText: 2026 Icinga GmbH <https://icinga.com>
// SPDX-License-Identifier: GPL-3.0-or-later

namespace Tests\Icinga\Module\Director\Web\Controller;

use Icinga\Exception\NotFoundError;
use Icinga\Module\Director\Controllers\HostController;
use Icinga\Module\Director\Objects\IcingaCommand;
use Icinga\Module\Director\Objects\IcingaHost;
use Icinga\Module\Director\Test\BaseTestCase;
use ReflectionClass;

class ObjectControllerCustomPropertiesTest extends BaseTestCase
{
    public function testAddingAVariableToAPlainHostIsRejected(): void
    {
        $host = IcingaHost::create(['object_name' => 'web01.example.com', 'object_type' => 'object']);

        $this->expectException(NotFoundError::class);

        self::callMethod($this->newController(), 'assertDefinesCustomProperties', [$host]);
    }

    public function testAddingAVariableToATemplateIsAllowed(): void
    {
        $host = IcingaHost::create(['object_name' => 'web-servers', 'object_type' => 'template']);

        self::callMethod($this->newController(), 'assertDefinesCustomProperties', [$host]);

        $this->addToAssertionCount(1);
    }

    public function testAddingAVariableToAnExternalCommandIsAllowed(): void
    {
        $command = IcingaCommand::create(['object_name' => 'ping4', 'object_type' => 'external_object']);

        self::callMethod($this->newController(), 'assertDefinesCustomProperties', [$command]);

        $this->addToAssertionCount(1);
    }

    private function newController(): HostController
    {
        return (new ReflectionClass(HostController::class))->newInstanceWithoutConstructor();
    }
}

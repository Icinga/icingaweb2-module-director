<?php

// SPDX-FileCopyrightText: 2026 Icinga GmbH <https://icinga.com>
// SPDX-License-Identifier: GPL-3.0-or-later

namespace Tests\Icinga\Module\Director\Form;

use Icinga\Application\Icinga;
use Icinga\Module\Director\Forms\IcingaTimePeriodForm;
use Icinga\Module\Director\Objects\IcingaTimePeriod;
use Icinga\Module\Director\Test\BaseTestCase;

class IcingaTimePeriodFormTest extends BaseTestCase
{
    private const PARENT_NAME = '___TEST___timeperiod_parent';
    private const CHILD_NAME = '___TEST___timeperiod_child';

    public function setUp(): void
    {
        parent::setUp();

        Icinga::app()->getModuleManager()->loadModule('director');
    }

    public function testSavingATimePeriodWithARemovedImportPersistsIt(): void
    {
        if ($this->skipForMissingDb()) {
            return;
        }

        $db = $this->getDb();

        $parent = IcingaTimePeriod::create(['object_name' => self::PARENT_NAME, 'object_type' => 'template'], $db);
        $parent->store();
        $child = IcingaTimePeriod::create(['object_name' => self::CHILD_NAME, 'object_type' => 'object'], $db);
        $child->setImports([self::PARENT_NAME]);
        $child->store();

        try {
            $child->setImports([]);
            (new IcingaTimePeriodForm())->setDb($db)->setObject($child)->setApiRequest()->onSuccess();

            $this->assertSame([], IcingaTimePeriod::load(self::CHILD_NAME, $db)->getImports());
        } finally {
            $child->delete();
            $parent->delete();
        }
    }
}

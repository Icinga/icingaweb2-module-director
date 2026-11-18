<?php

// SPDX-FileCopyrightText: 2026 Icinga GmbH <https://icinga.com>
// SPDX-License-Identifier: GPL-3.0-or-later

namespace Tests\Icinga\Module\Director\Objects;

use Icinga\Module\Director\Objects\IcingaHost;
use Icinga\Module\Director\Test\BaseTestCase;

class IcingaObjectToPlainObjectTest extends BaseTestCase
{
    private const TEMPLATE = '___TEST___plain_tpl';
    private const HOST = '___TEST___plain_host';

    public function testResolvingDoesNotChangeTheInheritedProperties(): void
    {
        if ($this->skipForMissingDb()) {
            return;
        }

        $db = $this->getDb();
        IcingaHost::create([
            'object_name'    => self::TEMPLATE,
            'object_type'    => 'template',
            'check_interval' => 60,
        ], $db)->store();
        IcingaHost::create([
            'object_name' => self::HOST,
            'object_type' => 'object',
            'imports'     => [self::TEMPLATE],
            'address'     => '127.0.0.1',
        ], $db)->store();

        $host = IcingaHost::load(self::HOST, $db);
        $host->toPlainObject(true);

        $inherited = $host->getInheritedProperties();
        $origins = $host->getOriginsProperties();
        $this->assertFalse(property_exists($inherited, 'address'), 'Own properties must not show up as inherited');
        $this->assertFalse(property_exists($inherited, 'disabled'), 'Own properties must not show up as inherited');
        foreach ($inherited as $key => $value) {
            $this->assertTrue(property_exists($origins, $key), "Inherited $key needs an origin");
        }
    }

    protected function tearDown(): void
    {
        if ($this->hasDb()) {
            $dba = $this->getDb()->getDbAdapter();
            $dba->delete('icinga_host', $dba->quoteInto('object_name = ?', self::HOST));
            $dba->delete('icinga_host', $dba->quoteInto('object_name = ?', self::TEMPLATE));
        }

        parent::tearDown();
    }
}

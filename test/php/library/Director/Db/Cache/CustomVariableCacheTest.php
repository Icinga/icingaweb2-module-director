<?php

// SPDX-FileCopyrightText: 2026 Icinga GmbH <https://icinga.com>
// SPDX-License-Identifier: GPL-3.0-or-later

namespace Tests\Icinga\Module\Director\Db\Cache;

use Icinga\Module\Director\Db\Cache\PrefetchCache;
use Icinga\Module\Director\Objects\DirectorProperty;
use Icinga\Module\Director\Objects\IcingaHost;
use Icinga\Module\Director\Test\BaseTestCase;
use Ramsey\Uuid\Uuid;

class CustomVariableCacheTest extends BaseTestCase
{
    private const PROP_KEY_NAME = 'CVCTEST_env';

    public function testPrefetchedVarsKeepTheirPropertyUuid(): void
    {
        if ($this->skipForMissingDb()) {
            return;
        }

        $db = $this->getDb();
        $property = DirectorProperty::create([
            'uuid'       => Uuid::uuid4()->getBytes(),
            'key_name'   => self::PROP_KEY_NAME,
            'value_type' => 'string',
            'label'      => 'Env',
        ], $db);
        $property->store();

        $host = IcingaHost::create([
            'object_name' => 'CVCTEST_host',
            'object_type' => 'object',
        ]);
        $host->vars()->set(self::PROP_KEY_NAME, 'production');
        $host->vars()->registerVarUuid(self::PROP_KEY_NAME, Uuid::fromBytes($property->get('uuid')));
        $host->store($db);

        PrefetchCache::forget();
        PrefetchCache::initialize($db);
        $reloaded = IcingaHost::load('CVCTEST_host', $db);

        $this->assertEquals(
            $property->get('uuid'),
            $reloaded->vars()->get(self::PROP_KEY_NAME)->getUuid()->getBytes()
        );
    }

    public function tearDown(): void
    {
        PrefetchCache::forget();

        if ($this->hasDb()) {
            $db = $this->getDb();
            $host = IcingaHost::load('CVCTEST_host', $db);
            $host->delete();
            $db->getDbAdapter()->delete(
                'director_property',
                $db->getDbAdapter()->quoteInto('key_name = ?', self::PROP_KEY_NAME)
            );
        }

        parent::tearDown();
    }
}

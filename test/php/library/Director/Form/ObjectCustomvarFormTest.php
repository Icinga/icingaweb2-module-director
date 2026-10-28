<?php

// SPDX-FileCopyrightText: 2026 Icinga GmbH <https://icinga.com>
// SPDX-License-Identifier: GPL-3.0-or-later

namespace Tests\Icinga\Module\Director\Form;

use Icinga\Module\Director\Db\DbUtil;
use Icinga\Module\Director\Forms\ObjectCustomvarForm;
use Icinga\Module\Director\Objects\DirectorProperty;
use Icinga\Module\Director\Objects\IcingaCommand;
use Icinga\Module\Director\Objects\IcingaHost;
use Icinga\Module\Director\Repository\IcingaTemplateRepository;
use Icinga\Module\Director\Test\BaseTestCase;
use Ramsey\Uuid\Uuid;

class ObjectCustomvarFormTest extends BaseTestCase
{
    private const PREFIX = '___TEST___';
    private const GENERIC_TEMPLATE = self::PREFIX . 'generic-host';
    private const WEB_TEMPLATE = self::PREFIX . 'web-servers';
    private const COMMAND = self::PREFIX . 'ping4';
    private const OS_KEY = self::PREFIX . 'os';
    private const WRTA_KEY = self::PREFIX . 'ping_wrta';
    private const BACKUP_KEY = self::PREFIX . 'backup_path';

    public function testVarFromAnImportedTemplateIsOffered(): void
    {
        if ($this->skipForMissingDb()) {
            return;
        }

        $this->createFixtures();

        $this->assertContains(self::OS_KEY, $this->offeredKeys());
    }

    public function testVarOnlyTheCommandHasIsOffered(): void
    {
        if ($this->skipForMissingDb()) {
            return;
        }

        $this->createFixtures();

        $this->assertContains(self::WRTA_KEY, $this->offeredKeys());
    }

    public function testVarTheObjectHasItselfIsNotOffered(): void
    {
        if ($this->skipForMissingDb()) {
            return;
        }

        $this->createFixtures();

        $this->assertNotContains(self::BACKUP_KEY, $this->offeredKeys());
    }

    public function testVarAddedThisSessionIsNotOffered(): void
    {
        if ($this->skipForMissingDb()) {
            return;
        }

        $this->createFixtures();
        $uuid = Uuid::fromBytes(DirectorProperty::load(self::OS_KEY, $this->getDb())->get('uuid'))->toString();

        $this->assertNotContains(self::OS_KEY, $this->offeredKeys([$uuid]));
    }

    protected function tearDown(): void
    {
        if ($this->hasDb()) {
            $db = $this->getDb();
            foreach ([self::WEB_TEMPLATE, self::GENERIC_TEMPLATE] as $name) {
                if (IcingaHost::exists($name, $db)) {
                    IcingaHost::load($name, $db)->delete();
                }
            }

            if (IcingaCommand::exists(self::COMMAND, $db)) {
                IcingaCommand::load(self::COMMAND, $db)->delete();
            }

            $dba = $db->getDbAdapter();
            $dba->delete('director_property', $dba->quoteInto('key_name IN (?)', [
                self::OS_KEY,
                self::WRTA_KEY,
                self::BACKUP_KEY,
            ]));
        }

        // The tree remembers ids from the objects deleted above
        IcingaTemplateRepository::clear();

        parent::tearDown();
    }

    /**
     * Get the key names the dropdown of the web template offers
     *
     * @param string[] $addedUuids Properties added this session
     *
     * @return string[]
     */
    private function offeredKeys(array $addedUuids = []): array
    {
        $form = new ObjectCustomvarForm(
            $this->getDb(),
            IcingaHost::load(self::WEB_TEMPLATE, $this->getDb()),
            $addedUuids
        );

        return array_values(self::callMethod($form, 'getCustomVars', []));
    }

    private function createFixtures(): void
    {
        $this->tearDown();
        $db = $this->getDb();

        IcingaCommand::create([
            'object_name' => self::COMMAND,
            'object_type' => 'external_object',
            'command'     => 'ping4',
        ], $db)->store();

        foreach ([self::OS_KEY, self::WRTA_KEY, self::BACKUP_KEY] as $key) {
            DirectorProperty::create([
                'uuid'       => Uuid::uuid4()->getBytes(),
                'key_name'   => $key,
                'value_type' => 'string',
                'label'      => $key,
            ], $db)->store();
        }

        IcingaHost::create([
            'object_name' => self::GENERIC_TEMPLATE,
            'object_type' => 'template',
        ], $db)->store();

        $web = IcingaHost::create([
            'object_name'   => self::WEB_TEMPLATE,
            'object_type'   => 'template',
            'check_command' => self::COMMAND,
        ], $db);
        $web->set('imports', [self::GENERIC_TEMPLATE]);
        $web->store();

        $this->attach('command', self::COMMAND, self::WRTA_KEY);
        $this->attach('host', self::GENERIC_TEMPLATE, self::OS_KEY);
        $this->attach('host', self::WEB_TEMPLATE, self::BACKUP_KEY);
    }

    /**
     * Attach a property to a host or command
     *
     * @param string $type Either host or command
     * @param string $name
     * @param string $key
     */
    private function attach(string $type, string $name, string $key): void
    {
        $class = $type === 'host' ? IcingaHost::class : IcingaCommand::class;
        $dba = $this->getDb()->getDbAdapter();
        $dba->insert("icinga_{$type}_property", [
            "{$type}_uuid"  => DbUtil::quoteBinaryCompat($class::load($name, $this->getDb())->get('uuid'), $dba),
            'property_uuid' => DbUtil::quoteBinaryCompat(
                DirectorProperty::load($key, $this->getDb())->get('uuid'),
                $dba
            ),
            'required'      => 'n',
        ]);
    }
}

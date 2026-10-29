<?php

// SPDX-FileCopyrightText: 2026 Icinga GmbH <https://icinga.com>
// SPDX-License-Identifier: GPL-3.0-or-later

namespace Tests\Icinga\Module\Director\Form;

use Icinga\Application\Config;
use Icinga\Module\Director\Db\DbUtil;
use Icinga\Module\Director\Forms\CustomVariablesForm;
use Icinga\Module\Director\Objects\DirectorProperty;
use Icinga\Module\Director\Objects\IcingaCommand;
use Icinga\Module\Director\Test\BaseTestCase;
use Ramsey\Uuid\Uuid;

/**
 * Saves and renders the real form for commands, so the dictionary and its
 * items are not swapped out like in the other form tests
 */
class CustomVariablesFormCommandTest extends BaseTestCase
{
    private const COMMAND_NAME = '___TEST___check_backup';
    private const KEY = '___TEST___backup_path';

    private ?DirectorProperty $property = null;

    public function setUp(): void
    {
        parent::setUp();
        if (! $this->hasDb()) {
            return;
        }

        // The dictionary items open their own connection, point it at the resource BaseTestCase uses
        Config::module('director')->setSection('db', ['resource' => static::getDbResourceName()]);
    }

    public function testCommandRowHasNoRequiredToggle(): void
    {
        if ($this->skipForMissingDb()) {
            return;
        }

        $command = $this->createCommand('object');
        $form = $this->buildForm($command, $this->rowFor(['required' => false]));

        $this->assertStringNotContainsString('item_required_0', $form->render());
    }

    public function testTemplateRowKeepsTheRequiredToggle(): void
    {
        if ($this->skipForMissingDb()) {
            return;
        }

        $command = $this->createCommand('template');
        $form = $this->buildForm($command, $this->rowFor(['required' => false]));

        $this->assertStringContainsString('item_required_0', $form->render());
    }

    public function testSavingACommandWithARequiredAttachmentDoesNotThrow(): void
    {
        if ($this->skipForMissingDb()) {
            return;
        }

        // What a 1.11 upgrade leaves behind for a command with a required field
        $command = $this->createCommand('object');
        $this->attach($command, 'y');
        $form = $this->buildForm($command, $this->rowFor(['required' => true]));
        $form->populate(['properties' => [
            'item-count' => 1,
            0 => ['name' => self::KEY, 'type' => 'string', 'var' => '/srv/backup'],
        ]]);

        $this->persist($form);

        $this->assertSame('y', $this->fetchRequired($command), 'The old flag is left alone');
    }

    public function testNewRowOnACommandIsNeverStoredAsRequired(): void
    {
        if ($this->skipForMissingDb()) {
            return;
        }

        $command = $this->createCommand('object');
        $form = $this->buildForm($command, $this->rowFor(['new' => true, 'required' => true]));
        // Someone ticking the toggle, or posting it by hand
        $form->populate(['properties' => [
            'item-count' => 1,
            0 => ['name' => self::KEY, 'type' => 'string', 'var' => '/srv/backup', 'item_required_0' => 'y'],
        ]]);

        $this->persist($form);

        $this->assertSame('n', $this->fetchRequired($command));
    }

    public function testRequiredAttachmentOnACommandDoesNotDemandAValue(): void
    {
        if ($this->skipForMissingDb()) {
            return;
        }

        $command = $this->createCommand('object');
        $this->attach($command, 'y');
        $form = $this->buildForm($command, $this->rowFor(['required' => true]));

        $this->assertStringNotContainsString('required', $this->renderValueInput($form));
    }

    public function testRequiredInheritedFromACommandTemplateStillDemandsAValue(): void
    {
        if ($this->skipForMissingDb()) {
            return;
        }

        // Not removable here, the requirement comes from an imported command template
        $command = $this->createCommand('object');
        $form = $this->buildForm($command, $this->rowFor(['required' => true, 'allow_removal' => false]));

        $this->assertStringContainsString('required', $this->renderValueInput($form));
    }

    public function testRequiredFromAnImportedTemplateStillDemandsAValueWhenTheCommandAttachesItToo(): void
    {
        if ($this->skipForMissingDb()) {
            return;
        }

        $command = $this->createCommand('object');
        $this->attach($command, 'n');
        $form = $this->buildForm($command, $this->rowFor(['required' => false, 'required_by_ancestor' => true]));

        $this->assertStringContainsString('required', $this->renderValueInput($form));
    }

    public function testRequiredFromAnImportedTemplateDoesNotDemandAValueOnATemplate(): void
    {
        if ($this->skipForMissingDb()) {
            return;
        }

        $command = $this->createCommand('template');
        $this->attach($command, 'n');
        $form = $this->buildForm($command, $this->rowFor(['required' => false, 'required_by_ancestor' => true]));

        $this->assertStringNotContainsString('required', $this->renderValueInput($form));
    }

    public function testRequiredInheritedByACommandTemplateDoesNotDemandAValue(): void
    {
        if ($this->skipForMissingDb()) {
            return;
        }

        // Templates define the schema, so they never have to fill it in
        $command = $this->createCommand('template');
        $form = $this->buildForm($command, $this->rowFor(['required' => true, 'allow_removal' => false]));

        $this->assertStringNotContainsString('required', $this->renderValueInput($form));
    }

    public function testRemovingARowDetachesItFromACommand(): void
    {
        if ($this->skipForMissingDb()) {
            return;
        }

        $command = $this->createCommand('external_object');
        $this->attach($command, 'n');
        $form = $this->buildForm($command, $this->rowFor(['required' => false]));
        $form->populate(['properties' => [
            'item-count' => 1,
            'items_removed' => json_encode([self::KEY]),
            0 => ['name' => self::KEY, 'type' => 'string'],
        ]]);

        $this->persist($form);

        $this->assertFalse($this->fetchRequired($command), 'The attachment should be gone');
    }

    public function testExternalCommandRowHasNoValueInput(): void
    {
        if ($this->skipForMissingDb()) {
            return;
        }

        $command = $this->createCommand('external_object');
        $form = $this->buildForm($command, $this->rowFor(['required' => false]));
        $html = $form->render();

        $this->assertStringContainsString('Backup path', $html, 'The variable should still be listed');
        $this->assertStringNotContainsString('name="properties[0][var]"', $html);
    }

    public function testRegularCommandRowKeepsTheValueInput(): void
    {
        if ($this->skipForMissingDb()) {
            return;
        }

        $command = $this->createCommand('object');
        $form = $this->buildForm($command, $this->rowFor(['required' => false]));

        $this->assertStringContainsString('name="properties[0][var]"', $form->render());
    }

    public function testNewRowOnAnExternalCommandIsAttachedWithoutAValue(): void
    {
        if ($this->skipForMissingDb()) {
            return;
        }

        $command = $this->createCommand('external_object');
        $form = $this->buildForm($command, $this->rowFor(['new' => true]));
        $form->populate(['properties' => [
            'item-count' => 1,
            0 => ['name' => self::KEY, 'type' => 'string'],
        ]]);

        $this->persist($form);

        $this->assertSame('n', $this->fetchRequired($command), 'The variable should be attached');
        $this->assertNull(
            IcingaCommand::load(self::COMMAND_NAME, $this->getDb())->getVars()->{self::KEY} ?? null
        );
    }

    public function testSavingAnExternalCommandLeavesItsStoredValueAlone(): void
    {
        if ($this->skipForMissingDb()) {
            return;
        }

        $command = $this->createCommand('external_object');
        $this->attach($command, 'n');
        $command->vars()->set(self::KEY, '/srv/backup');
        $command->store($this->getDb());
        $command = IcingaCommand::load(self::COMMAND_NAME, $this->getDb());

        $form = $this->buildForm($command, $this->rowFor(['required' => false, 'value' => '/srv/backup']));
        $form->populate(['properties' => [
            'item-count' => 1,
            0 => ['name' => self::KEY, 'type' => 'string'],
        ]]);

        $this->persist($form);

        $this->assertSame(
            '/srv/backup',
            IcingaCommand::load(self::COMMAND_NAME, $this->getDb())->getVars()->{self::KEY}
        );
    }

    protected function tearDown(): void
    {
        if ($this->hasDb()) {
            $db = $this->getDb();
            if (IcingaCommand::exists(self::COMMAND_NAME, $db)) {
                IcingaCommand::load(self::COMMAND_NAME, $db)->delete();
            }

            $dba = $db->getDbAdapter();
            $dba->delete('director_property', $dba->quoteInto('key_name = ?', self::KEY));
        }

        parent::tearDown();
    }

    private function createCommand(string $objectType): IcingaCommand
    {
        $db = $this->getDb();
        if (IcingaCommand::exists(self::COMMAND_NAME, $db)) {
            IcingaCommand::load(self::COMMAND_NAME, $db)->delete();
        }

        $command = IcingaCommand::create([
            'object_name' => self::COMMAND_NAME,
            'object_type' => $objectType,
            'command'     => '/usr/local/bin/backup',
        ]);
        $command->store($db);

        $this->property = DirectorProperty::create([
            'uuid'       => Uuid::uuid4()->getBytes(),
            'key_name'   => self::KEY,
            'value_type' => 'string',
            'label'      => 'Backup path',
        ], $db);
        $this->property->store();

        return $command;
    }

    private function attach(IcingaCommand $command, string $required): void
    {
        $dba = $this->getDb()->getDbAdapter();
        $dba->insert('icinga_command_property', [
            'command_uuid'  => DbUtil::quoteBinaryCompat($command->get('uuid'), $dba),
            'property_uuid' => DbUtil::quoteBinaryCompat($this->property->get('uuid'), $dba),
            'required'      => $required,
        ]);
    }

    private function fetchRequired(IcingaCommand $command): string|false
    {
        $dba = $this->getDb()->getDbAdapter();

        return $dba->fetchOne(
            $dba->select()
                ->from('icinga_command_property', ['required'])
                ->where('command_uuid = ?', DbUtil::quoteBinaryCompat($command->get('uuid'), $dba))
                ->where('property_uuid = ?', DbUtil::quoteBinaryCompat($this->property->get('uuid'), $dba))
        );
    }

    /**
     * @param array<string, mixed> $extra Row flags like new or required
     *
     * @return array<string, array<string, mixed>>
     */
    private function rowFor(array $extra): array
    {
        return [self::KEY => array_merge([
            'key_name'      => self::KEY,
            'uuid'          => $this->property->get('uuid'),
            'value_type'    => 'string',
            'label'         => 'Backup path',
            'allow_removal' => true,
        ], $extra)];
    }

    /**
     * @param array<string, array<string, mixed>> $rows
     */
    private function buildForm(IcingaCommand $command, array $rows): CustomVariablesForm
    {
        $form = (new CustomVariablesForm($command, $rows))->setIsAdmin(true);
        $form->load(array_values($rows));

        return $form;
    }

    private function persist(CustomVariablesForm $form): void
    {
        $form->ensureAssembled();
        self::callMethod($form, 'persistPropertyChanges', []);
    }

    private function renderValueInput(CustomVariablesForm $form): string
    {
        $html = $form->render();
        preg_match('/<input[^>]*name="properties\[0]\[var]"[^>]*>/', $html, $match);

        return $match[0] ?? $html;
    }
}

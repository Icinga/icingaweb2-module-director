<?php

// SPDX-FileCopyrightText: 2026 Icinga GmbH <https://icinga.com>
// SPDX-License-Identifier: GPL-3.0-or-later

namespace Tests\Icinga\Module\Director\Form\DictionaryElements;

use Icinga\Application\Config;
use Icinga\Data\ResourceFactory;
use Icinga\Module\Director\CustomVariable\ObjectCustomPropertySources;
use Icinga\Module\Director\Forms\DictionaryElements\Dictionary;
use Icinga\Module\Director\Test\BaseTestCase;
use ipl\Validator\CallbackValidator;
use Ramsey\Uuid\Uuid;

class DictionaryTest extends BaseTestCase
{
    public function setUp(): void
    {
        parent::setUp();

        if (! $this->hasDb()) {
            return;
        }

        // The items open their own connection, point it at the test DB
        $resource = getenv('DIRECTOR_TESTDB_RES');
        Config::module('director')->setSection('db', ['resource' => $resource]);
        $config = ResourceFactory::getResourceConfig($resource);
        $config->dbname = getenv('DIRECTOR_TESTDB');
        $config->host = getenv('DIRECTOR_TESTDB_HOST');
        $config->port = getenv('DIRECTOR_TESTDB_PORT');
        $config->username = getenv('DIRECTOR_TESTDB_USER');
        $config->password = getenv('DIRECTOR_TESTDB_PASSWORD');
    }

    public function testRowsAreDrawnInOwnTemplateCommandOrder(): void
    {
        if ($this->skipForMissingDb()) {
            return;
        }

        $html = $this->render($this->rows());

        $this->assertLessThan(strpos($html, 'Added by template'), strpos($html, 'value="backup_path"'));
        $this->assertLessThan(strpos($html, 'Added by command'), strpos($html, 'Added by template'));
    }

    public function testTemplateSectionHasItsHeading(): void
    {
        if ($this->skipForMissingDb()) {
            return;
        }

        $html = $this->render($this->rows());

        $this->assertStringContainsString('Added by template &quot;generic-host&quot;', $html);
    }

    public function testImportedCommandTemplateKeepsTheTemplateHeading(): void
    {
        if ($this->skipForMissingDb()) {
            return;
        }

        $imported = [
            'kind' => ObjectCustomPropertySources::TEMPLATE,
            'name' => 'ping-base',
            'type' => 'command',
            'object_type' => 'template',
            'order' => 1,
        ];
        $html = $this->render([$this->row('ping_cpl', 'Critical packet loss', $imported)]);

        $this->assertStringContainsString('Added by template &quot;ping-base&quot;', $html);
        $this->assertStringNotContainsString('Added by command', $html);
    }

    public function testImportedCommandIsCalledACommandInItsHeading(): void
    {
        if ($this->skipForMissingDb()) {
            return;
        }

        $imported = [
            'kind' => ObjectCustomPropertySources::TEMPLATE,
            'name' => 'ping-base',
            'type' => 'command',
            'object_type' => 'object',
            'order' => 1,
        ];
        $html = $this->render([$this->row('ping_cpl', 'Critical packet loss', $imported)]);

        $this->assertStringContainsString('Added by command &quot;ping-base&quot;', $html);
        $this->assertStringNotContainsString('Added by template', $html);
        $this->assertStringContainsString('<details class="dictionary-section template collapsible" open', $html);
    }

    public function testTemplateSectionStartsOpenAndCanBeFolded(): void
    {
        if ($this->skipForMissingDb()) {
            return;
        }

        $html = $this->render($this->rows());

        $this->assertStringContainsString('<details class="dictionary-section template collapsible" open', $html);
    }

    public function testOwnSectionStartsOpenAndCanBeFolded(): void
    {
        if ($this->skipForMissingDb()) {
            return;
        }

        $html = $this->render($this->rows(), false, 'Added in this template "linux-web-template"');

        $this->assertStringContainsString('<details class="dictionary-section own collapsible" open', $html);
    }

    public function testCommandSectionIsCollapsedDetails(): void
    {
        if ($this->skipForMissingDb()) {
            return;
        }

        $html = $this->render($this->rows());

        $this->assertStringContainsString('<details class="dictionary-section command collapsible"', $html);
        $this->assertStringNotContainsString('command collapsible" open', $html);
        $this->assertStringContainsString('data-no-persistence', $html);
        $this->assertStringContainsString('Added by command &quot;ping4&quot;', $html);
    }

    public function testCommandSectionOpensWhenOneOfItsRowsFailed(): void
    {
        if ($this->skipForMissingDb()) {
            return;
        }

        $html = $this->renderWithError('0');

        $this->assertMatchesRegularExpression(
            '/<details class="dictionary-section command collapsible"[^>]*\sopen[\s>]/',
            $html
        );
    }

    public function testCommandSectionStaysFoldedWhenAnotherRowFailed(): void
    {
        if ($this->skipForMissingDb()) {
            return;
        }

        $html = $this->renderWithError('1');

        $this->assertDoesNotMatchRegularExpression(
            '/<details class="dictionary-section command collapsible"[^>]*\sopen[\s>]/',
            $html
        );
    }

    public function testNewVariableSlotSitsInTheOwnSection(): void
    {
        if ($this->skipForMissingDb()) {
            return;
        }

        $html = $this->render($this->rows(), true);

        $this->assertLessThan(strpos($html, 'Added by template'), strpos($html, 'id="new-var-slot-3"'));
    }

    public function testOwnRowsSayTheyWereAddedInThisTemplate(): void
    {
        if ($this->skipForMissingDb()) {
            return;
        }

        $html = $this->render($this->rows(), false, 'Added in this template "linux-web-template"');

        $this->assertStringContainsString('Added in this template &quot;linux-web-template&quot;', $html);
        $this->assertLessThan(strpos($html, 'Added by template'), strpos($html, 'Added in this template'));
    }

    public function testOwnRowsAloneAreWrappedInTheirSection(): void
    {
        if ($this->skipForMissingDb()) {
            return;
        }

        $own = ['kind' => ObjectCustomPropertySources::OWN, 'name' => 'linux-web-template', 'order' => 0];
        $html = $this->render([$this->row('backup_path', 'Backup path', $own)], true, 'Added in this template "x"');

        $this->assertStringContainsString('Added in this template &quot;x&quot;', $html);
        $this->assertStringContainsString('<details class="dictionary-section own collapsible" open', $html);
    }

    public function testOwnBoxIsThereForNewVariablesWithoutOwnRows(): void
    {
        if ($this->skipForMissingDb()) {
            return;
        }

        $template = ['kind' => ObjectCustomPropertySources::TEMPLATE, 'name' => 'generic-host', 'order' => 1];
        $html = $this->render([$this->row('os', 'Operating system', $template)], true, 'Added in this template "x"');

        $this->assertStringContainsString('Added in this template &quot;x&quot;', $html);
        $this->assertLessThan(strpos($html, 'Added by template'), strpos($html, 'id="new-var-slot-1"'));
    }

    public function testNoOwnHeadingWhenNothingCanBeAdded(): void
    {
        if ($this->skipForMissingDb()) {
            return;
        }

        $template = ['kind' => ObjectCustomPropertySources::TEMPLATE, 'name' => 'generic-host', 'order' => 1];
        $html = $this->render([$this->row('os', 'Operating system', $template)], false, 'Added in this template "x"');

        $this->assertStringNotContainsString('Added in this template', $html);
    }

    public function testInheritedRowsGetASwapTarget(): void
    {
        if ($this->skipForMissingDb()) {
            return;
        }

        $html = $this->render($this->rows(), true, 'Added in this template "linux-web-template"');

        foreach (['ping_wrta', 'os'] as $key) {
            $this->assertStringContainsString('id="' . Dictionary::getInheritedRowId($key) . '"', $html);
            $this->assertStringContainsString('id="' . Dictionary::getSwapTargetId($key) . '"', $html);
            $this->assertLessThan(
                strpos($html, 'id="' . Dictionary::getInheritedRowId($key) . '"'),
                strpos($html, 'id="' . Dictionary::getSwapTargetId($key) . '"')
            );
        }

        $this->assertStringNotContainsString('id="' . Dictionary::getInheritedRowId('backup_path') . '"', $html);
        $this->assertStringNotContainsString('id="' . Dictionary::getSwapTargetId('backup_path') . '"', $html);
    }

    public function testNothingIsWrappedWhenVariablesCannotBeAdded(): void
    {
        if ($this->skipForMissingDb()) {
            return;
        }

        $html = $this->render($this->rows());

        $this->assertStringNotContainsString('dictionary-row', $html);
        $this->assertStringNotContainsString('swap-target', $html);
    }

    public function testRowsWithoutASectionHaveNoBoxes(): void
    {
        if ($this->skipForMissingDb()) {
            return;
        }

        $rows = [$this->row('backup_path', 'Backup path', null), $this->row('retention', 'Retention', null)];
        $html = $this->render($rows);

        $this->assertStringNotContainsString('dictionary-section', $html);
        $this->assertLessThan(strpos($html, 'value="retention"'), strpos($html, 'value="backup_path"'));
    }

    public function testRowNumbersFollowTheDataNotTheSections(): void
    {
        if ($this->skipForMissingDb()) {
            return;
        }

        $html = $this->render($this->rows());

        // The command row is first in the data, so it keeps number 0 even though it is drawn last
        $this->assertMatchesRegularExpression('/value="ping_wrta" name="properties\[0]\[name]"/', $html);
        $this->assertMatchesRegularExpression('/value="backup_path" name="properties\[2]\[name]"/', $html);
    }

    /**
     * @return array<int, array>
     */
    private function rows(): array
    {
        $command = ['kind' => ObjectCustomPropertySources::COMMAND, 'name' => 'ping4', 'order' => 3];
        $template = ['kind' => ObjectCustomPropertySources::TEMPLATE, 'name' => 'generic-host', 'order' => 1];
        $own = ['kind' => ObjectCustomPropertySources::OWN, 'name' => 'linux-web-template', 'order' => 0];

        return [
            $this->row('ping_wrta', 'Warning round trip', $command),
            $this->row('os', 'Operating system', $template),
            $this->row('backup_path', 'Backup path', $own),
        ];
    }

    private function row(string $key, string $label, ?array $section): array
    {
        $row = [
            'key_name'      => $key,
            'uuid'          => Uuid::uuid4()->getBytes(),
            'value_type'    => 'string',
            'label'         => $label,
            'allow_removal' => true,
        ];

        if ($section !== null) {
            $row['section'] = $section;
        }

        return $row;
    }

    private function renderWithError(string $rowNumber): string
    {
        $dictionary = new Dictionary('properties', $this->rows());
        $row = $dictionary->ensureAssembled()->getElement($rowNumber)->ensureAssembled();
        $row->getElement('name')->addValidators([
            new CallbackValidator(function ($value, CallbackValidator $validator) {
                $validator->addMessage('Not an allowed value');

                return false;
            })
        ]);

        $dictionary->validate();

        return (string) $dictionary->render();
    }

    private function render(array $rows, bool $removable = false, ?string $ownHeading = null): string
    {
        $dictionary = (new Dictionary('properties', $rows))
            ->setAllowItemRemoval($removable)
            ->setOwnHeading($ownHeading);

        return (string) $dictionary->render();
    }
}

<?php

// SPDX-FileCopyrightText: 2026 Icinga GmbH <https://icinga.com>
// SPDX-License-Identifier: GPL-3.0-or-later

namespace Tests\Icinga\Module\Director\Form\DictionaryElements;

use DOMDocument;
use DOMXPath;
use Icinga\Application\Config;
use Icinga\Module\Director\Forms\DictionaryElements\Dictionary;
use Icinga\Module\Director\Test\BaseTestCase;
use ipl\Web\Compat\CompatForm;
use Ramsey\Uuid\Uuid;

/**
 * The custom variables of an object, grouped by category
 */
class DictionaryTest extends BaseTestCase
{
    public function setUp(): void
    {
        parent::setUp();

        // DictionaryItem opens its own connection through the module config. getDb()
        // registers the test db resource, which that connection relies on.
        if ($this->hasDb()) {
            $this->getDb();
            Config::module('director')->setSection('db', ['resource' => static::getDbResourceName()]);
        }
    }

    public function testItemsAreSortedByCategoryAndLabel(): void
    {
        $items = [
            $this->item('team', 'Team'),
            $this->item('pve_user', 'PVE API User', 'Proxmox'),
            $this->item('is_pve', 'Is Proxmox VM/LXC?', 'Proxmox'),
            $this->item('bp_paths', 'Business Process paths', 'Business Processes'),
            $this->item('alpha', null),
            $this->item('pve_token', 'PVE API Token', 'Proxmox'),
        ];

        $this->assertSame(
            ['bp_paths', 'alpha', 'team', 'is_pve', 'pve_token', 'pve_user'],
            array_column(Dictionary::sortByCategory($items), 'key_name'),
            'categories by name, no category counts as "Custom Variables", then by label or key name'
        );
    }

    public function testItemsAreSortedCaseInsensitivelyAndNaturally(): void
    {
        $items = [
            $this->item('var10', 'Disk 10'),
            $this->item('var2', 'disk 2'),
            $this->item('var1', 'Disk 1'),
        ];

        $this->assertSame(
            ['var1', 'var2', 'var10'],
            array_column(Dictionary::sortByCategory($items), 'key_name')
        );
    }

    public function testEachCategoryGetsItsOwnSection(): void
    {
        if ($this->skipForMissingDb()) {
            return;
        }

        $items = Dictionary::sortByCategory([
            $this->item('team', 'Team'),
            $this->item('pve_user', 'PVE API User', 'Proxmox'),
            $this->item('is_pve', 'Is Proxmox VM/LXC?', 'Proxmox'),
        ]);

        $sections = $this->renderSections((new Dictionary('properties', $items))->setGroupByCategory());

        $this->assertSame(
            [
                'Custom Variables' => ['Team (String)'],
                'Proxmox'          => ['Is Proxmox VM/LXC? (String)', 'PVE API User (String)'],
            ],
            $sections
        );
    }

    public function testValuesStillBelongToTheirItemWhenGrouped(): void
    {
        if ($this->skipForMissingDb()) {
            return;
        }

        $items = Dictionary::sortByCategory([
            $this->item('team', 'Team'),
            $this->item('pve_user', 'PVE API User', 'Proxmox'),
        ]);

        $dictionary = (new Dictionary('properties', $items))->setGroupByCategory();
        $dictionary->populate([
            0 => ['name' => 'team', 'var' => 'networking'],
            1 => ['name' => 'pve_user', 'var' => 'monitoring@pve'],
        ]);

        $this->assertSame(
            ['team' => 'networking', 'pve_user' => 'monitoring@pve'],
            $dictionary->getDictionary()
        );
    }

    public function testItemsAddedInThisSessionStayBelowTheSections(): void
    {
        if ($this->skipForMissingDb()) {
            return;
        }

        $items = Dictionary::sortByCategory([
            $this->item('pve_user', 'PVE API User', 'Proxmox'),
        ]);
        $items[] = $this->item('added', 'Added Variable', 'Proxmox') + ['new' => true];

        $dictionary = (new Dictionary('properties', $items))->setGroupByCategory();

        $xpath = $this->renderXPath($dictionary);

        $this->assertSame(['Proxmox' => ['PVE API User (String)']], $this->sectionsOf($xpath));
        $outside = $xpath->query(
            '//fieldset[contains(@class, "dictionary-item")]'
            . '[not(ancestor::fieldset[contains(@class, "custom-var-category")])]/legend'
        );
        $this->assertSame(1, $outside->length);
        $this->assertSame('Added Variable (String)', trim($outside->item(0)->textContent));
    }

    public function testFlatItemIsARowWithItsLabelAndInput(): void
    {
        if ($this->skipForMissingDb()) {
            return;
        }

        $dictionary = (new Dictionary('properties', [$this->item('team', 'Team')]))->setFlatItems();
        $xpath = $this->renderXPath($dictionary);

        $this->assertSame(1, $xpath->query('//fieldset[contains(@class, "flat")]')->length);
        $this->assertSame(0, $xpath->query('//fieldset[contains(@class, "flat")]/legend')->length, 'no box heading');
        $this->assertSame(0, $xpath->query('//input[@name="properties[0][key_name_display]"]')->length);
        $this->assertSame(['Team'], $this->labels($xpath));
        $this->assertStringNotContainsString(
            'vars.team',
            $xpath->document->saveHTML(),
            'only the label, like a Data Field'
        );
    }

    public function testDictionaryKeepsItsBoxWhenItemsAreFlat(): void
    {
        if ($this->skipForMissingDb()) {
            return;
        }

        $item = ['value_type' => 'fixed-dictionary'] + $this->item('mysql', 'MySQL');
        $dictionary = (new Dictionary('properties', [$item]))->setFlatItems();
        $xpath = $this->renderXPath($dictionary);

        $this->assertSame(0, $xpath->query('//fieldset[contains(@class, "flat")]')->length);
        $this->assertSame(
            'MySQL (Dictionary)',
            trim($xpath->query('//fieldset[contains(@class, "dictionary-item")]/legend')->item(0)->textContent)
        );
    }

    public function testDynamicDictionaryIsARowWithItsEntries(): void
    {
        if ($this->skipForMissingDb()) {
            return;
        }

        $item = ['value_type' => 'dynamic-dictionary'] + $this->item('notifications', 'Notifications');
        $dictionary = (new Dictionary('properties', [$item]))->setFlatItems();
        $xpath = $this->renderXPath($dictionary);

        $row = '//fieldset[contains(@class, "flat")]/div[contains(@class, "flat-dictionary")]';
        $this->assertSame(['Notifications'], $this->labels($xpath));
        $this->assertSame(1, $xpath->query($row . '/div[@class="control-label-group"]/label')->length);
        $this->assertSame(1, $xpath->query($row . '//fieldset[contains(@class, "nested-dictionary")]')->length);
        $this->assertSame(1, $xpath->query($row . '//button[contains(@class, "add-item")]')->length);
        $this->assertSame(0, $xpath->query('//legend')->length, 'neither a box nor a legend above the entries');
    }

    public function testValuesOfFlatItemsStillBelongToTheirItem(): void
    {
        if ($this->skipForMissingDb()) {
            return;
        }

        $dictionary = (new Dictionary('properties', [
            $this->item('team', 'Team'),
            ['value_type' => 'number'] + $this->item('interval', 'Interval'),
        ]))->setFlatItems();
        $dictionary->populate([
            0 => ['name' => 'team', 'var' => 'networking'],
            1 => ['name' => 'interval', 'var' => '30'],
        ]);

        $this->assertEquals(['team' => 'networking', 'interval' => 30], $dictionary->getDictionary());
    }

    public function testNoSectionsUnlessAsked(): void
    {
        if ($this->skipForMissingDb()) {
            return;
        }

        $dictionary = new Dictionary('properties', [$this->item('pve_user', 'PVE API User', 'Proxmox')]);

        $this->assertSame([], $this->renderSections($dictionary));
    }

    /**
     * Render the dictionary and get the labels of the items in each category section
     *
     * @return array<string, string[]> Section name => legends of the items in it
     */
    private function renderSections(Dictionary $dictionary): array
    {
        return $this->sectionsOf($this->renderXPath($dictionary));
    }

    /**
     * Get the labels of the items in each category section of a rendered dictionary
     *
     * @return array<string, string[]> Section name => legends of the items in it
     */
    private function sectionsOf(DOMXPath $xpath): array
    {
        $sections = [];
        foreach ($xpath->query('//fieldset[contains(@class, "custom-var-category")]') as $section) {
            $name = trim($xpath->query('./legend', $section)->item(0)->textContent);
            $sections[$name] = [];
            foreach ($xpath->query('.//fieldset[contains(@class, "dictionary-item")]/legend', $section) as $legend) {
                $sections[$name][] = trim($legend->textContent);
            }
        }

        return $sections;
    }

    private function renderXPath(Dictionary $dictionary): DOMXPath
    {
        // Labels and descriptions are rendered by the form's decorator
        $form = (new CompatForm())->addElement($dictionary);

        $dom = new DOMDocument();
        $dom->loadHTML('<?xml encoding="UTF-8"><body>' . $form . '</body>', LIBXML_NOERROR);

        return new DOMXPath($dom);
    }

    /**
     * @return string[] The text of every visible label
     */
    private function labels(DOMXPath $xpath): array
    {
        $labels = [];
        foreach ($xpath->query('//label') as $label) {
            $labels[] = trim($label->textContent);
        }

        return $labels;
    }

    private function item(string $keyName, ?string $label, ?string $category = null): array
    {
        return [
            'uuid'          => Uuid::uuid4()->getBytes(),
            'key_name'      => $keyName,
            'label'         => $label,
            'category'      => $category,
            'value_type'    => 'string',
            'allow_removal' => false,
        ];
    }
}

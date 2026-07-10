<?php

// SPDX-FileCopyrightText: 2026 Icinga GmbH <https://icinga.com>
// SPDX-License-Identifier: GPL-3.0-or-later

namespace Tests\Icinga\Module\Director\CustomVariable;

use Icinga\Module\Director\CustomVariable\CustomVariables;
use Icinga\Module\Director\Db\DbUtil;
use Icinga\Module\Director\Objects\DirectorProperty;
use Icinga\Module\Director\Objects\IcingaHost;
use Icinga\Module\Director\Test\BaseTestCase;
use Ramsey\Uuid\Uuid;

class CustomVariablesTest extends BaseTestCase
{
    protected $indent = '    ';

    public function testWhetherSpecialKeyNames()
    {
        $vars = $this->newVars();
        $vars->bla = 'da';
        $vars->{'aBc'} = 'normal';
        $vars->{'a-0'} = 'special';
        $expected = $this->indentVarsList([
            'vars["a-0"] = "special"',
            'vars.aBc = "normal"',
            'vars.bla = "da"'
        ]);
        $this->assertEquals($expected, $vars->toConfigString());
    }

    public function testVarsCanBeUnsetAndSetAgain()
    {
        $vars = $this->newVars();
        $vars->one = 'two';
        unset($vars->one);
        $vars->one = 'three';

        $res = [];
        foreach ($vars as $k => $v) {
            $res[$k] = $v->getValue();
        }

        $this->assertEquals(['one' => 'three'], $res);
    }

    public function testNumericKeysAreRenderedWithArraySyntax()
    {
        $vars = $this->newVars();
        $vars->{'1'} = 1;
        $expected = $this->indentVarsList([
            'vars["1"] = 1'
        ]);

        $this->assertEquals(
            $expected,
            $vars->toConfigString(true)
        );
    }

    public function testVariablesToExpression()
    {
        $vars = $this->newVars();
        $vars->bla = 'da';
        $vars->abc = '$val$';
        $expected = $this->indentVarsList([
            'vars.abc = "$val$"',
            'vars.bla = "da"'
        ]);
        $this->assertEquals($expected, $vars->toConfigString(true));
    }

    public function testPrefetchCustomVarTypesPrefersExactObjectMatchOverAncestorRows(): void
    {
        if ($this->skipForMissingDb()) {
            return;
        }

        $db = $this->getDb();
        $dba = $db->getDbAdapter();

        // Template needs at least one icinga_host_var row to satisfy the JOIN in
        // CustomVariables::prefetchCustomVarTypes().
        $template = IcingaHost::create([
            'object_name' => '___TEST___linux-server',
            'object_type' => 'template',
            'vars'        => ['env' => 'production'],
        ], $db);
        $template->store();

        $property = DirectorProperty::create([
            'uuid'       => Uuid::uuid4()->getBytes(),
            'key_name'   => '___TEST___disk_mount_thresholds',
            'value_type' => 'dynamic-dictionary',
            'label'      => 'Disk Mount Thresholds',
        ], $db);
        $property->store();

        $child = IcingaHost::create([
            'object_name' => '___TEST___db-server-02',
            'object_type' => 'object',
            'address'     => '10.0.1.55',
            'vars'        => [
                '___TEST___disk_mount_thresholds' => (object) [
                    'root' => (object) ['mount_point' => '/', 'warn' => '20%', 'crit' => '10%'],
                ],
            ],
        ], $db);
        $child->imports = '___TEST___linux-server';
        $child->store();

        // Assign the same property to both the template and the child, so the query in
        // prefetchCustomVarTypes() returns two rows for this key: one with object_id equal
        // to the ancestor template's id, one equal to the child's own id. Which row the
        // database happens to return last must not decide which object_id gets cached.
        $db->insert('icinga_host_property', [
            'property_uuid' => DbUtil::quoteBinaryCompat($property->get('uuid'), $dba),
            'host_uuid'     => DbUtil::quoteBinaryCompat($template->get('uuid'), $dba),
        ]);
        $db->insert('icinga_host_property', [
            'property_uuid' => DbUtil::quoteBinaryCompat($property->get('uuid'), $dba),
            'host_uuid'     => DbUtil::quoteBinaryCompat($child->get('uuid'), $dba),
        ]);

        $loaded = IcingaHost::load('___TEST___db-server-02', $db);
        $vars = $loaded->vars();

        self::callMethod($vars, 'prefetchCustomVarTypes', [$loaded]);

        $cacheProperty = new \ReflectionProperty(CustomVariables::class, 'cachedCustomVariableTypes');
        $cacheProperty->setAccessible(true);
        $cache = $cacheProperty->getValue($vars);

        $this->assertSame(
            (int) $loaded->get('id'),
            $cache['___TEST___disk_mount_thresholds']['object_id'],
            'the exact object being rendered must win over an ancestor row for the same key, '
            . 'regardless of the order the database returns rows in'
        );
    }

    public function tearDown(): void
    {
        if ($this->hasDb()) {
            $db = $this->getDb();
            $dba = $db->getDbAdapter();

            // Child must be deleted before the template it imports from.
            foreach (['___TEST___db-server-02', '___TEST___linux-server'] as $name) {
                if (IcingaHost::exists($name, $db)) {
                    IcingaHost::load($name, $db)->delete();
                }
            }

            $dba->delete(
                'director_property',
                $dba->quoteInto('key_name = ?', '___TEST___disk_mount_thresholds')
            );
        }

        parent::tearDown();
    }

    protected function indentVarsList($vars)
    {
        return $this->indent . implode(
            "\n" . $this->indent,
            $vars
        ) . "\n";
    }

    protected function newVars()
    {
        return new CustomVariables();
    }
}

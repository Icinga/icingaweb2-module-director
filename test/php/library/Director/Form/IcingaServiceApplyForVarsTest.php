<?php

// SPDX-FileCopyrightText: 2026 Icinga GmbH <https://icinga.com>
// SPDX-License-Identifier: GPL-3.0-or-later

namespace Tests\Icinga\Module\Director\Form;

use Icinga\Module\Director\Forms\IcingaServiceForm;
use Icinga\Module\Director\Objects\IcingaHost;
use Icinga\Module\Director\Test\BaseTestCase;

class IcingaServiceApplyForVarsTest extends BaseTestCase
{
    public function testSyncedArrayVarIsSelectableWithoutHostPropertyOrDatafield(): void
    {
        if ($this->skipForMissingDb()) {
            return;
        }

        $db = $this->getDb();
        $host = IcingaHost::create([
            'object_name' => '___TEST___apply_for_synced_arrays_3073',
            'object_type' => 'template',
            'vars' => [
                '___TEST___synced_tcp_ports_3073' => [443, 8443],
                '___TEST___synced_scalar_3073' => '["not", "an", "array"]',
                '___TEST___synced_dictionary_3073' => (object) ['https' => 443],
            ],
        ], $db);
        $host->store();

        try {
            $form = IcingaServiceForm::load()->setDb($db);
            $groups = self::callMethod($form, 'applyForVars', []);
            $vars = array_values($groups)[0];

            $this->assertArrayHasKey(
                'host.vars.___TEST___synced_tcp_ports_3073',
                $vars,
                'An existing array-valued host var should not require a second data field declaration'
            );
            $this->assertArrayNotHasKey('host.vars.___TEST___synced_scalar_3073', $vars);
            $this->assertArrayNotHasKey('host.vars.___TEST___synced_dictionary_3073', $vars);
        } finally {
            $host->delete();
        }
    }
}

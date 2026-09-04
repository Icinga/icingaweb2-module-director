<?php

// SPDX-FileCopyrightText: 2026 Icinga GmbH <https://icinga.com>
// SPDX-License-Identifier: GPL-3.0-or-later

namespace Tests\Icinga\Module\Director\Objects;

use Icinga\Module\Director\Objects\DirectorDatalistEntry;
use Icinga\Module\Director\Test\BaseTestCase;

class DirectorDatalistEntryTest extends BaseTestCase
{
    public function testUnrestrictedEntryIsAllowedForAnyRoles(): void
    {
        $this->assertTrue(DirectorDatalistEntry::isAllowedForRoles(null, []));
        $this->assertTrue(DirectorDatalistEntry::isAllowedForRoles(null, ['network-team']));
    }

    public function testEmptyAllowedRolesIsTreatedAsUnrestricted(): void
    {
        $this->assertTrue(DirectorDatalistEntry::isAllowedForRoles('', ['network-team']));
    }

    public function testEntryAllowedForCurrentRoleIsAllowed(): void
    {
        $allowedRoles = json_encode(['network-team']);

        $this->assertTrue(DirectorDatalistEntry::isAllowedForRoles($allowedRoles, ['network-team']));
    }

    public function testEntryAllowedForOneOfSeveralCurrentRolesIsAllowed(): void
    {
        $allowedRoles = json_encode(['security-team', 'network-team']);

        $this->assertTrue(
            DirectorDatalistEntry::isAllowedForRoles($allowedRoles, ['read-only', 'network-team'])
        );
    }

    public function testEntryRestrictedToAnotherRoleIsDenied(): void
    {
        $allowedRoles = json_encode(['security-team']);

        $this->assertFalse(DirectorDatalistEntry::isAllowedForRoles($allowedRoles, ['network-team']));
    }

    public function testRestrictedEntryIsDeniedForUserWithNoRoles(): void
    {
        // having zero roles is not the same as the entry being unrestricted
        $allowedRoles = json_encode(['security-team']);

        $this->assertFalse(DirectorDatalistEntry::isAllowedForRoles($allowedRoles, []));
    }

    public function testMalformedAllowedRolesFailsClosed(): void
    {
        // bad data in the column should hide the entry, not expose it
        $this->assertFalse(DirectorDatalistEntry::isAllowedForRoles('not valid json', ['network-team']));
    }
}

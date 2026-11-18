<?php

// SPDX-FileCopyrightText: 2026 Icinga GmbH <https://icinga.com>
// SPDX-License-Identifier: GPL-3.0-or-later

namespace Icinga\Module\Director\Web\Navigation\Renderer;

use Exception;

/**
 * Shows the number of import sources for which a change-detection job
 * found data that has not yet been imported.
 *
 * Reuse the existing navigation DB lookup so a user's currently selected
 * Director database is respected.
 */
class ImportPendingNavigationItemRenderer extends ConfigHealthItemRenderer
{
    private ?int $pendingCount = null;

    protected function hasProblems()
    {
        return $this->getCount() > 0;
    }

    public function getCount()
    {
        if ($this->pendingCount !== null) {
            return $this->pendingCount;
        }

        $this->pendingCount = 0;

        try {
            $db = $this->db();
            if ($db) {
                $this->pendingCount = (int) $db->getDbAdapter()->fetchOne(
                    "SELECT COUNT(*) FROM import_source WHERE import_state = 'pending-changes'"
                );
            }
        } catch (Exception $e) {
            // An unconfigured database or an unapplied migration must not
            // break navigation. The Activity Log badge reports DB health.
        }

        return $this->pendingCount;
    }

    public function getState()
    {
        return $this->getCount() > 0 ? self::STATE_WARNING : self::STATE_OK;
    }

    public function getTitle()
    {
        $count = $this->getCount();
        if ($count === 0) {
            return null;
        }

        return sprintf(
            $this->translate('There are %d import sources with pending changes'),
            $count
        );
    }
}

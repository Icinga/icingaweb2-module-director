<?php

namespace Icinga\Module\Director\Web\Navigation\Renderer;

use Exception;
use Icinga\Application\Config;
use Icinga\Module\Director\ConfigHealthChecker;
use Icinga\Module\Director\Db;
use Icinga\Module\Director\Db\Migrations;
use Icinga\Module\Director\KickstartHelper;
use Icinga\Web\Navigation\Renderer\BadgeNavigationItemRenderer;

class ConfigHealthItemRenderer extends BadgeNavigationItemRenderer
{
    private $db;

    private $directorState = self::STATE_OK;

    private $message;

    private $count = 0;

    protected function hasProblems()
    {
        $this->checkHealth();
        return $this->count > 0;
    }

    public function getState()
    {
        return $this->directorState;
    }

    public function getCount()
    {
        if ($this->hasProblems()) {
            return $this->count;
        } else {
            return 0;
        }
    }

    public function getTitle()
    {
        return $this->message;
    }

    protected function checkHealth()
    {
        $db = $this->db();
        if (! $db) {
            $this->directorState = self::STATE_PENDING;
            $this->count = 1;
            $this->message = $this->translate(
                'No database has been configured for Icinga Director'
            );

            return;
        }

        $migrations = new Migrations($db);
        if (!$migrations->hasSchema()) {
            $this->count = 1;
            $this->directorState = self::STATE_CRITICAL;
            $this->message = $this->translate(
                'Director database schema has not been created yet'
            );
            return;
        }

        if ($migrations->hasPendingMigrations()) {
            $this->count = $migrations->countPendingMigrations();
            $this->directorState = self::STATE_PENDING;
            $this->message = sprintf(
                $this->translate('There are %d pending database migrations'),
                $this->count
            );
            return;
        }

        $kickstart = new KickstartHelper($db);
        if ($kickstart->isRequired()) {
            $this->directorState = self::STATE_PENDING;
            $this->count = 1;
            $this->message = $this->translate(
                'No API user configured, you might run the kickstart helper'
            );

            return;
        }

        $pendingChanges = $db->countActivitiesSinceLastDeployedConfig();

        if ($pendingChanges > 0) {
            $this->directorState = self::STATE_WARNING;
            $this->count = $pendingChanges;
            $this->message = sprintf(
                $this->translate(
                    '%s config changes happend since the last deployed configuration'
                ),
                $pendingChanges
            );

            return;
        }
    }

    protected function translate($message)
    {
        return mt('director', $message);
    }

    protected function db()
    {
        $resourceName = Config::module('director')->get('db', 'resource');
        if ($resourceName) {
            return Db::fromResourceName($resourceName);
        } else {
            return false;
        }
    }
}

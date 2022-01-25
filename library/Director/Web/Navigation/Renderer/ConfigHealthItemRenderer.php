<?php

namespace Icinga\Module\Director\Web\Navigation\Renderer;

use Exception;
use Icinga\Application\Config;
use Icinga\Application\Icinga;
use Icinga\Application\Web;
use Icinga\Authentication\Auth;
use Icinga\Module\Director\Db;
use Icinga\Module\Director\Db\Branch\Branch;
use Icinga\Module\Director\Db\Branch\BranchStore;
use Icinga\Module\Director\Db\Migrations;
use Icinga\Module\Director\KickstartHelper;
use Icinga\Module\Director\Web\Controller\Extension\DirectorDb;
use Icinga\Web\Navigation\Renderer\BadgeNavigationItemRenderer;
use Icinga\Module\Director\Web\Window;

class ConfigHealthItemRenderer extends BadgeNavigationItemRenderer
{
    use DirectorDb;

    private $directorState = self::STATE_OK;

    private $message;

    private $count = 0;

    private $window;

    protected function hasProblems()
    {
        try {
            $this->checkHealth();
        } catch (Exception $e) {
            $this->directorState = self::STATE_UNKNOWN;
            $this->count = 1;
            $this->message = $e->getMessage();
        }

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

        $branch = Branch::detect(new BranchStore($this->db()));
        if ($branch->isBranch()) {
            $count = $branch->getActivityCount();
            if ($count > 0) {
                $this->directorState = self::STATE_PENDING;
                $this->count = $count;
                $this->message = sprintf(
                    $this->translate('%s config changes are available in your configuration branch'),
                    $count
                );
            }
            return;
        }

        $pendingLiveModifications = $db->countPendingLiveModifications();
        if ($pendingLiveModifications > 0) {
            $this->directorState = self::STATE_PENDING;
            $this->count = $pendingLiveModifications;
            $this->message = sprintf(
                $this->translate('There are %d pending live modifications'),
                $pendingLiveModifications
            );
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
        }
    }

    protected function translate($message)
    {
        return mt('director', $message);
    }

    protected function db()
    {
        try {
            $resourceName = Config::module('director')->get('db', 'resource');
            if ($resourceName) {
                // Window might have switched to another DB:
                return Db::fromResourceName($this->getDbResourceName());
            } else {
                return false;
            }
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * TODO: the following methods are for the DirectorDb trait, we need
     *       something better in future. It is required to show Health
     *       related to the DB chosen in the current Window
     *
     * @codingStandardsIgnoreStart
     * @return Auth
     */
    protected function Auth()
    {
        return Auth::getInstance();
    }

    /**
     * @return Window
     */
    public function Window()
    {
        if ($this->window === null) {
            try {
                /** @var $app Web */
                $app = Icinga::app();
                $this->window = new Window(
                    $app->getRequest()->getHeader('X-Icinga-WindowId')
                );
            } catch (Exception $e) {
                $this->window = new Window(Window::UNDEFINED);
            }
        }
        return $this->window;
    }

    /**
     * @return Config
     */
    protected function Config()
    {
        // @codingStandardsIgnoreEnd
        return Config::module('director');
    }
}

<?php

namespace Icinga\Module\Director\Dashboard\Dashlet;

use DirectoryIterator;
use Icinga\Exception\ProgrammingError;
use Icinga\Web\View;
use Icinga\Module\Director\Acl;
use Icinga\Module\Director\Dashboard\Dashboard;

abstract class Dashlet
{
    protected $sectionName;

    protected $icon = 'help';

    protected $supportsLegacyConfig;

    protected $view;

    protected $db;

    protected $stats;

    protected $requiredStats = array();

    public function __construct(Dashboard $dashboard)
    {
        $this->view = $dashboard->getView();
        $this->db = $dashboard->getDb();
    }

    public function listRequiredStats()
    {
        return $this->requiredStats;
    }

    public function addStats($type, $stats)
    {
        $this->stats[$type] = $stats;
    }

    public static function loadAll(Dashboard $dashboard)
    {
        $dashlets = array();

        foreach (new DirectoryIterator(__DIR__) as $file) {
            if ($file->isDot()) {
                continue;
            }
            $filename = $file->getFilename();
            if (preg_match('/^(\w+)Dashlet\.php$/', $filename, $match)) {
                $dashlet = static::loadByName($match[1], $dashboard);

                if ($dashlet->isAllowed()) {
                    $dashlets[] = $dashlet;
                }
            }
        }

        return $dashlets;
    }

    public static function loadByName($name, Dashboard $dashboard)
    {
        $class = __NAMESPACE__ . '\\' . $name . 'Dashlet';
        return new $class($dashboard);
    }

    public static function loadByNames(array $names, Dashboard $dashboard)
    {
        $prefix = __NAMESPACE__ . '\\';
        $dashlets =  array();
        foreach ($names as $name) {
            $class = $prefix . $name . 'Dashlet';
            $dashlet = new $class($dashboard);

            if ($dashlet->isAllowed()) {
                $dashlets[] = $dashlet;
            }
        }

        return $dashlets;
    }

    public function renderClassAttribute()
    {
        $classes = $this->listCssClasses();
        if (empty($classes)) {
            return '';
        } else {
            if (! is_array($classes)) {
                $classes = array($classes);
            }

            return ' class="' . implode(' ', $classes) . '"';
        }
    }

    public function listCssClasses()
    {
        return array();
    }

    public function getSectionName()
    {
        if ($this->sectionName === null) {
            throw new ProgrammingError(
                'Dashlets without a sectionName are not allowed'
            );
        }

        return $this->sectionName;
    }

    public function getIconName()
    {
        return $this->icon;
    }

    public function render()
    {
        return $this->view->partial(
            'dashlets/' . $this->getViewScript() . '.phtml',
            array('dashlet' => $this)
        );
    }

    public function listRequiredPermissions()
    {
        return array($this->getUrl());
    }

    public function getViewScript()
    {
        return 'default';
    }

    public function isAllowed()
    {
        $acl = Acl::instance();
        foreach ($this->listRequiredPermissions() as $perm) {
            if (! $acl->hasPermission($perm)) {
                return false;
            }
        }

        return true;
    }

    public function getSummary()
    {
        $result = '';
        if (! empty($this->requiredStats)) {
            $result .= $this->statSummary(current($this->requiredStats));
        }

        return $result;
    }

    public function getEscapedSummary()
    {
        return $this->view->escape(
            $this->getSummary()
        );
    }

    protected function translate($msg)
    {
        return $this->view->translate($msg);
    }

    protected function statSummary($type)
    {
        $view = $this->view;
        $stat = $this->stats[$type];

        if ((int) $stat->cnt_total === 0) {
            return $view->translate('No object has been defined yet');
        }

        if ((int) $stat->cnt_total === 1) {
            if ($stat->cnt_template > 0) {
                $msg = $view->translate('One template has been defined');
            } elseif ($stat->cnt_external > 0) {
                $msg = $view->translate(
                    'One external object has been defined, it will not be deployed'
                );
            } else {
                $msg = $view->translate('One object has been defined');
            }

        } else {
            $msg = sprintf(
                $view->translate('%d objects have been defined'),
                $stat->cnt_total
            );
        }

        $extra = array();
        if ($stat->cnt_total !== $stat->cnt_object) {

            if ($stat->cnt_template > 0) {
                $extra[] = sprintf(
                    $view->translate('%d of them are templates'),
                    $stat->cnt_template
                );
            }
            if ($stat->cnt_external > 0) {
                $extra[] = sprintf(
                    $view->translate(
                        '%d have been externally defined and will not be deployed'
                    ),
                    $stat->cnt_external
                );
            }
        }

        if (array_key_exists($type . 'group', $this->stats)) {
            $groupstat = $this->stats[$type . 'group'];
            if ((int) $groupstat->cnt_total === 0) {
                $extra[] = $view->translate('no related group exists');
            } elseif ((int) $groupstat->cnt_total === 1) {
                $extra[] = $view->translate('one related group exists');
            } else {
                $extra[] = sprintf(
                    $view->translate('%s related group objects have been created'),
                    $groupstat->cnt_total
                );
            }
        }

        if (empty($extra)) {
            return $msg;
        }

        return $msg . ', ' . implode(', ', $extra);
    }
}

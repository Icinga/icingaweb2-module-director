<?php

namespace Icinga\Module\Director\Dashboard\Dashlet;

use DirectoryIterator;
use Icinga\Exception\ProgrammingError;
use Icinga\Module\Director\Acl;
use Icinga\Module\Director\Dashboard\Dashboard;
use dipl\Html\BaseHtmlElement;
use dipl\Html\Html;
use dipl\Html\Icon;
use dipl\Html\Link;
use dipl\Translation\TranslationHelper;

abstract class Dashlet extends BaseHtmlElement
{
    use TranslationHelper;

    protected $tag = 'li';

    protected $sectionName;

    protected $icon = 'help';

    protected $supportsLegacyConfig;

    /** @var \Icinga\Module\Director\Db */
    protected $db;

    protected $stats;

    protected $requiredStats = array();

    public function __construct(Dashboard $dashboard)
    {
        $this->db = $dashboard->getDb();
    }

    /**
     * @return string[]
     */
    public function listRequiredStats()
    {
        return $this->requiredStats;
    }

    public function addStats($type, $stats)
    {
        $this->stats[$type] = $stats;
    }

    /**
     * @deprecated This is obsolete, should not be used
     * @param Dashboard $dashboard
     * @return array
     */
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
            /** @var Dashlet $dashlet */
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

    abstract public function getTitle();

    abstract public function getUrl();

    public function renderContent()
    {
        $this->add(
            Link::create(
                [
                    $this->getTitle(),
                    Icon::create($this->getIconName()),
                    Html::tag('p', null, $this->getSummary())
                ],
                $this->getUrl(),
                null,
                ['class' => $this->listCssClasses()]
            )
        );

        return parent::renderContent();
    }

    public function listRequiredPermissions()
    {
        return array($this->getUrl());
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

    public function shouldBeShown()
    {
        return true;
    }

    public function getSummary()
    {
        $result = '';
        if (! empty($this->requiredStats)) {
            reset($this->requiredStats);
            $result .= $this->statSummary(current($this->requiredStats));
        }

        return $result;
    }

    public function getStats($type, $name = null)
    {
        if ($name === null) {
            return $this->stats[$type];
        } else {
            return $this->stats[$type]->{'cnt_' . $name};
        }
    }

    protected function getTemplateSummaryText($type)
    {
        $cnt = (int) $this->stats[$type]->cnt_template;

        if ($cnt === 0) {
            return $this->translate('No template has been defined yet');
        }

        if ($cnt === 1) {
            return $this->translate('One template has been defined');
        }

        return sprintf(
            $this->translate('%d templates have been defined'),
            $cnt
        );
    }

    protected function getApplySummaryText($type)
    {
        $cnt = (int) $this->stats[$type]->cnt_apply;

        if ($cnt === 0) {
            return $this->translate('No apply rule has been defined yet');
        }

        if ($cnt === 1) {
            return $this->translate('One apply rule has been defined');
        }

        return sprintf(
            $this->translate('%d apply rules have been defined'),
            $cnt
        );
    }

    protected function statSummary($type)
    {
        $stat = $this->stats[$type];

        if ((int) $stat->cnt_total === 0) {
            return $this->translate('No object has been defined yet');
        }

        if ((int) $stat->cnt_total === 1) {
            if ($stat->cnt_template > 0) {
                $msg = $this->translate('One template has been defined');
            } elseif ($stat->cnt_external > 0) {
                $msg = $this->translate(
                    'One external object has been defined, it will not be deployed'
                );
            } else {
                $msg = $this->translate('One object has been defined');
            }
        } else {
            $msg = sprintf(
                $this->translate('%d objects have been defined'),
                $stat->cnt_total
            );
        }

        $extra = array();
        if ($stat->cnt_total !== $stat->cnt_object) {
            if ($stat->cnt_template > 0) {
                $extra[] = sprintf(
                    $this->translate('%d of them are templates'),
                    $stat->cnt_template
                );
            }

            if ($stat->cnt_external > 0) {
                $extra[] = sprintf(
                    $this->translate(
                        '%d have been externally defined and will not be deployed'
                    ),
                    $stat->cnt_external
                );
            }
        }

        if (array_key_exists($type . 'group', $this->stats)) {
            $groupstat = $this->stats[$type . 'group'];
            if ((int) $groupstat->cnt_total === 0) {
                $extra[] = $this->translate('no related group exists');
            } elseif ((int) $groupstat->cnt_total === 1) {
                $extra[] = $this->translate('one related group exists');
            } else {
                $extra[] = sprintf(
                    $this->translate('%s related group objects have been created'),
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

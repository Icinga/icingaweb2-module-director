<?php

namespace Icinga\Module\Director\Dashboard\Dashlet;

use Icinga\Module\Director\Acl;
use Icinga\Module\Director\Db;
use ipl\Html\BaseHtmlElement;
use ipl\Html\Html;
use gipfl\IcingaWeb2\Icon;
use gipfl\IcingaWeb2\Link;
use ipl\I18n\Translation;

abstract class Dashlet extends BaseHtmlElement
{
    use Translation;

    /** @var Db */
    protected $db;

    protected $tag = 'li';

    protected $icon = 'help';

    protected $stats;

    protected $requiredStats = [];

    public function __construct(Db $db)
    {
        $this->db = $db;
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
     * @param $name
     * @param Db $db
     * @return Dashlet
     */
    public static function loadByName($name, Db $db)
    {
        /** @var Dashlet */
        $class = __NAMESPACE__ . '\\' . $name . 'Dashlet';
        return new $class($db);
    }

    public static function loadByNames(array $names, Db $db)
    {
        $dashlets =  [];
        foreach ($names as $name) {
            $dashlet = static::loadByName($name, $db);

            if ($dashlet->isAllowed()) {
                $dashlets[] = $dashlet;
            }
        }

        return $dashlets;
    }

    public function listCssClasses()
    {
        return [];
    }

    public function getIconName()
    {
        return $this->icon;
    }

    abstract public function getTitle();

    abstract public function getUrl();

    protected function assemble()
    {
        $this->add(Link::create([
            $this->getTitle(),
            Icon::create($this->getIconName()),
            Html::tag('p', null, $this->getSummary())
        ], $this->getUrl(), null, [
            'class' => $this->listCssClasses()
        ]));
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

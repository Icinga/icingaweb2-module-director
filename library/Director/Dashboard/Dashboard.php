<?php

namespace Icinga\Module\Director\Dashboard;

use Exception;
use gipfl\Translation\TranslationHelper;
use gipfl\IcingaWeb2\Widget\Tabs;
use ipl\Html\Html;
use ipl\Html\HtmlDocument;
use ipl\Html\HtmlString;
use Icinga\Authentication\Auth;
use Icinga\Module\Director\Objects\IcingaObject;
use Icinga\Module\Director\Restriction\HostgroupRestriction;
use Icinga\Module\Director\Dashboard\Dashlet\Dashlet;
use Icinga\Module\Director\Db;
use Icinga\Web\Widget\Tab;
use ipl\Html\ValidHtml;
use Zend_Db_Select as ZfSelect;

abstract class Dashboard extends HtmlDocument
{
    use TranslationHelper;

    protected $name;

    /** @var  Dashlet[] */
    protected $dashlets;

    protected $dashletNames;

    /** @var  Db */
    protected $db;

    protected function __construct()
    {
        // This used to be final, which turned out to be too strict
        // Please make sure to always set $this->db and $this->name
        // Hint: some interface might help
    }

    /**
     * @param $name
     * @param Db $db
     *
     * @return self
     */
    public static function loadByName($name, Db $db)
    {
        $class = __NAMESPACE__ . '\\' . ucfirst($name) . 'Dashboard';
        $dashboard = new $class();
        $dashboard->db = $db;
        $dashboard->name = $name;
        return $dashboard;
    }

    public static function exists($name)
    {
        return class_exists(__NAMESPACE__ . '\\' . ucfirst($name) . 'Dashboard');
    }

    /**
     * @param $description
     * @return $this
     */
    protected function addDescription($description)
    {
        if ($description instanceof ValidHtml) {
            $this->add(Html::tag('p', $description));
        } elseif ($description !== null) {
            $this->add(Html::tag(
                'p',
                null,
                HtmlString::create(nl2br(Html::escape($description)))
            ));
        }

        return $this;
    }

    public function render()
    {
        $this
            ->setSeparator("\n")
            ->add(Html::tag('h1', null, $this->getTitle()))
            ->addDescription($this->getDescription())
            ->add($this->renderDashlets());

        return parent::render();
    }

    public function renderDashlets()
    {
        $ul = Html::tag('ul', [
            'class' => 'main-actions',
            'data-base-target' => '_next'
        ]);

        foreach ($this->dashlets() as $dashlet) {
            if ($dashlet->shouldBeShown()) {
                $ul->add($dashlet);
            }
        }

        return $ul;
    }

    public function getName()
    {
        return $this->name;
    }

    abstract public function getTitle();

    public function getDescription()
    {
        return null;
    }

    public function getTabs()
    {
        $lName = $this->getName();
        $tabs = new Tabs();
        $tabs->add($lName, new Tab([
            'label' => $this->translate(ucfirst($this->getName())),
            'url'   => 'director/dashboard',
            'urlParams' => ['name' => $lName]
        ]));

        return $tabs;
    }

    protected function createTabsForDashboards($names)
    {
        $tabs = new Tabs();
        foreach ($names as $name) {
            $dashboard = Dashboard::loadByName($name, $this->getDb());
            if ($dashboard->isAvailable()) {
                $tabs->add($name, $this->createTabForDashboard($dashboard));
            }
        }

        return $tabs;
    }

    protected function createTabForDashboard(Dashboard $dashboard)
    {
        $name = $dashboard->getName();
        return new Tab([
            'label' => $this->translate(ucfirst($name)),
            'url'   => 'director/dashboard',
            'urlParams' => ['name' => $name]
        ]);
    }

    public function count()
    {
        return count($this->dashlets());
    }

    public function isAvailable()
    {
        return $this->count() > 0;
    }

    public function getDb()
    {
        return $this->db;
    }

    public function dashlets()
    {
        if ($this->dashlets === null) {
            $this->loadDashlets();
            $this->fetchDashletSummaries();
        }

        return $this->dashlets;
    }

    public function loadDashlets()
    {
        $names = $this->getDashletNames();

        if (empty($names)) {
            $this->dashlets = array();
        } else {
            $this->dashlets = Dashlet::loadByNames(
                $this->dashletNames,
                $this->getDb()
            );
        }
    }

    public function getDashletNames()
    {
        return $this->dashletNames;
    }

    protected function fetchDashletSummaries()
    {
        $types = array();
        foreach ($this->dashlets as $dashlet) {
            foreach ($dashlet->listRequiredStats() as $objectType) {
                $types[$objectType] = $objectType;
            }
        }

        if (empty($types)) {
            return;
        }

        try {
            $stats = $this->getObjectSummary($types);
        } catch (Exception $e) {
            $stats = array();
        }

        $failing = array();
        foreach ($this->dashlets as $key => $dashlet) {
            foreach ($dashlet->listRequiredStats() as $objectType) {
                if (array_key_exists($objectType, $stats)) {
                    $dashlet->addStats($objectType, $stats[$objectType]);
                } else {
                    $failing[] = $key;
                }
            }
        }

        foreach ($failing as $key) {
            unset($this->dashlets[$key]);
        }
    }

    public function getObjectSummary($types)
    {
        $queries = array();

        foreach ($types as $type) {
            $queries[] = $this->makeSummaryQuery($type);
        }
        $query = $this->db->select()->union($queries, ZfSelect::SQL_UNION_ALL);

        $result = array();
        foreach ($this->db->fetchAll($query) as $row) {
            $result[$row->icinga_type] = $row;
        }

        return $result;
    }

    protected function makeSummaryQuery($type)
    {
        $columns = array(
            'icinga_type'  => "('" . $type . "')",
            'cnt_object'   => $this->getCntSql('object'),
            'cnt_template' => $this->getCntSql('template'),
            'cnt_external' => $this->getCntSql('external_object'),
            'cnt_apply'    => $this->getCntSql('apply'),
            'cnt_total'    => 'COUNT(*)',
        );

        if ($this->db->isPgsql()) {
            $dummy = IcingaObject::createByType($type);
            if (! $dummy->supportsApplyRules()) {
                $columns['cnt_apply'] = '(0)';
            }
        }

        $query = $this->db->getDbAdapter()->select()->from(
            array('o' => 'icinga_' . $type),
            $columns
        );

        return $this->applyRestrictions($type, $query);
    }

    protected function applyRestrictions($type, $query)
    {
        switch ($type) {
            case 'host':
            case 'hostgroup':
                $r = new HostgroupRestriction($this->getDb(), $this->getAuth());
                $r->applyToQuery($query);
                break;
        }

        return $query;
    }

    protected function applyHostgroupRestrictions($query)
    {
        $restrictions = new HostgroupRestriction($this->getDb(), $this->getAuth());
        $restrictions->applyToHostGroupsQuery($query);
    }

    protected function getAuth()
    {
        return Auth::getInstance();
    }

    protected function getCntSql($objectType)
    {
        return sprintf(
            "COALESCE(SUM(CASE WHEN o.object_type = '%s' THEN 1 ELSE 0 END), 0)",
            $objectType
        );
    }
}

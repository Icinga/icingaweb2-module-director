<?php

namespace Icinga\Module\Director\Dashboard;

use Countable;
use Exception;
use Icinga\Authentication\Auth;
use Icinga\Module\Director\Objects\IcingaObject;
use Icinga\Module\Director\Restriction\BetaHostgroupRestriction;
use Icinga\Web\View;
use Icinga\Module\Director\Dashboard\Dashlet\Dashlet;
use Icinga\Module\Director\Db;
use Zend_Db_Select as ZfSelect;

abstract class Dashboard implements Countable
{
    protected $name;

    /** @var  Dashlet[] */
    protected $dashlets;

    protected $dashletNames;

    /** @var  Db */
    protected $db;

    /** @var View */
    protected $view;

    final private function __construct()
    {
    }

    /**
     * @param $name
     * @param Db $db
     * @param View $view
     *
     * @return self
     */
    public static function loadByName($name, Db $db, View $view)
    {
        $class = __NAMESPACE__ . '\\' . ucfirst($name) . 'Dashboard';
        $dashboard = new $class();
        $dashboard->db = $db;
        $dashboard->name = $name;
        $dashboard->view = $view;
        return $dashboard;
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

    public function getView()
    {
        return $this->view;
    }

    protected function translate($msg)
    {
        return $this->view->translate($msg);
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
                $this
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
            case 'hostgroup':
                $r = new BetaHostgroupRestriction($this->getDb(), $this->getAuth());
                $r->applyToHostGroupsQuery($query);
                break;
            case 'host':
                $r = new BetaHostgroupRestriction($this->getDb(), $this->getAuth());
                $r->applyToHostsQuery($query, 'o.id');
                break;
        }

        return $query;
    }

    protected function applyHostgroupRestrictions($query)
    {
        $restrictions = new BetaHostgroupRestriction($this->getDb(), $this->getAuth());
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

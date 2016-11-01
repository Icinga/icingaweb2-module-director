<?php

namespace Icinga\Module\Director\Dashboard;

use Countable;
use Exception;
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

    abstract public function getDescription();

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
            if ($this->dashletNames === null) {
                $this->dashlets = Dashlet::loadAll($this);
            } else {
                $this->dashlets = Dashlet::loadByNames(
                    $this->dashletNames,
                    $this
                );
            }
            $this->fetchDashletSummaries();
        }

        return $this->dashlets;
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
        return $this->db->select()->from(
            array('o' => 'icinga_' . $type),
            array(
                'icinga_type'  => "('" . $type . "')",
                'cnt_object'   => $this->getCntSql('object'),
                'cnt_template' => $this->getCntSql('template'),
                'cnt_external' => $this->getCntSql('external_object'),
                'cnt_total'    => 'COUNT(*)',
            )
        );
    }

    protected function getCntSql($objectType)
    {
        return sprintf(
            "COALESCE(SUM(CASE WHEN o.object_type = '%s' THEN 1 ELSE 0 END), 0)",
            $objectType
        );
    }
}

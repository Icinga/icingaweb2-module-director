<?php

namespace Icinga\Module\Director\Web\Table;

use Icinga\Authentication\Auth;
use Icinga\Module\Director\Db;
use Icinga\Module\Director\Objects\IcingaObject;
use Icinga\Module\Director\Restriction\HostgroupRestriction;
use Icinga\Module\Director\Restriction\ObjectRestriction;
use ipl\Html\Link;
use ipl\Web\Url;
use Zend_Db_Select as ZfSelect;

class ObjectsTable extends QueryBasedTable
{
    /** @var ObjectRestriction[] */
    protected $objectRestrictions;

    protected $columns = [
        'object_name' => 'o.object_name',
        'id'          => 'o.id',
    ];

    protected $searchColumns = ['o.object_name'];

    protected $showColumns = ['object_name' => 'Name'];

    protected $type;

    private $auth;

    /**
     * @param $type
     * @param Db $db
     * @return static
     */
    public static function create($type, Db $db)
    {
        $class = __NAMESPACE__ . '\\ObjectsTable' . ucfirst($type);
        if (! class_exists($class)) {
            $class = __CLASS__;
        }

        /** @var static $table */
        $table = new $class($db);
        $table->type = $type;
        return $table;
    }

    public function getType()
    {
        return $this->type;
    }

    public function getAuth()
    {
        return $this->auth;
    }

    public function setAuth(Auth $auth)
    {
        $this->auth = $auth;
        return $this;
    }

    public function addObjectRestriction(ObjectRestriction $restriction)
    {
        $this->objectRestrictions[$restriction->getName()] = $restriction;
        return $this;
    }

    public function getColumns()
    {
        return $this->columns;
    }

    public function getColumnsToBeRendered()
    {
        return $this->showColumns;
    }

    protected function getMainLinkLabel($row)
    {
        return $row->object_name;
    }

    protected function renderObjectNameColumn($row)
    {
        $type = $this->getType();
        $url = Url::fromPath("director/${type}", [
            'name' => $row->object_name
        ]);

        return static::td(Link::create($this->getMainLinkLabel($row), $url));
    }

    protected function renderExtraColumns($row)
    {
        $columns = $this->getColumnsToBeRendered();
        unset($columns['object_name']);
        $cols = [];
        foreach ($columns as $key => & $label) {
            $cols[] = static::td($row->$key);
        }

        return $cols;
    }

    public function renderRow($row)
    {
        $tr = static::tr([
            $this->renderObjectNameColumn($row),
            $this->renderExtraColumns($row)
        ]);

        $classes = $this->getRowClasses($row);
        if (! empty($classes)) {
            $tr->attributes()->add('class', $classes);
        }

        return $tr;
    }

    protected function applyObjectTypeFilter(ZfSelect $query)
    {
        return $query->where("o.object_type = 'object'");
    }

    protected function applyRestrictions(ZfSelect $query)
    {
        foreach ($this->getRestrictions() as $restriction) {
            $restriction->applyToQuery($query);
        }

        return $query;
    }

    protected function getRestrictions()
    {
        if ($this->objectRestrictions === null) {
            $this->objectRestrictions = $this->loadRestrictions();
        }

        return $this->objectRestrictions;
    }

    protected function loadRestrictions()
    {
        return [
            new HostgroupRestriction($this->connection(), $this->getAuth())
        ];
    }

    protected function prepareQuery()
    {
        $type = $this->getType();
        $object = IcingaObject::createByType($type);
        $table = $object->getTableName();
        $query = $this->applyRestrictions($this->db()->select()
            ->from(
                ['o' => $table],
                $this->getColumns()
            )
            ->order('o.object_name')
        );

        return $this->applyObjectTypeFilter($query);
    }
}

<?php

namespace Icinga\Module\Director\Web\Table;

use Icinga\Authentication\Auth;
use Icinga\Module\Director\Db;
use Icinga\Module\Director\Db\IcingaObjectFilterHelper;
use Icinga\Module\Director\Objects\IcingaObject;
use Icinga\Module\Director\Restriction\FilterByNameRestriction;
use Icinga\Module\Director\Restriction\HostgroupRestriction;
use Icinga\Module\Director\Restriction\ObjectRestriction;
use gipfl\IcingaWeb2\Link;
use gipfl\IcingaWeb2\Table\ZfQueryBasedTable;
use gipfl\IcingaWeb2\Url;
use Ramsey\Uuid\Uuid;
use Zend_Db_Select as ZfSelect;

class ObjectsTable extends ZfQueryBasedTable
{
    /** @var ObjectRestriction[] */
    protected $objectRestrictions;

    protected $columns = [
        'object_name' => 'o.object_name',
        'object_type' => 'o.object_type',
        'disabled'    => 'o.disabled',
        'uuid'        => 'o.uuid',
    ];

    protected $searchColumns = ['o.object_name'];

    protected $showColumns = ['object_name' => 'Name'];

    protected $filterObjectType = 'object';

    protected $type;

    protected $baseObjectUrl;

    /** @var IcingaObject */
    protected $dummyObject;

    /** @var Auth */
    private $auth;

    public function __construct($db, Auth $auth)
    {
        $this->auth = $auth;
        parent::__construct($db);
    }

    /**
     * @param $type
     * @param Db $db
     * @return static
     */
    public static function create($type, Db $db, Auth $auth)
    {
        $class = __NAMESPACE__ . '\\ObjectsTable' . ucfirst($type);
        if (! class_exists($class)) {
            $class = __CLASS__;
        }

        /** @var static $table */
        $table = new $class($db, $auth);
        $table->type = $type;
        return $table;
    }

    public function getType()
    {
        return $this->type;
    }

    /**
     * @param string $url
     * @return $this
     */
    public function setBaseObjectUrl($url)
    {
        $this->baseObjectUrl = $url;

        return $this;
    }

    public function filterObjectType($type)
    {
        $this->filterObjectType = $type;
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

    public function filterTemplate(
        IcingaObject $template,
        $inheritance = Db\IcingaObjectFilterHelper::INHERIT_DIRECT
    ) {
        IcingaObjectFilterHelper::filterByTemplate(
            $this->getQuery(),
            $template,
            'o',
            $inheritance
        );

        return $this;
    }

    protected function getMainLinkLabel($row)
    {
        return $row->object_name;
    }

    protected function renderObjectNameColumn($row)
    {
        $type = $this->baseObjectUrl;
        $url = Url::fromPath("director/{$type}", [
            'uuid' => Uuid::fromBytes($row->uuid)->toString()
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
        if (isset($row->uuid) && is_resource($row->uuid)) {
            $row->uuid = stream_get_contents($row->uuid);
        }
        $tr = static::tr([
            $this->renderObjectNameColumn($row),
            $this->renderExtraColumns($row)
        ]);

        $classes = $this->getRowClasses($row);
        if ($row->disabled === 'y') {
            $classes[] = 'disabled';
        }
        if (! empty($classes)) {
            $tr->getAttributes()->add('class', $classes);
        }

        return $tr;
    }

    protected function getRowClasses($row)
    {
        return [];
    }

    protected function applyObjectTypeFilter(ZfSelect $query)
    {
        return $query->where(
            'o.object_type = ?',
            $this->filterObjectType
        );
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
        /** @var Db $db */
        $db = $this->connection();
        $dummyObject = $this->getDummyObject();
        $type = $dummyObject->getShortTableName();

        if ($dummyObject->isApplyRule()) {
            return [new FilterByNameRestriction($db, $this->auth, $type)];
        } else {
            return [new HostgroupRestriction($db, $this->auth)];
        }
    }

    /**
     * @return IcingaObject
     */
    protected function getDummyObject()
    {
        if ($this->dummyObject === null) {
            $type = $this->getType();
            $this->dummyObject = IcingaObject::createByType($type);
        }
        return $this->dummyObject;
    }

    protected function prepareQuery()
    {
        $table = $this->getDummyObject()->getTableName();
        $columns = $this->getColumns();
        $query = $this->db()->select()->from(['o' => $table], $columns);

        $this->applyObjectTypeFilter($query);
        $query = $this->applyRestrictions($query);
        $query->order('o.object_name')->limit(100);

        return $query;
    }

    public function removeQueryLimit()
    {
        $query = $this->getQuery();
        $query->reset($query::LIMIT_OFFSET);
        $query->reset($query::LIMIT_COUNT);

        return $this;
    }
}

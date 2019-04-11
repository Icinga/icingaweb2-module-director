<?php

namespace Icinga\Module\Director\Web\Table;

use Icinga\Authentication\Auth;
use Icinga\Module\Director\Db;
use Icinga\Module\Director\Db\IcingaObjectFilterHelper;
use Icinga\Module\Director\Objects\IcingaObject;
use Icinga\Module\Director\Restriction\FilterByNameRestriction;
use Icinga\Module\Director\Restriction\HostgroupRestriction;
use Icinga\Module\Director\Restriction\ObjectRestriction;
use dipl\Html\Link;
use dipl\Web\Table\ZfQueryBasedTable;
use dipl\Web\Url;
use Zend_Db_Select as ZfSelect;

class ObjectsTable extends ZfQueryBasedTable
{
    /** @var ObjectRestriction[] */
    protected $objectRestrictions;

    protected $columns = [
        'object_name' => 'o.object_name',
        'disabled'    => 'o.disabled',
        'id'          => 'o.id',
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

    /**
     * @param string $url
     * @return $this
     */
    public function setBaseObjectUrl($url)
    {
        $this->baseObjectUrl = $url;

        return $this;
    }

    /**
     * @return Auth
     */
    public function getAuth()
    {
        return $this->auth;
    }

    public function setAuth(Auth $auth)
    {
        $this->auth = $auth;
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
        if ($row->disabled === 'y') {
            $classes[] = 'disabled';
        }
        if (! empty($classes)) {
            $tr->getAttributes()->add('class', $classes);
        }

        return $tr;
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
        $db = $this->connection();
        $auth = $this->getAuth();

        return [
            new HostgroupRestriction($db, $auth),
            new FilterByNameRestriction($db, $auth, $this->getDummyObject()->getShortTableName())
        ];
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
        $query = $this->applyRestrictions(
            $this->db()
                ->select()
                ->from(
                    ['o' => $table],
                    $this->getColumns()
                )
                ->order('o.object_name')->limit(100)
        );

        return $this->applyObjectTypeFilter($query);
    }
}

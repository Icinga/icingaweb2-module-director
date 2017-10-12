<?php

namespace Icinga\Module\Director\Restriction;

use dipl\Db\Zf1\FilterRenderer;
use Icinga\Authentication\Auth;
use Icinga\Data\Filter\Filter;
use Icinga\Module\Director\Db;
use Icinga\Module\Director\Objects\IcingaObject;
use Zend_Db_Select as ZfSelect;

class FilterByNameRestriction extends ObjectRestriction
{
    protected $type;

    /** @var Filter */
    protected $filter;

    public function __construct(Db $connection, Auth $auth, $type)
    {
        parent::__construct($connection, $auth);
        $this->setType($type);
    }

    protected function setType($type)
    {
        $this->type = $type;
        $this->setNameForType($type);
    }

    protected function setNameForType($type)
    {
        $this->name = "director/${type}/filter-by-name";
    }

    public function allows(IcingaObject $object)
    {
        if (! $this->isRestricted()) {
            return true;
        }

        return $this->prepareFilter()->matches([
            (object) ['object_name' => $object->getObjectName()]
        ]);
    }

    public function getFilter()
    {
        if ($this->filter === null) {
            $this->filter = $this->prepareFilter();
        }

        return $this->filter;
    }

    protected function filterQuery(ZfSelect $query, $tableAlias = 'o')
    {
        FilterRenderer::applyToQuery($this->getFilter(), $query);
    }

    protected function prepareFilter()
    {
        $filter = Filter::matchAll();
        foreach ($this->auth->getRestrictions($this->name) as $restriction) {
            $filter->addFilter(Filter::expression('object_name', '=', $restriction));
        }

        return $filter;
    }

    protected function preparePrefixedFilter($prefix)
    {
        $filter = Filter::matchAll();
        foreach ($this->auth->getRestrictions($this->name) as $restriction) {
            $filter->addFilter(
                Filter::expression("$prefix.object_name", '=', $restriction)
            );
        }

        return $filter;
    }
}

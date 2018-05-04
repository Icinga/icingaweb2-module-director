<?php

namespace Icinga\Module\Director\Web\Table;

use Icinga\Authentication\Auth;
use Icinga\Data\Filter\Filter;
use Icinga\Exception\IcingaException;
use Icinga\Module\Director\Db;
use Icinga\Module\Director\Db\IcingaObjectFilterHelper;
use Icinga\Module\Director\IcingaConfig\AssignRenderer;
use Icinga\Module\Director\Objects\IcingaObject;
use dipl\Db\Zf1\FilterRenderer;
use dipl\Html\Icon;
use dipl\Html\Link;
use dipl\Web\Table\ZfQueryBasedTable;
use dipl\Web\Url;
use Zend_Db_Select as ZfSelect;

class ApplyRulesTable extends ZfQueryBasedTable
{
    protected $searchColumns = [
        'o.object_name',
        'o.assign_filter',
    ];

    private $type;

    public static function create($type, Db $db)
    {
        $table = new static($db);
        $table->setType($type);
        return $table;
    }

    public function setType($type)
    {
        $this->type = $type;
        return $this;
    }

    public function getColumnsToBeRendered()
    {
        return ['Name', 'assign where'/*, 'Actions'*/];
    }

    public function renderRow($row)
    {
        $url = Url::fromPath("director/{$this->type}/edit", [
            'id' => $row->id,
        ]);

        $tr = static::tr([
            static::td(Link::create($row->object_name, $url)),
            static::td($this->renderApplyFilter($row->assign_filter)),
            // NOT (YET) static::td($this->createActionLinks($row))->setSeparator(' ')
        ]);

        if ($row->disabled === 'y') {
            $tr->getAttributes()->add('class', 'disabled');
        }

        return $tr;
    }

    public function filterTemplate(
        IcingaObject $template,
        $inheritance = IcingaObjectFilterHelper::INHERIT_DIRECT
    ) {
        IcingaObjectFilterHelper::filterByTemplate(
            $this->getQuery(),
            $template,
            'o',
            $inheritance
        );

        return $this;
    }

    protected function renderApplyFilter($assignFilter)
    {
        try {
            $string = AssignRenderer::forFilter(
                Filter::fromQueryString($assignFilter)
            )->renderAssign();
            // Do not prefix it
            $string = preg_replace('/^assign where /', '', $string);
        } catch (IcingaException $e) {
            // ignore errors in filter rendering
            $string = 'Error in Filter rendering: ' . $e->getMessage();
        }

        return $string;
    }

    public function createActionLinks($row)
    {
        $type = $this->type;
        $links = [];
        $links[] = Link::create(
            Icon::create('sitemap'),
            "director/${type}template/applytargets",
            ['id' => $row->id],
            ['title' => $this->translate('Show affected Objects')]
        );

        $links[] = Link::create(
            Icon::create('edit'),
            "director/$type/edit",
            ['id' => $row->id],
            ['title' => $this->translate('Modify this Apply Rule')]
        );

        $links[] = Link::create(
            Icon::create('doc-text'),
            "director/$type/render",
            ['id' => $row->id],
            ['title' => $this->translate('Apply Rule rendering preview')]
        );

        $links[] = Link::create(
            Icon::create('history'),
            "director/$type/history",
            ['id' => $row->id],
            ['title' => $this->translate('Apply rule history')]
        );

        return $links;
    }

    protected function applyRestrictions(ZfSelect $query)
    {
        $auth = Auth::getInstance();
        $type = $this->type;
        $restrictions = $auth->getRestrictions("director/$type/apply/filter-by-name");
        if (empty($restrictions)) {
            return $query;
        }

        $filter = Filter::matchAny();
        foreach ($restrictions as $restriction) {
            $filter->addFilter(Filter::where('o.object_name', $restriction));
        }

        return FilterRenderer::applyToQuery($filter, $query);
    }

    public function prepareQuery()
    {
        $type = $this->type;
        $columns = [
            'id'            => 'o.id',
            'object_name'   => 'o.object_name',
            'disabled'      => 'o.disabled',
            'assign_filter' => 'o.assign_filter',
        ];
        $query = $this->db()->select()->from(
            ['o' => "icinga_$type"],
            $columns
        )->where(
            "object_type = 'apply'"
        )->order('o.object_name');

        if ($type === 'service') {
            $query->where('service_set_id IS NULL');
        }

        return $this->applyRestrictions($query);
    }
}

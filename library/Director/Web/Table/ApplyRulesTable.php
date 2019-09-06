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

    /** @var IcingaObject */
    protected $dummyObject;

    protected $baseObjectUrl;

    protected $linkWithName = false;

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

    public function setBaseObjectUrl($url)
    {
        $this->baseObjectUrl = $url;

        return $this;
    }

    public function createLinksWithNames($linksWithName = true)
    {
        $this->linkWithName = (bool) $linksWithName;

        return $this;
    }

    public function getType()
    {
        return $this->type;
    }

    public function getColumnsToBeRendered()
    {
        return ['Name', 'assign where'/*, 'Actions'*/];
    }

    public function renderRow($row)
    {
        if ($this->linkWithName) {
            $params = ['name' => $row->object_name];
        } else {
            $params = ['id' => $row->id];
        }
        $url = Url::fromPath("director/{$this->baseObjectUrl}/edit", $params);

        $assignWhere = $this->renderApplyFilter($row->assign_filter);

        if (! empty($row->apply_for)) {
            $assignWhere = sprintf('apply for %s / %s', $row->apply_for, $assignWhere);
        }

        $tr = static::tr([
            static::td(Link::create($row->object_name, $url)),
            static::td($assignWhere),
            // NOT (YET) static::td($this->createActionLinks($row))->setSeparator(' ')
        ]);

        if ($row->disabled === 'y') {
            $tr->getAttributes()->add('class', 'disabled');
        }

        return $tr;
    }

    /**
     * Should be triggered from renderRow, still unused.
     *
     * @param IcingaObject $template
     * @param string $inheritance
     * @return $this
     * @throws \Icinga\Exception\ProgrammingError
     */
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
        $baseUrl = 'director/' . $this->baseObjectUrl;
        $links = [];
        $links[] = Link::create(
            Icon::create('sitemap'),
            "${baseUrl}template/applytargets",
            ['id' => $row->id],
            ['title' => $this->translate('Show affected Objects')]
        );

        $links[] = Link::create(
            Icon::create('edit'),
            "$baseUrl/edit",
            ['id' => $row->id],
            ['title' => $this->translate('Modify this Apply Rule')]
        );

        $links[] = Link::create(
            Icon::create('doc-text'),
            "$baseUrl/render",
            ['id' => $row->id],
            ['title' => $this->translate('Apply Rule rendering preview')]
        );

        $links[] = Link::create(
            Icon::create('history'),
            "$baseUrl/history",
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


    /**
     * @return IcingaObject
     */
    protected function getDummyObject()
    {
        if ($this->dummyObject === null) {
            $type = $this->type;
            $this->dummyObject = IcingaObject::createByType($type);
        }
        return $this->dummyObject;
    }

    public function prepareQuery()
    {
        $table = $this->getDummyObject()->getTableName();
        $columns = [
            'id'            => 'o.id',
            'object_name'   => 'o.object_name',
            'disabled'      => 'o.disabled',
            'assign_filter' => 'o.assign_filter',
            'apply_for'     => '(NULL)',
        ];

        if ($table === 'icinga_service') {
            $columns['apply_for'] = 'o.apply_for';
        }
        $query = $this->db()->select()->from(
            ['o' => $table],
            $columns
        )->where(
            "object_type = 'apply'"
        )->order('o.object_name');

        if ($this->type === 'service') {
            $query->where('service_set_id IS NULL');
        }

        return $this->applyRestrictions($query);
    }
}

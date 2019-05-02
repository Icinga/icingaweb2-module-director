<?php

namespace Icinga\Module\Director\Web\Table;

use Icinga\Authentication\Auth;
use Icinga\Data\Filter\Filter;
use Icinga\Module\Director\Db;
use Icinga\Module\Director\Db\IcingaObjectFilterHelper;
use Icinga\Module\Director\Objects\IcingaObject;
use ipl\Html\Html;
use gipfl\IcingaWeb2\Icon;
use gipfl\IcingaWeb2\Link;
use gipfl\IcingaWeb2\Table\Extension\MultiSelect;
use gipfl\IcingaWeb2\Table\ZfQueryBasedTable;
use gipfl\IcingaWeb2\Url;
use gipfl\IcingaWeb2\Zf1\Db\FilterRenderer;
use Zend_Db_Select as ZfSelect;

class TemplatesTable extends ZfQueryBasedTable implements FilterableByUsage
{
    use MultiSelect;

    protected $searchColumns = ['o.object_name'];

    private $type;

    public static function create($type, Db $db)
    {
        $table = new static($db);
        $table->type = strtolower($type);
        return $table;
    }

    protected function assemble()
    {
        $type = $this->type;
        $this->enableMultiSelect(
            "director/${type}s/edittemplates",
            "director/${type}template",
            ['name']
        );
    }

    public function getType()
    {
        return $this->type;
    }

    public function getColumnsToBeRendered()
    {
        return [$this->translate('Template Name')];
    }

    public function renderRow($row)
    {
        $name = $row->object_name;
        $type = str_replace('_', '-', $this->getType());
        $caption = $row->is_used === 'y' ? $name : [
            $name,
            Html::tag(
                'span',
                ['style' => 'font-style: italic'],
                $this->translate(' - not in use -')
            )
        ];

        $url = Url::fromPath("director/${type}template/usage", [
            'name' => $name
        ]);

        return $this::row([
            new Link($caption, $url),
            [
                new Link(new Icon('plus'), "director/$type/add", [
                    'type' => 'object',
                    'imports' => $name
                ]),
                new Link(new Icon('history'), "director/$type/history", [
                    'name' => $name
                ])
            ]
        ]);
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

    public function showOnlyUsed()
    {
        $type = $this->getType();
        $this->getQuery()->where(
            "(EXISTS (SELECT ${type}_id FROM icinga_${type}_inheritance"
            . " WHERE parent_${type}_id = o.id))"
        );
    }

    public function showOnlyUnUsed()
    {
        $type = $this->getType();
        $this->getQuery()->where(
            "(NOT EXISTS (SELECT ${type}_id FROM icinga_${type}_inheritance"
            . " WHERE parent_${type}_id = o.id))"
        );
    }

    protected function applyRestrictions(ZfSelect $query)
    {
        $auth = Auth::getInstance();
        $type = $this->type;
        $restrictions = $auth->getRestrictions("director/$type/template/filter-by-name");
        if (empty($restrictions)) {
            return $query;
        }

        $filter = Filter::matchAny();
        foreach ($restrictions as $restriction) {
            $filter->addFilter(Filter::where('o.object_name', $restriction));
        }

        return FilterRenderer::applyToQuery($filter, $query);
    }

    protected function prepareQuery()
    {
        $type = $this->getType();
        $used = "CASE WHEN EXISTS(SELECT 1 FROM icinga_${type}_inheritance oi"
            . " WHERE oi.parent_${type}_id = o.id) THEN 'y' ELSE 'n' END";

        $columns = [
            'object_name' => 'o.object_name',
            'id'      => 'o.id',
            'is_used' => $used,
        ];
        $query = $this->db()->select()->from(
            ['o' => "icinga_${type}"],
            $columns
        )->where(
            "o.object_type = 'template'"
        )->order('o.object_name');

        return $this->applyRestrictions($query);
    }
}

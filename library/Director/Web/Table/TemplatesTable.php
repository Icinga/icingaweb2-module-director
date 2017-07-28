<?php

namespace Icinga\Module\Director\Web\Table;

use Icinga\Authentication\Auth;
use Icinga\Data\Filter\Filter;
use Icinga\Module\Director\Db;
use Icinga\Module\Director\Db\IcingaObjectFilterHelper;
use Icinga\Module\Director\Objects\IcingaObject;
use ipl\Db\Zf1\FilterRenderer;
use ipl\Html\Html;
use ipl\Html\Icon;
use ipl\Html\Link;
use ipl\Web\Table\ZfQueryBasedTable;
use ipl\Web\Url;
use Zend_Db_Select as ZfSelect;

class TemplatesTable extends ZfQueryBasedTable
{
    protected $searchColumns = ['o.object_name'];

    private $type;

    public static function create($type, Db $db)
    {
        $table = new static($db);
        $table->type = strtolower($type);
        return $table;
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
        $type = $this->getType();
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

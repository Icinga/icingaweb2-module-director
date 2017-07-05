<?php

namespace Icinga\Module\Director\Web\Table;

use Icinga\Module\Director\Db;
use Icinga\Module\Director\Db\IcingaObjectFilterHelper;
use Icinga\Module\Director\Objects\IcingaObject;
use ipl\Html\Html;
use ipl\Html\Icon;
use ipl\Html\Link;
use ipl\Web\Table\ZfQueryBasedTable;
use ipl\Web\Url;

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
        return ['Template name'];
    }

    public function renderRow($row)
    {
        $type = $this->getType();
        $caption = $row->is_used === 'y'
            ? $row->object_name
            : [
                $row->object_name,
                Html::tag(
                    'span',
                    ['style' => 'font-style: italic'],
                    $this->translate(' - not in use -')
                )
            ];

        // TODO: remove this once we have host template usage
        if ($type === 'host') {
            $url = Url::fromPath("director/host", [
                'name' => $row->object_name
            ]);
        } else {
            $url = Url::fromPath("director/${type}template/usage", [
                'name' => $row->object_name
            ]);
        }

        return $this::tr([
            $this::td(new Link($caption, $url)),
            $this::td(new Link(new Icon('plus'), $url))
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

        return $query;
    }
}

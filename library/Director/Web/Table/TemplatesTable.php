<?php

// SPDX-FileCopyrightText: 2018 Icinga GmbH <https://icinga.com>
// SPDX-License-Identifier: GPL-3.0-or-later

namespace Icinga\Module\Director\Web\Table;

use Icinga\Authentication\Auth;
use Icinga\Data\Filter\Filter;
use Icinga\Module\Director\Db;
use Icinga\Module\Director\Db\IcingaObjectFilterHelper;
use Icinga\Module\Director\Objects\IcingaObject;
use InvalidArgumentException;
use ipl\Html\Html;
use gipfl\IcingaWeb2\Icon;
use gipfl\IcingaWeb2\Link;
use gipfl\IcingaWeb2\Table\Extension\MultiSelect;
use gipfl\IcingaWeb2\Table\ZfQueryBasedTable;
use gipfl\IcingaWeb2\Url;
use gipfl\IcingaWeb2\Zf1\Db\FilterRenderer;
use Ramsey\Uuid\Uuid;
use Zend_Db_Select as ZfSelect;

class TemplatesTable extends ZfQueryBasedTable implements FilterableByUsage
{
    use MultiSelect;

    protected $searchColumns = ['o.object_name'];

    private $type;

    /** @var string[] Additional explicitly selected core properties */
    private $additionalColumns = [];

    /**
     * Only core properties which are safe to display in a template overview.
     * Do not accept arbitrary SQL expressions or sensitive custom variables.
     */
    private const ADDITIONAL_CORE_COLUMNS = [
        'display_name'         => 'Display Name',
        'address'              => 'Address',
        'address6'             => 'Address6',
        'check_command'        => 'Check Command',
        'check_period'         => 'Check Period',
        'check_interval'       => 'Check Interval',
        'retry_interval'       => 'Retry Interval',
        'max_check_attempts'   => 'Max Check Attempts',
        'zone'                 => 'Zone',
        'command_endpoint'     => 'Command Endpoint',
        'enable_active_checks' => 'Active Checks',
        'enable_notifications' => 'Notifications',
        'notes'                => 'Notes',
    ];

    private const RELATION_COLUMNS = [
        'check_command'    => ['icinga_command', 'check_command_id'],
        'check_period'     => ['icinga_timeperiod', 'check_period_id'],
        'zone'             => ['icinga_zone', 'zone_id'],
        'command_endpoint' => ['icinga_endpoint', 'command_endpoint_id'],
    ];

    /**
     * Enable explicit core properties via ?add_columns=check_command,address.
     * Only host/service template columns are supported by this first step.
     */
    public function setAdditionalColumns(?string $columns): self
    {
        if ($columns === null || trim($columns) === '') {
            $this->additionalColumns = [];
            return $this;
        }

        $requested = array_values(array_unique(array_map('trim', explode(',', $columns))));
        if (count($requested) > 10) {
            throw new InvalidArgumentException('A maximum of 10 additional template columns is supported');
        }

        $type = $this->getType();
        foreach ($requested as $column) {
            if (
                ! isset(self::ADDITIONAL_CORE_COLUMNS[$column])
                || ($type !== 'host' && $type !== 'service')
                || ($type === 'service' && in_array($column, ['address', 'address6'], true))
            ) {
                throw new InvalidArgumentException('Unsupported template list column: ' . $column);
            }
        }

        $this->additionalColumns = $requested;
        return $this;
    }

    private function additionalColumnExpression(string $column): string
    {
        if (isset(self::RELATION_COLUMNS[$column])) {
            [$table, $foreignKey] = self::RELATION_COLUMNS[$column];
            return "(SELECT rel.object_name FROM $table rel WHERE rel.id = o.$foreignKey)";
        }

        return "o.$column";
    }

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
            "director/{$type}s/edittemplates",
            "director/{$type}template",
            ['name']
        );
    }

    public function getType()
    {
        return $this->type;
    }

    public function getColumnsToBeRendered()
    {
        $columns = [$this->translate('Template Name')];
        foreach ($this->additionalColumns as $column) {
            $columns[] = $this->translate(self::ADDITIONAL_CORE_COLUMNS[$column]);
        }

        return $columns;
    }

    public function renderRow($row)
    {
        $name = $row->object_name;
        $type = str_replace('_', '-', $this->getType());
        $caption = $row->is_used === 'y' ? $name : [
            $name,
            Html::tag(
                'span',
                ['class' => 'font-italic'],
                $this->translate(' - not in use -')
            )
        ];

        $url = Url::fromPath("director/{$type}template/usage", [
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
                    'uuid' => Uuid::fromBytes(Db\DbUtil::binaryResult($row->uuid))->toString(),
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
            "(EXISTS (SELECT {$type}_id FROM icinga_{$type}_inheritance"
            . " WHERE parent_{$type}_id = o.id))"
        );
    }

    public function showOnlyUnUsed()
    {
        $type = $this->getType();
        $this->getQuery()->where(
            "(NOT EXISTS (SELECT {$type}_id FROM icinga_{$type}_inheritance"
            . " WHERE parent_{$type}_id = o.id))"
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
        $used = "CASE WHEN EXISTS(SELECT 1 FROM icinga_{$type}_inheritance oi"
            . " WHERE oi.parent_{$type}_id = o.id) THEN 'y' ELSE 'n' END";

        $columns = [
            'object_name' => 'o.object_name',
            'uuid'    => 'o.uuid',
            'id'      => 'o.id',
            'is_used' => $used,
        ];
        foreach ($this->additionalColumns as $column) {
            $columns[$column] = $this->additionalColumnExpression($column);
        }
        $query = $this->db()->select()->from(
            ['o' => "icinga_{$type}"],
            $columns
        )->where(
            "o.object_type = 'template'"
        )->order('o.object_name');

        return $this->applyRestrictions($query);
    }
}

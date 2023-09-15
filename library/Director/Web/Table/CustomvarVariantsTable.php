<?php

namespace Icinga\Module\Director\Web\Table;

use Icinga\Module\Director\Db;
use Icinga\Module\Director\PlainObjectRenderer;
use gipfl\IcingaWeb2\Link;
use gipfl\IcingaWeb2\Table\ZfQueryBasedTable;
use Zend_Db_Adapter_Abstract as ZfDbAdapter;
use Zend_Db_Select as ZfDbSelect;

class CustomvarVariantsTable extends ZfQueryBasedTable
{
    protected $searchColumns = ['varvalue'];

    protected $varName;

    public static function create(Db $db, $varName)
    {
        $table = new static($db);
        $table->varName = $varName;
        $table->getAttributes()->set('class', 'common-table');
        return $table;
    }

    public function renderRow($row)
    {
        if ($row->format === 'json') {
            $value = PlainObjectRenderer::render(json_decode($row->varvalue));
        } else {
            $value = $row->varvalue;
        }
        $tr = $this::row([
            /* new Link(
                $value,
                'director/customvar/value',
                ['name' => $row->varvalue]
            )*/
            $value
        ]);

        foreach ($this->getObjectTypes() as $type) {
            $cnt = (int) $row->{"cnt_$type"};
            if ($cnt === 0) {
                $cnt = '-';
            }
            $tr->add($this::td($cnt));
        }

        return $tr;
    }

    public function getColumnsToBeRendered()
    {
        return array(
            $this->translate('Variable Value'),
            $this->translate('Commands'),
            $this->translate('Hosts'),
            $this->translate('Services'),
            $this->translate('Service Sets'),
            $this->translate('Notifications'),
            $this->translate('Users'),
        );
    }

    protected function getObjectTypes()
    {
        return ['command', 'host', 'service', 'service_set', 'notification', 'user'];
    }

    public function prepareQuery()
    {
        $db = $this->db();
        $varsColumns = ['varvalue' => 'v.varvalue'];
        $varsTypes = $this->getObjectTypes();
        foreach ($varsTypes as $type) {
            $varsColumns["cnt_$type"] = '(0)';
        }
        $varsQueries = [];
        foreach ($varsTypes as $type) {
            $varsQueries[] = $this->makeVarSub($type, $varsColumns, $db);
        }

        $union = $db->select()->union($varsQueries, ZfDbSelect::SQL_UNION_ALL);

        $columns = [
            'varvalue' => 'u.varvalue',
            'format'   => 'u.format',
        ];
        foreach ($varsTypes as $column) {
            $columns["cnt_$column"] = "SUM(u.cnt_$column)";
        }
        return $db->select()->from(['u' => $union], $columns)
            ->group('u.varvalue')->group('u.format')
            ->order('u.varvalue ASC')
            ->order('u.format ASC')
            ->limit(100);
    }

    /**
     * @param string $type
     * @param array $columns
     * @param ZfDbAdapter $db
     * @return ZfDbSelect
     */
    protected function makeVarSub($type, array $columns, ZfDbAdapter $db)
    {
        $columns["cnt_$type"] = 'COUNT(*)';
        $columns['format'] = 'v.format';
        return $db->select()->from(
            ['v' => "icinga_{$type}_var"],
            $columns
        )->join(
            ['o' => "icinga_{$type}"],
            "o.id = v.{$type}_id",
            []
        )->where(
            'v.varname = ?',
            $this->varName
        )->where(
            'o.object_type != ?',
            'external_object'
        )->group('varvalue')->group('v.format');
    }
}

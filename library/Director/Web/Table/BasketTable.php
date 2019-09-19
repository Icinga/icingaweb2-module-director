<?php

namespace Icinga\Module\Director\Web\Table;

use gipfl\IcingaWeb2\Link;
use gipfl\IcingaWeb2\Table\ZfQueryBasedTable;

class BasketTable extends ZfQueryBasedTable
{
    protected $searchColumns = [
        'basket_name',
    ];

    public function renderRow($row)
    {
        $tr = $this::row([
            new Link(
                $row->basket_name,
                'director/basket',
                ['name' => $row->basket_name]
            ),
            $row->cnt_snapshots
        ]);

        return $tr;
    }

    public function getColumnsToBeRendered()
    {
        return [
            $this->translate('Basket'),
            $this->translate('Snapshots'),
        ];
    }

    public function prepareQuery()
    {
        return $this->db()->select()->from([
            'b' => 'director_basket'
        ], [
            'b.uuid',
            'b.basket_name',
            'cnt_snapshots' => 'COUNT(bs.basket_uuid)',
        ])->joinLeft(
            ['bs' => 'director_basket_snapshot'],
            'bs.basket_uuid = b.uuid',
            []
        )->group('b.uuid')->order('b.basket_name');
    }
}

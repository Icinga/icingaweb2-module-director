<?php

namespace Icinga\Module\Director\Web\Table;

use dipl\Html\Link;
use dipl\Web\Table\ZfQueryBasedTable;
use Icinga\Date\DateFormatter;
use Icinga\Module\Director\Core\Json;
use Icinga\Module\Director\DirectorObject\Automation\Basket;
use RuntimeException;

class BasketSnapshotTable extends ZfQueryBasedTable
{
    protected $searchColumns = [
        'basket_name',
    ];

    /** @var Basket */
    protected $basket;

    public function setBasket(Basket $basket)
    {
        $this->basket = $basket;
        $this->searchColumns = [];

        return $this;
    }

    public function renderRow($row)
    {
        $hexUuid = bin2hex($row->uuid);
        $link = $this->linkToSnapshot($this->renderSummary($row->summary), $row);

        if ($this->basket === null) {
            $columns = [
                $link,
                new Link(
                    $row->basket_name,
                    'director/basket',
                    ['uuid' => $hexUuid]
                ),
                DateFormatter::formatDateTime($row->ts_create / 1000),
            ];
        } else {
            $columns = [
                $link,
                DateFormatter::formatDateTime($row->ts_create / 1000),
            ];
        }
        return $this::row($columns);
    }

    protected function renderSummary($summary)
    {
        $summary = Json::decode($summary);
        if ($summary === null) {
            return '-';
        }
        $result = [];
        if (! is_object($summary) && ! is_array($summary)) {
            throw new RuntimeException(sprintf(
                'Got invalid basket summary: %s ',
                var_export($summary, 1)
            ));
        }

        foreach ($summary as $type => $count) {
            $result[] = sprintf(
                '%dx %s',
                $count,
                $type
            );
        }

        return implode(', ', $result);
    }

    protected function linkToSnapshot($caption, $row)
    {
        return new Link($caption, 'director/basket/snapshot', [
            'checksum' => bin2hex($row->content_checksum),
            'ts'       => $row->ts_create,
            'uuid'     => bin2hex($row->uuid),
        ]);
    }

    public function getColumnsToBeRendered()
    {
        if ($this->basket === null) {
            return [
                $this->translate('Content'),
                $this->translate('Basket'),
                $this->translate('Created'),
            ];
        } else {
            return [
                $this->translate('Content'),
                $this->translate('Created'),
            ];
        }
    }

    public function prepareQuery()
    {
        $query = $this->db()->select()->from([
            'b' => 'director_basket'
        ], [
            'b.uuid',
            'b.basket_name',
            'bs.ts_create',
            'bs.content_checksum',
            'bc.summary',
        ])->join(
            ['bs' => 'director_basket_snapshot'],
            'bs.basket_uuid = b.uuid',
            []
        )->join(
            ['bc' => 'director_basket_content'],
            'bc.checksum = bs.content_checksum',
            []
        )->order('bs.ts_create DESC');

        if ($this->basket !== null) {
            $query->where('b.uuid = ?', $this->basket->get('uuid'));
        }

        return $query;
    }
}

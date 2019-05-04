<?php

namespace Icinga\Module\Director\Web\Table;

use dipl\Html\Html;
use dipl\Html\Link;
use dipl\Web\Table\ZfQueryBasedTable;
use Icinga\Date\DateFormatter;
use Icinga\Module\Director\Core\Json;
use Icinga\Module\Director\DirectorObject\Automation\Basket;
use RuntimeException;

class BasketSnapshotTable extends ZfQueryBasedTable
{
    use DbHelper;

    protected $searchColumns = [
        'basket_name',
        'summary'
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
        $this->splitByDay($row->ts_create_seconds);
        $link = $this->linkToSnapshot($this->renderSummary($row->summary), $row);

        if ($this->basket === null) {
            $columns = [
                [
                    new Link(
                        Html::tag('strong', $row->basket_name),
                        'director/basket',
                        ['name' => $row->basket_name]
                    ),
                    Html::tag('br'),
                    $link,
                ],
                DateFormatter::formatTime($row->ts_create / 1000),
            ];
        } else {
            $columns = [
                $link,
                DateFormatter::formatTime($row->ts_create / 1000),
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

        if (empty($result)) {
            return '-';
        }

        return implode(', ', $result);
    }

    protected function linkToSnapshot($caption, $row)
    {
        return new Link($caption, 'director/basket/snapshot', [
            'checksum' => bin2hex($this->wantBinaryValue($row->content_checksum)),
            'ts'       => $row->ts_create,
            'name'     => $row->basket_name,
        ]);
    }

    public function prepareQuery()
    {
        $query = $this->db()->select()->from([
            'b' => 'director_basket'
        ], [
            'b.uuid',
            'b.basket_name',
            'bs.ts_create',
            'ts_create_seconds' => '(bs.ts_create / 1000)',
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
            $query->where('b.uuid = ?', $this->quoteBinary($this->basket->get('uuid')));
        }

        return $query;
    }
}

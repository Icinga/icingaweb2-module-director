<?php

namespace Icinga\Module\Director\Web\Table;

use DateTime;
use gipfl\IcingaWeb2\Link;
use Icinga\Module\Director\Auth\Permission;
use Icinga\Module\Director\Util;
use IntlDateFormatter;
use ipl\Html\Html;
use ipl\Html\HtmlElement;

class ActivityLogTable extends IntlZfQueryBasedTable
{
    protected $filters = [];

    protected $lastDeployedId;

    protected $extraParams = [];

    protected $columnCount;

    protected $hasObjectFilter = false;

    protected $searchColumns = [
        'author',
        'object_name',
        'object_type',
    ];

    protected $ranges = [];

    /** @var ?object */
    protected $currentRange = null;
    /** @var ?HtmlElement */
    protected $currentRangeCell = null;
    /** @var int */
    protected $rangeRows = 0;
    protected $continueRange = false;
    protected $currentRow;

    public function __construct($db)
    {
        parent::__construct($db);
    }

    public function assemble()
    {
        $this->getAttributes()->add('class', 'activity-log');
    }

    public function setLastDeployedId($id)
    {
        $this->lastDeployedId = $id;
        return $this;
    }

    protected function fetchQueryRows()
    {
        $rows = parent::fetchQueryRows();
        // Hint -> DESC, that's why they are inverted
        if (empty($rows)) {
            return $rows;
        }
        $last = $rows[0]->id;
        $first = $rows[count($rows) - 1]->id;
        $db = $this->db();
        $this->ranges = $db->fetchAll(
            $db->select()
                ->from('director_activity_log_remark')
                ->where('first_related_activity <= ?', $last)
                ->where('last_related_activity >= ?', $first)
        );

        return $rows;
    }


    public function renderRow($row)
    {
        $this->currentRow = $row;
        $this->splitByDay($row->ts_change_time);
        $action = 'action-' . $row->action . ' ';
        if ($row->id > $this->lastDeployedId) {
            $action .= 'undeployed';
        } else {
            $action .= 'deployed';
        }

        $columns = [
            $this::td($this->makeLink($row))->setSeparator(' '),
        ];
        if (! $this->hasObjectFilter) {
            $columns[] = $this->makeRangeInfo($row->id);
        }


        $columns[] = $this::td($this->getTime($row->ts_change_time));

        return $this::tr($columns)->addAttributes(['class' => $action]);
    }

    /**
     * Hint: cloned from parent class and modified
     * @param  int $timestamp
     */
    protected function renderDayIfNew($timestamp)
    {
        $day = $this->getDateFormatter()->format((new DateTime())->setTimestamp($timestamp));

        if ($this->lastDay !== $day) {
            $this->nextHeader()->add(
                $this::th($day, [
                    'colspan' => $this->hasObjectFilter ? 2 : 3,
                    'class'   => 'table-header-day'
                ])
            );

            $this->lastDay = $day;
            if ($this->currentRangeCell) {
                if ($this->currentRange->first_related_activity <= $this->currentRow->id) {
                    $this->currentRangeCell->addAttributes(['class' => 'continuing']);
                    $this->continueRange = true;
                } else {
                    $this->continueRange = false;
                }
            }
            $this->currentRangeCell = null;
            $this->currentRange = null;
            $this->rangeRows = 0;
            $this->nextBody();
        }
    }

    protected function makeRangeInfo($id)
    {
        $range = $this->getRangeForId($id);
        if ($range === null) {
            if ($this->currentRangeCell) {
                $this->currentRangeCell->getAttributes()->remove('class', 'continuing');
            }
            $this->currentRange = null;
            $this->currentRangeCell = null;
            $this->rangeRows = 0;
            return $this::td();
        }

        if ($range === $this->currentRange) {
            $this->growCurrentRange();
            return null;
        }
        $this->startRange($range);

        return $this->currentRangeCell;
    }

    protected function startRange($range)
    {
        $this->currentRangeCell = $this::td($this->renderRangeComment($range), [
            'colspan' => $this->rangeRows = 1,
            'class' => 'comment-cell'
        ]);
        if ($this->continueRange) {
            $this->currentRangeCell->addAttributes(['class' => 'continued']);
            $this->continueRange = false;
        }
        $this->currentRange = $range;
    }

    protected function renderRangeComment($range)
    {
        // The only purpose of this container is to avoid hovered rows from influencing
        // the comments background color, as we're using the alpha channel to lighten it
        // This can be replaced once we get theme-safe colors for such messages
        return Html::tag('div', [
            'class' => 'range-comment-container',
        ], Link::create($this->continueRange ? '' : $range->remark, '#', null, [
            'title' => $range->remark,
            'class' => 'range-comment'
        ]));
    }

    protected function growCurrentRange()
    {
        $this->rangeRows++;
        $this->currentRangeCell->setAttribute('rowspan', $this->rangeRows);
    }

    protected function getRangeForId($id)
    {
        foreach ($this->ranges as $range) {
            if ($id >= $range->first_related_activity && $id <= $range->last_related_activity) {
                return $range;
            }
        }

        return null;
    }

    protected function makeLink($row)
    {
        $type = $row->object_type;
        $name = $row->object_name;
        if (substr($type, 0, 7) === 'icinga_') {
            $type = substr($type, 7);
        }

        if (Util::hasPermission(Permission::SHOW_CONFIG)) {
            // Later on replacing, service_set -> serviceset

            // multi column key :(
            if ($type === 'service' || $this->hasObjectFilter) {
                $object = "\"$name\"";
            } elseif ($type === 'scheduled_downtime') {
                $object = Link::create(
                    "\"$name\"",
                    'director/' . str_replace('_', '-', $type),
                    ['name' => $name],
                    ['title' => $this->translate('Jump to this object')]
                );
            } else {
                $object = Link::create(
                    "\"$name\"",
                    'director/' . str_replace('_', '', $type),
                    ['name' => $name],
                    ['title' => $this->translate('Jump to this object')]
                );
            }

            return [
                '[' . $row->author . ']',
                Link::create(
                    $row->action,
                    'director/config/activity',
                    array_merge(['id' => $row->id], $this->extraParams),
                    ['title' => $this->translate('Show details related to this change')]
                ),
                str_replace('_', ' ', $type),
                $object
            ];
        } else {
            return sprintf(
                '[%s] %s %s "%s"',
                $row->author,
                $row->action,
                $type,
                $name
            );
        }
    }

    public function filterObject($type, $name)
    {
        $this->hasObjectFilter = true;
        $this->filters[] = ['l.object_type = ?', $type];
        $this->filters[] = ['l.object_name = ?', $name];

        return $this;
    }

    public function filterHost($name)
    {
        $db = $this->db();
        $filter = '%"host":' . json_encode($name) . '%';
        $this->filters[] = ['('
            . $db->quoteInto('l.old_properties LIKE ?', $filter)
            . ' OR '
            . $db->quoteInto('l.new_properties LIKE ?', $filter)
            . ')', null];

        return $this;
    }

    public function getColumns()
    {
        return [
            'author'          => 'l.author',
            'action'          => 'l.action_name',
            'object_name'     => 'l.object_name',
            'object_type'     => 'l.object_type',
            'id'              => 'l.id',
            'change_time'     => 'l.change_time',
            'ts_change_time'  => 'UNIX_TIMESTAMP(l.change_time)',
        ];
    }

    public function prepareQuery()
    {
        $query = $this->db()->select()->from(
            ['l' => 'director_activity_log'],
            $this->getColumns()
        )->order('change_time DESC')->order('id DESC')->limit(100);

        foreach ($this->filters as $filter) {
            $query->where($filter[0], $filter[1]);
        }

        return $query;
    }
}

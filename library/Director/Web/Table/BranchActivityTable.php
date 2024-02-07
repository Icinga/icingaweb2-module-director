<?php

namespace Icinga\Module\Director\Web\Table;

use Icinga\Module\Director\Db;
use Icinga\Module\Director\Db\Branch\BranchActivity;
use Icinga\Module\Director\Util;
use gipfl\IcingaWeb2\Link;
use Ramsey\Uuid\UuidInterface;

class BranchActivityTable extends IntlZfQueryBasedTable
{
    protected $extraParams = [];

    /** @var UuidInterface */
    protected $branchUuid;

    /** @var ?UuidInterface */
    protected $objectUuid;

    public function __construct(UuidInterface $branchUuid, $db, UuidInterface $objectUuid = null)
    {
        $this->branchUuid = $branchUuid;
        $this->objectUuid = $objectUuid;
        parent::__construct($db);
    }

    public function assemble()
    {
        $this->getAttributes()->add('class', 'activity-log');
    }

    public function renderRow($row)
    {
        $ts = (int) floor($row->timestamp_ns / 1000000);
        $this->splitByDay($ts);
        $activity = BranchActivity::fromDbRow($row);
        return $this::tr([
            $this::td($this->makeBranchLink($activity))->setSeparator(' '),
            $this::td($this->getTime($ts))
        ])->addAttributes(['class' => ['action-' . $activity->getAction(), 'branched']]);
    }

    protected function linkObject(BranchActivity $activity)
    {
        // $type, UuidInterface $uuid
        // Later on replacing, service_set -> serviceset
        $type = preg_replace('/^icinga_/', '', $activity->getObjectTable());
        return Link::create(
            $activity->getObjectName(),
            'director/' . str_replace('_', '', $type),
            ['uuid' => $activity->getObjectUuid()->toString()],
            ['title' => $this->translate('Jump to this object')]
        );
    }

    protected function makeBranchLink(BranchActivity $activity)
    {
        $type = preg_replace('/^icinga_/', '', $activity->getObjectTable());

        if (Util::hasPermission('director/showconfig')) {
            // Later on replacing, service_set -> serviceset
            return [
                '[' . $activity->getAuthor() . ']',
                Link::create(
                    $activity->getAction(),
                    'director/branch/activity',
                    array_merge(['ts' => $activity->getTimestampNs()], $this->extraParams),
                    ['title' => $this->translate('Show details related to this change')]
                ),
                str_replace('_', ' ', $type),
                $this->linkObject($activity)
            ];
        } else {
            return sprintf(
                '[%s] %s %s "%s"',
                $activity->getAuthor(),
                $activity->getAction(),
                $type,
                'object name'
            );
        }
    }

    public function prepareQuery()
    {
        /** @var Db $connection */
        $connection = $this->connection();
        $query = $this->db()->select()->from(['ba' => 'director_branch_activity'], 'ba.*')
            ->join(['b' => 'director_branch'], 'b.uuid = ba.branch_uuid', ['b.owner'])
            ->where('branch_uuid = ?', $connection->quoteBinary($this->branchUuid->getBytes()))
            ->order('timestamp_ns DESC');
        if ($this->objectUuid) {
            $query->where('ba.object_uuid = ?', $connection->quoteBinary($this->objectUuid->getBytes()));
        }

        return $query;
    }
}

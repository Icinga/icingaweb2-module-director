<?php

namespace Icinga\Module\Director\Web\Table;

use Icinga\Module\Director\Data\Db\DbObject;
use Icinga\Module\Director\Db\Branch\ObjectModification;
use Icinga\Module\Director\Util;
use gipfl\IcingaWeb2\Link;
use gipfl\IcingaWeb2\Table\ZfQueryBasedTable;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

class BranchActivityTable extends ZfQueryBasedTable
{
    protected $extraParams = [];

    /** @var UuidInterface */
    protected $branchUuid;

    public function __construct(UuidInterface $branchUuid, $db)
    {
        $this->branchUuid = $branchUuid;
        parent::__construct($db);
    }

    public function assemble()
    {
        $this->getAttributes()->add('class', 'activity-log');
    }

    public function renderRow($row)
    {
        return $this->renderBranchRow($row);
    }

    public function renderBranchRow($row)
    {
        $ts = $row->change_time / 1000;
        $this->splitByDay($ts);
        $changes = ObjectModification::fromSerialization(json_decode($row->change_set));
        $action = 'action-' . $changes->getAction(). ' branched'; // not gray
        return $this::tr([
            $this::td($this->makeBranchLink(
                $changes,
                Uuid::fromBytes($row->uuid),
                Uuid::fromBytes($row->branch_uuid)
            ))->setSeparator(' '),
            $this::td(strftime('%H:%M:%S', $ts))
        ])->addAttributes(['class' => $action]);
    }

    protected function linkObject($type, $name)
    {
        // Later on replacing, service_set -> serviceset

        // multi column key :(
        if ($type === 'service') {
            return "\"$name\"";
        }

        return Link::create(
            "\"$name\"",
            'director/' . str_replace('_', '', $type),
            ['name' => $name],
            ['title' => $this->translate('Jump to this object')]
        );
    }

    protected function makeBranchLink(ObjectModification $modification, UuidInterface $uuid, UuidInterface  $branch)
    {
        /** @var string|DbObject $class */
        $class = $modification->getClassName();
        $type = $class::create([])->getShortTableName();
        // TODO: short type in table, not class name
        $keyParams = $modification->getKeyParams();
        if (is_object($keyParams)) {
            $keyParams = (array)$keyParams;
        }
        if (is_array($keyParams)) {
            if (array_keys($keyParams)  === ['object_name']) {
                $name = $keyParams['object_name'];
            } else {
                $name = json_encode($keyParams);
            }
        } else {
            $name = $keyParams;
        }
        $author = 'branch owner';

        if (Util::hasPermission('director/showconfig')) {
            // Later on replacing, service_set -> serviceset
            $id = 0; // $row->id

            return [
                '[' . $author . ']',
                Link::create(
                    $modification->getAction(),
                    'director/branch/activity',
                    array_merge(['uuid' => $uuid->toString()], $this->extraParams),
                    ['title' => $this->translate('Show details related to this change')]
                ),
                str_replace('_', ' ', $type),
                $this->linkObject($type, $name)
            ];
        } else {
            return sprintf(
                '[%s] %s %s "%s"',
                $author,
                $modification->getAction(),
                $type,
                $name
            );
        }
    }

    public function prepareQuery()
    {
        return  $this->db()->select()->from('director_branch_activity')
            ->where('branch_uuid = ?', $this->branchUuid->getBytes())
            ->order('change_time DESC');
    }
}

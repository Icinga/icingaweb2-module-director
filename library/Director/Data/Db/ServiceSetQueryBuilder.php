<?php

namespace Icinga\Module\Director\Data\Db;

use Icinga\Module\Director\Db;
use Icinga\Module\Director\Db\Branch\BranchSupport;
use Icinga\Module\Director\Db\DbSelectParenthesis;
use Icinga\Module\Director\Db\DbUtil;
use Icinga\Module\Director\Objects\IcingaService;
use Icinga\Module\Director\Objects\IcingaServiceSet;
use Icinga\Module\Director\Web\Table\TableWithBranchSupport;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

class ServiceSetQueryBuilder
{
    use TableWithBranchSupport;

    const TABLE = BranchSupport::TABLE_ICINGA_SERVICE;
    const BRANCHED_TABLE = BranchSupport::BRANCHED_TABLE_ICINGA_SERVICE;
    const SET_TABLE = BranchSupport::TABLE_ICINGA_SERVICE_SET;
    const BRANCHED_SET_TABLE = BranchSupport::BRANCHED_TABLE_ICINGA_SERVICE_SET;

    /** @var Db */
    protected $connection;

    /** @var \Zend_Db_Adapter_Abstract */
    protected $db;

    protected $searchColumns = [];

    /**
     * @param ?UuidInterface $uuid
     */
    public function __construct(Db $connection, $uuid = null)
    {
        $this->connection = $connection;
        $this->db = $connection->getDbAdapter();
        if ($uuid) {
            $this->setBranchUuid($uuid);
        }
    }

    /**
     * @return \Zend_Db_Select
     * @throws \Zend_Db_Select_Exception
     */
    public function selectServicesForSet(IcingaServiceSet $set)
    {
        $db = $this->connection->getDbAdapter();
        if ($this->branchUuid) {
            $right = $this->selectRightBranchedServices($set)->columns($this->getRightBranchedColumns());
            $left = $this->selectLeftBranchedServices($set)->columns($this->getLeftBranchedColumns());
            $query = $this->db->select()->from(['u' => $db->select()->union([
                'l' => new DbSelectParenthesis($left),
                'r' => new DbSelectParenthesis($right),
            ])]);
            $query->order('service_set');
        } else {
            $query = $this->selectServices($set)->columns($this->getColumns());
        }

        return $query;
    }

    protected function selectServices(IcingaServiceSet $set)
    {
        return $this->db
            ->select()
            ->from(['o' =>self::TABLE], [])
            ->joinLeft(['os' => self::SET_TABLE], 'os.id = o.service_set_id', [])
            ->where('os.uuid = ?', $this->connection->quoteBinary($set->getUniqueId()->getBytes()));
    }

    protected function selectLeftBranchedServices(IcingaServiceSet $set)
    {
        return $this
            ->selectServices($set)
            ->joinLeft(
                ['bo' => self::BRANCHED_TABLE],
                $this->db->quoteInto('bo.uuid = o.uuid AND bo.branch_uuid = ?', $this->getQuotedBranchUuid()),
                []
            );
    }

    protected function selectRightBranchedServices(IcingaServiceSet $set)
    {
        return $this->db
            ->select()
            ->from(['o' => self::TABLE], [])
            ->joinRight(['bo' => self::BRANCHED_TABLE], 'bo.uuid = o.uuid', [])
            ->where('bo.service_set = ?', $set->get('object_name'))
            ->where('bo.branch_uuid = ?', $this->getQuotedBranchUuid());
    }

    protected static function resetQueryProperties(\Zend_Db_Select $query)
    {
        // TODO: Keep existing UUID, becomes important when using this for other tables too (w/o UNION)
        // $columns = $query->getPart($query::COLUMNS);
        $query->reset($query::COLUMNS);
        $query->columns('uuid');
        return $query;
    }

    public function fetchServicesWithQuery(\Zend_Db_Select $query)
    {
        static::resetQueryProperties($query);
        $db = $this->connection->getDbAdapter();
        $uuids = $db->fetchCol($query);

        $services = [];
        foreach ($uuids as $uuid) {
            $service = IcingaService::loadWithUniqueId(Uuid::fromBytes(DbUtil::binaryResult($uuid)), $this->connection);
            $service->set('service_set', null); // TODO: CHECK THIS!!!!

            $services[$service->getObjectName()] = $service;
        }

        return $services;
    }

    protected function getColumns()
    {
        return [
            'uuid'           => 'o.uuid', // MUST be first because of UNION column order, see branchifyColumns()
            'id'             => 'o.id',
            'branch_uuid'    => '(null)',
            'service_set'    => 'os.object_name',
            'service'        => 'o.object_name',
            'disabled'       => 'o.disabled',
            'object_type'    => 'o.object_type',
            'blacklisted'    => "('n')",
        ];
    }

    protected function getLeftBranchedColumns()
    {
        $columns = $this->getColumns();
        $columns['branch_uuid'] = 'bo.branch_uuid';
        $columns['service_set'] = 'COALESCE(os.object_name, bo.service_set)';

        return $this->branchifyColumns($columns);
    }

    protected function getRightBranchedColumns()
    {
        $columns = $this->getColumns();
        $columns = $this->branchifyColumns($columns);
        $columns['branch_uuid'] = 'bo.branch_uuid';
        $columns['service_set'] = 'bo.service_set';
        $columns['id'] = '(NULL)';

        return $columns;
    }

    protected function getQuotedBranchUuid()
    {
        return $this->connection->quoteBinary($this->branchUuid->getBytes());
    }
}

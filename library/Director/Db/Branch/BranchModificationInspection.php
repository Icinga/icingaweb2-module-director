<?php

namespace Icinga\Module\Director\Db\Branch;

use gipfl\Translation\StaticTranslator;
use Icinga\Module\Director\Db;
use ipl\Html\Html;
use ipl\Html\HtmlDocument;
use ipl\I18n\Translation;
use Ramsey\Uuid\UuidInterface;

class BranchModificationInspection
{
    use Translation;

    protected $connection;

    protected $db;

    public function __construct(Db $connection)
    {
        $this->connection = $connection;
        $this->db = $connection->getDbAdapter();
    }

    public function describe($table, UuidInterface $uuid)
    {
        return static::describeModificationStatistics($this->loadSingleTableStats($table, $uuid));
    }

    public function describeBranch(UuidInterface $uuid)
    {
        $tables = [
            $this->translate('API Users')            => BranchSupport::BRANCHED_TABLE_ICINGA_APIUSER,
            $this->translate('Endpoints')            => BranchSupport::BRANCHED_TABLE_ICINGA_COMMAND,
            $this->translate('Zones')                => BranchSupport::BRANCHED_TABLE_ICINGA_DEPENDENCY,
            $this->translate('Commands')             => BranchSupport::BRANCHED_TABLE_ICINGA_ENDPOINT,
            $this->translate('Hosts')                => BranchSupport::BRANCHED_TABLE_ICINGA_HOST,
            $this->translate('Hostgroups')           => BranchSupport::BRANCHED_TABLE_ICINGA_HOSTGROUP,
            $this->translate('Services')             => BranchSupport::BRANCHED_TABLE_ICINGA_NOTIFICATION,
            $this->translate('Servicegroups')        => BranchSupport::BRANCHED_TABLE_ICINGA_SCHEDULED_DOWNTIME,
            $this->translate('Servicesets')          => BranchSupport::BRANCHED_TABLE_ICINGA_SERVICE_SET,
            $this->translate('Users')                => BranchSupport::BRANCHED_TABLE_ICINGA_SERVICE,
            $this->translate('Usergroups')           => BranchSupport::BRANCHED_TABLE_ICINGA_SERVICEGROUP,
            $this->translate('Timeperiods')          => BranchSupport::BRANCHED_TABLE_ICINGA_TIMEPERIOD,
            $this->translate('Notifications')        => BranchSupport::BRANCHED_TABLE_ICINGA_USER,
            $this->translate('Dependencies')         => BranchSupport::BRANCHED_TABLE_ICINGA_USERGROUP,
            $this->translate('Scheduled Downtimes')  => BranchSupport::BRANCHED_TABLE_ICINGA_ZONE,
        ];

        $parts = new HtmlDocument();
        $parts->setSeparator(Html::tag('br'));
        foreach ($tables as $label => $table) {
            $info = $this->describe($table, $uuid);
            if (! empty($info) && $info !== '-') {
                $parts->add("$label: $info");
            }
        }

        return $parts;
    }

    public static function describeModificationStatistics($stats)
    {
        $t = StaticTranslator::get();
        $relevantStats = [];
        if ($stats->cnt_created > 0) {
            $relevantStats[] = sprintf($t->translate('%d created'), $stats->cnt_created);
        }
        if ($stats->cnt_deleted > 0) {
            $relevantStats[] = sprintf($t->translate('%d deleted'), $stats->cnt_deleted);
        }
        if ($stats->cnt_modified > 0) {
            $relevantStats[] = sprintf($t->translate('%d modified'), $stats->cnt_modified);
        }
        if (empty($relevantStats)) {
            return '-';
        }

        return implode(', ', $relevantStats);
    }

    public function loadSingleTableStats($table, UuidInterface $uuid)
    {
        $query = $this->db->select()->from($table, [
            'cnt_created'  => "SUM(CASE WHEN branch_created = 'y' THEN 1 ELSE 0 END)",
            'cnt_deleted'  => "SUM(CASE WHEN branch_deleted = 'y' THEN 1 ELSE 0 END)",
            'cnt_modified' => "SUM(CASE WHEN branch_deleted = 'n' AND branch_created = 'n' THEN 1 ELSE 0 END)",
        ])->where('branch_uuid = ?', $this->connection->quoteBinary($uuid->getBytes()));

        return $this->db->fetchRow($query);
    }
}

<?php

namespace Icinga\Module\Director\Db\Branch;

use gipfl\Translation\TranslationHelper;
use Icinga\Module\Director\Db;
use ipl\Html\Html;
use ipl\Html\HtmlDocument;
use Ramsey\Uuid\UuidInterface;

class BranchModificationInspection
{
    use TranslationHelper;

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
            $this->translate('API Users')     => 'branched_icinga_apiuser',
            $this->translate('Endpoints')     => 'branched_icinga_endpoint',
            $this->translate('Zones')         => 'branched_icinga_zone',
            $this->translate('Commands')      => 'branched_icinga_command',
            $this->translate('Hosts')         => 'branched_icinga_host',
            $this->translate('Hostgroups')    => 'branched_icinga_hostgroup',
            $this->translate('Services')      => 'branched_icinga_service',
            $this->translate('Servicegroups') => 'branched_icinga_servicegroup',
            $this->translate('Users')         => 'branched_icinga_user',
            $this->translate('Timeperiods')  => 'branched_icinga_timeperiod',
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
        $t = TranslationHelper::getTranslator();
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

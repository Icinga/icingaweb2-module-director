<?php

namespace Icinga\Module\Director\Resolver;

use gipfl\IcingaWeb2\Link;
use ipl\I18n\Translation;
use Icinga\Module\Director\Objects\IcingaCommand;

class CommandUsage
{
    use Translation;

    /** @var IcingaCommand */
    protected $command;

    /** @var \Zend_Db_Adapter_Abstract */
    protected $db;

    /**
     * CommandUsageTable constructor.
     * @param IcingaCommand $command
     */
    public function __construct(IcingaCommand $command)
    {
        $this->command = $command;
        $this->db = $command->getDb();
    }

    /**
     * @return array
     */
    public function getLinks()
    {
        $name = $this->command->getObjectName();
        $links = [];
        $map = [
            'host'         => ['check_command', 'event_command'],
            'service'      => ['check_command', 'event_command'],
            'notification' => ['command'],
            'command'      => [],
        ];
        $types = [
            'host' => [
                'object'   => $this->translate('%d Host(s)'),
                'template' => $this->translate('%d Host Template(s)'),
            ],
            'service' => [
                'object'   => $this->translate('%d Service(s)'),
                'template' => $this->translate('%d Service Template(s)'),
                'apply'     => $this->translate('%d Service Apply Rule(s)'),
            ],
            'notification' => [
                'object'   => $this->translate('%d Notification(s)'),
                'template' => $this->translate('%d Notification Template(s)'),
                'apply'     => $this->translate('%d Notification Apply Rule(s)'),
            ],
            'command' => [
                'object'   => $this->translate('%d Command(s)'),
                'template' => $this->translate('%d Command Template(s)'),
            ],
        ];

        $urlSuffix = [
            'object'   => '',
            'template' => '/templates',
            'apply'    => '/applyrules',
        ];

        foreach ($map as $type => $relations) {
            $res = $this->fetchFor($type, $relations, array_keys($types[$type]));
            foreach ($types[$type] as $objectType => $caption) {
                if ($res->$objectType > 0) {
                    $suffix = $urlSuffix[$objectType];
                    $links[] = Link::create(
                        sprintf($caption, $res->$objectType),
                        "director/{$type}s$suffix",
                        ['command' => $name]
                    );
                }
            }
        }

        return $links;
    }

    protected function fetchFor($table, $rels, $objectTypes)
    {
        $id = $this->command->getAutoincId();

        $columns = [];
        foreach ($objectTypes as $type) {
            $columns[$type] = "COALESCE(SUM(CASE WHEN object_type = '$type' THEN 1 ELSE 0 END), 0)";
        }

        $query = $this->db->select()->from(['c' => "icinga_$table"], $columns);

        if ($table === 'command') {
            $query->join(
                ['ici' => 'icinga_command_inheritance'],
                'ici.command_id = c.id',
                []
            )->where('ici.parent_command_id = ?', $id);
        }

        foreach ($rels as $rel) {
            $query->orWhere("{$rel}_id = ?", $id);
        }

        return $this->db->fetchRow($query);
    }
}

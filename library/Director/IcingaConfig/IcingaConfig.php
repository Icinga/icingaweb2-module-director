<?php

namespace Icinga\Module\Director\IcingaConfig;

use Icinga\Data\Db\DbConnection;
use Icinga\Module\Director\Objects\IcingaCommand;
use Icinga\Module\Director\Objects\IcingaHost;

class IcingaConfig
{
    protected $files = array();

    protected function __construct()
    {
    }

    public function getFiles()
    {
        return $this->files;
    }

    public static function fromDb(DbConnection $db)
    {
        $config = new static;
        return $config->loadFromDb($db);
    }

    protected function loadFromDb(DbConnection $db)
    {
        $this->db = $db;
        $this
            ->createFileFromDb('zone')
            ->createFileFromDb('command')
            ->createFileFromDb('host')
            ->createFileFromDb('service')
            ->createFileFromDb('user')
            ;
        return $this;
    }

    protected function createFileFromDb($type)
    {
        $class = 'Icinga\\Module\\Director\\Objects\\Icinga' . ucfirst($type);
        $objects = $class::loadAll($this->db);

        if (! empty($objects)) {
            $class = 'Icinga\\Module\\Director\\IcingaConfig\\Icinga'
                   . ucfirst($type)
                   . 'sConfigFile';
            $file = new $class();
            $file->addObjects($objects);
            $this->files[$type . 's.conf'] = $file;
        }

        return $this;
    }
}

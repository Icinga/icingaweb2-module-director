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

    public static function generate(DbConnection $db)
    {
        $config = new static;
        return $config->loadFromDb($db);
    }

    protected function loadFromDb(DbConnection $db)
    {
        $this->db = $db;
        $this
            ->createFileFromDb('zone')
            ->createFileFromDb('endpoint')
            ->createFileFromDb('command')
            ->createFileFromDb('hostGroup')
            ->createFileFromDb('host')
            ->createFileFromDb('serviceGroup')
            ->createFileFromDb('service')
            ->createFileFromDb('userGroup')
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
            $file = new IcingaConfigFile();
            if ($type === 'command') {
                $file->prepend("library \"methods\"\n\n");
            }
            $file->addObjects($objects);
            $this->files[strtolower($type) . 's.conf'] = $file;
        }

        return $this;
    }
}

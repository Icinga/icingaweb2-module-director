<?php

namespace Icinga\Module\Director\IcingaConfig;

use Icinga\Application\Benchmark;
use Icinga\Application\Hook;
use Icinga\Application\Icinga;
use Icinga\Exception\IcingaException;
use Icinga\Exception\ProgrammingError;
use Icinga\Module\Director\Db;
use Icinga\Module\Director\Util;
use Icinga\Module\Director\Objects\IcingaHost;
use Icinga\Module\Director\Objects\IcingaZone;
use Icinga\Module\Director\Objects\IcingaEndpoint;
use Exception;

class IcingaConfig
{
    protected $files = array();

    protected $checksum;

    protected $zoneMap = array();

    protected $lastActivityChecksum;

    /**
     * @var \Zend_Db_Adapter_Abstract
     */
    protected $db;

    protected $connection;

    protected $generationTime;

    public static $table = 'director_generated_config';

    public function __construct(Db $connection)
    {
        // Make sure module hooks are loaded:
        Icinga::app()->getModuleManager()->loadEnabledModules();

        $this->connection = $connection;
        $this->db = $connection->getDbAdapter();
    }

    public function getSize()
    {
        $size = 0;
        foreach ($this->getFiles() as $file) {
            $size += $file->getSize();
        }
        return $size;
    }

    public function getDuration()
    {
        return $this->duration;
    }

    public function getFileCount()
    {
        return count($this->files);
    }

    public function getObjectCount()
    {
        $cnt = 0;
        foreach ($this->getFiles() as $file) {
            $cnt += $file->getObjectCount();
        }
        return $cnt;
    }

    public function getTemplateCount()
    {
        $cnt = 0;
        foreach ($this->getFiles() as $file) {
            $cnt += $file->getTemplateCount();
        }
        return $cnt;
    }

    public function getChecksum()
    {
        return $this->checksum;
    }

    public function getHexChecksum()
    {
        return Util::binary2hex($this->checksum);
    }

    public function getFiles()
    {
        return $this->files;
    }

    public function getFileContents()
    {
        $result = array();
        foreach ($this->files as $name => $file) {
            $result[$name] = $file->getContent();
        }

        return $result;
    }

    public function getFileNames()
    {
        return array_keys($this->files);
    }

    public function getFile($name)
    {
        return $this->files[$name];
    }

    public function getMissingFiles($missing)
    {
        $files = array();
        foreach ($this->files as $name => $file) {
            $files[] = $name . '=' . $file->getChecksum();
        }
        return $files;
    }

    public static function load($checksum, Db $connection)
    {
        $config = new static($connection);
        $config->loadFromDb($checksum);
        return $config;
    }

    public static function generate(Db $connection)
    {
        $config = new static($connection);
        return $config->storeIfModified();
    }

    public static function wouldChange(Db $connection)
    {
        $config = new static($connection);
        return $config->hasBeenModified();
    }

    public function hasBeenModified()
    {
        $this->generateFromDb();
        $this->collectExtraFiles();
        $checksum = $this->calculateChecksum();
        $activity = $this->getLastActivityChecksum();

        $lastActivity = $this->binFromDb(
            $this->db->fetchOne(
                $this->db->select()->from(
                    self::$table,
                    'last_activity_checksum'
                )->where(
                    'checksum = ?',
                    $this->dbBin($checksum)
                )
            )
        );

        if ($lastActivity === false || $lastActivity === null) {
            return true;
        }

        if ($lastActivity !== $activity) {
            $this->db->update(
                self::$table,
                array(
                    'last_activity_checksum' => $this->dbBin($activity)
                ),
                $this->db->quoteInto('checksum = ?', $this->dbBin($checksum))
            );
        }

        return false;
    }

    protected function storeIfModified()
    {
        if ($this->hasBeenModified()) {
            $this->store();
        }

        return $this;
    }

    protected function dbBin($binary)
    {
        if ($this->connection->isPgsql()) {
            return Util::pgBinEscape($binary);
        } else {
            return $binary;
        }
    }

    protected function binFromDb($value)
    {
        if (is_resource($value)) {
            return stream_get_contents($value);
        }

        return $value;
    }

    protected function calculateChecksum()
    {
        $files = array();
        $sortedFiles = $this->files;
        ksort($sortedFiles);
        /** @var IcingaConfigFile $file */
        foreach ($sortedFiles as $name => $file) {
            $files[] = $name . '=' . $file->getHexChecksum();
        }

        $this->checksum = sha1(implode(';', $files), true);
        return $this->checksum;
    }

    public function getFilesChecksums()
    {
        $checksums = array();

        /** @var IcingaConfigFile $file */
        foreach ($this->files as $name => $file) {
            $checksums[] = $file->getChecksum();
        }

        return $checksums;
    }

    protected function getZoneName($id)
    {
        return $this->zoneMap[$id];
    }

    public function store()
    {

        $fileTable = IcingaConfigFile::$table;
        $fileKey = IcingaConfigFile::$keyName;

        $this->db->beginTransaction();
        try {
            $existingQuery = $this->db->select()
                ->from($fileTable, 'checksum')
                ->where('checksum IN (?)', array_map(array($this, 'dbBin'), $this->getFilesChecksums()));

            $existing = $this->db->fetchCol($existingQuery);

            foreach ($existing as $key => $val) {
                if (is_resource($val)) {
                    $existing[$key] = stream_get_contents($val);
                }
            }

            $missing = array_diff($this->getFilesChecksums(), $existing);
            $stored = array();

            /** @var IcingaConfigFile $file */
            foreach ($this->files as $name => $file) {
                $checksum = $file->getChecksum();
                if (! in_array($checksum, $missing)) {
                    continue;
                }

                if (array_key_exists($checksum, $stored)) {
                    continue;
                }

                $stored[$checksum] = true;

                $this->db->insert(
                    $fileTable,
                    array(
                        $fileKey       => $this->dbBin($checksum),
                        'content'      => $file->getContent(),
                        'cnt_object'   => $file->getObjectCount(),
                        'cnt_template' => $file->getTemplateCount()
                    )
                );
            }

            $activity = $this->dbBin($this->getLastActivityChecksum());
            $this->db->insert(
                self::$table,
                array(
                    'duration'                => $this->generationTime,
                    'first_activity_checksum' => $activity,
                    'last_activity_checksum'  => $activity,
                    'checksum'                => $this->dbBin($this->getChecksum()),
                )
            );
            /** @var IcingaConfigFile $file */
            foreach ($this->files as $name => $file) {
                $this->db->insert(
                    'director_generated_config_file',
                    array(
                        'config_checksum' => $this->dbBin($this->getChecksum()),
                        'file_checksum'   => $this->dbBin($file->getChecksum()),
                        'file_path'       => $name,
                    )
                );
            }

            $this->db->commit();
        } catch (Exception $e) {
            $this->db->rollBack();
            throw $e;
        }

        return $this;
    }

    protected function generateFromDb()
    {
        $start = microtime(true);

        // Raise limits. TODO: do this in a failsafe way, and only if necessary
        ini_set('memory_limit', '768M');
        ini_set('max_execution_time', 0);

        if (! $this->connection->isPgsql() && $this->db->quote("1\0") !== '\'1\\0\'') {

            throw new IcingaException(
                'Refusing to render the configuration, your DB layer corrupts binary data.'
                . ' You might be affected by Zend Framework bug #655'
            );
        }

        $this->configFile(
            sprintf(
                'zones.d/%s/001-director-basics',
                $this->connection->getDefaultGlobalZoneName()
            )
        )->prepend(
            "\nconst DirectorStageDir = dirname(dirname(current_filename))\n"
        );

        $this
            ->createFileFromDb('zone')
            ->createFileFromDb('endpoint')
            ->createFileFromDb('command')
            ->createFileFromDb('timePeriod')
            ->createFileFromDb('hostGroup')
            ->createFileFromDb('host')
            ->autogenerateAgents()
            ->createFileFromDb('serviceGroup')
            ->createFileFromDb('service')
            ->createFileFromDb('userGroup')
            ->createFileFromDb('user')
            ;

        $this->generationTime = (int) ((microtime(true) - $start) * 1000);

        return $this;
    }

    protected function loadFromDb($checksum)
    {
        $query = $this->db->select()->from(
            self::$table,
            array('checksum', 'last_activity_checksum', 'duration')
        )->where('checksum = ?', $this->dbBin($checksum));
        $result = $this->db->fetchRow($query);

        if (empty($result)) {
            throw new Exception(sprintf('Got no config for %s', Util::binary2hex($checksum)));
        }

        $this->checksum = $this->binFromDb($result->checksum);
        $this->duration = $result->duration;
        $this->lastActivityChecksum = $this->binFromDb($result->last_activity_checksum);

        $query = $this->db->select()->from(
            array('cf' => 'director_generated_config_file'),
            array(
                'file_path'    => 'cf.file_path',
                'checksum'     => 'f.checksum',
                'content'      => 'f.content',
                'cnt_object'   => 'f.cnt_object',
                'cnt_template' => 'f.cnt_template',
            )
        )->join(
            array('f' => 'director_generated_file'),
            'cf.file_checksum = f.checksum',
            array()
        )->where('cf.config_checksum = ?', $this->dbBin($checksum));

        foreach ($this->db->fetchAll($query) as $row) {
            $file = new IcingaConfigFile();
            $this->files[$row->file_path] = $file
                ->setContent($row->content)
                ->setObjectCount($row->cnt_object)
                ->setTemplateCount($row->cnt_template);
        }

        return $this;
    }

    protected function autogenerateAgents()
    {
        $zones = array();
        $endpoints = array();
        foreach (IcingaHost::prefetchAll($this->connection) as $host) {
            if ($host->object_type !== 'object') {
                continue;
            }

            if ($host->getResolvedProperty('has_agent') !== 'y') {
                continue;
            }

            $name = $host->object_name;
            if (IcingaEndpoint::exists($name, $this->connection)) {
                continue;
            }

            $props = array(
                'object_name'  => $name,
                'object_type'  => 'object',
                'log_duration' => 0
            );
            if ($host->getResolvedProperty('master_should_connect') === 'y') {
                $props['host'] = $host->getResolvedProperty('address');
                $props['zone_id'] = $host->getResolvedProperty('zone_id');
            }

            $endpoints[] = IcingaEndpoint::create($props);
            $zones[] = IcingaZone::create(array(
                'object_name' => $name,
                'parent'      => $this->connection->getMasterZoneName()
            ), $this->connection)->setEndpointList(array($name));
        }

        $this->createFileForObjects('endpoint', $endpoints);
        $this->createFileForObjects('zone', $zones);
        return $this;
    }

    protected function createFileFromDb($type)
    {
        $class = 'Icinga\\Module\\Director\\Objects\\Icinga' . ucfirst($type);
        Benchmark::measure(sprintf('Prefetching %s', $type));
        $objects = $class::prefetchAll($this->connection);
        return $this->createFileForObjects($type, $objects);
    }

    protected function createFileForObjects($type, $objects)
    {
        Benchmark::measure(sprintf('Generating %ss: %s', $type, count($objects)));
        if (empty($objects)) {
            return $this;
        }

        $masterZone = $this->connection->getMasterZoneName();
        $globalZone = $this->connection->getDefaultGlobalZoneName();
        $file = null;

        foreach ($objects as $object) {
            if ($object->disabled === 'y') {
                continue;
            }

            if ($object->isExternal()) {
                if ($type === 'zone') {
                    $this->zoneMap[$object->id] = $object->object_name;
                }

                continue;
            } elseif ($object->isTemplate()) {
                $filename = strtolower($type) . '_templates';
            } else {
                $filename = strtolower($type) . 's';
            }

            // Zones get special handling
            if ($type === 'zone') {
                $this->zoneMap[$object->id] = $object->object_name;
                // If the zone has a parent zone...
                if ($object->parent_id) {
                    // ...we render the zone object to the parent zone
                    $zone = $object->parent;
                } elseif ($object->is_global === 'y') {
                    // ...additional global zones are rendered to our global zone...
                    $zone = $globalZone;
                } else {
                    // ...and all the other zones are rendered to our master zone
                    $zone = $masterZone;
                }
            // Zone definitions for all other objects are respected...
            } elseif ($object->hasProperty('zone_id') && ($zone_id = $object->zone_id)) {
                $zone = $this->getZoneName($zone_id);
            // ...and if there is no zone defined, special rules take place
            } else {
                if ($this->typeWantsMasterZone($type)) {
                    $zone = $masterZone;
                } elseif ($this->typeWantsGlobalZone($type)) {
                    $zone = $globalZone;
                } else {
                    throw new ProgrammingError(
                        'I have no idea of how to deploy a "%s" object',
                        $type
                    );
                }
            }

            $filename = 'zones.d/' . $zone . '/' . $filename;
            $file = $this->configFile($filename);
            $file->addObject($object);
        }
        if ($file && $type === 'command') {
            $file->prepend("library \"methods\"\n\n");
        }
        Benchmark::measure(sprintf('%ss done', $type, count($objects)));
        return $this;
    }

    protected function typeWantsGlobalZone($type)
    {
        $types = array(
            'command',
        );

        return in_array($type, $types);
    }

    protected function typeWantsMasterZone($type)
    {
        $types = array(
            'host',
            'hostGroup',
            'service',
            'serviceGroup',
            'endpoint',
            'user',
            'userGroup',
            'timePeriod',
            'notification'
        );

        return in_array($type, $types);
    }

    protected function configFile($name, $suffix = '.conf')
    {
        $filename = $name . $suffix;
        if (! array_key_exists($filename, $this->files)) {
            $this->files[$filename] = new IcingaConfigFile();
        }

        return $this->files[$filename];
    }

    protected function collectExtraFiles()
    {
        foreach (Hook::all('Director\\ShipConfigFiles') as $hook) {
            foreach ($hook->fetchFiles() as $filename => $file) {
                if (array_key_exists($filename, $this->files)) {
                    throw new ProgrammingError(
                        'Cannot ship one file twice: %s',
                        $filename
                    );
                }
                if ($file instanceof IcingaConfigFile) {
                    $this->files[$filename] = $file;
                } else {
                    $this->configFile($filename, '')->setContent((string) $file);
                }
            }
        }

        return $this;
    }

    public function getLastActivityHexChecksum()
    {
        return Util::binary2hex($this->getLastActivityChecksum());
    }

    /**
     * @return mixed
     */
    public function getLastActivityChecksum()
    {
        if ($this->lastActivityChecksum === null) {
            $query = $this->db->select()
                ->from('director_activity_log', 'checksum')
                ->order('id DESC')
                ->limit(1);

            $this->lastActivityChecksum = $this->db->fetchOne($query);

            // PgSQL workaround:
            if (is_resource($this->lastActivityChecksum)) {
                $this->lastActivityChecksum = stream_get_contents($this->lastActivityChecksum);
            }
        }

        return $this->lastActivityChecksum;
    }
}

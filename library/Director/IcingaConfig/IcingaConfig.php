<?php

namespace Icinga\Module\Director\IcingaConfig;

use Icinga\Exception\ProgrammingError;
use Icinga\Module\Director\Db;
use Icinga\Module\Director\Util;
use Icinga\Module\Director\Objects\IcingaCommand;
use Icinga\Module\Director\Objects\IcingaHost;
use Icinga\Module\Director\Objects\IcingaEndpoint;
use Icinga\Web\Hook;
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

    protected function __construct(Db $connection)
    {
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

    protected function hasBeenModified()
    {
        $this->generateFromDb();
        $this->collectExtraFiles();
        $checksum = $this->calculateChecksum();
        $exists = $this->db->fetchOne(
            $this->db->select()->from(
                self::$table,
                'COUNT(*)'
            )->where(
                'checksum = ?',
                $this->dbBin($checksum)
            )
        );

        return (int) $exists === 0;
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
        if ($this->connection->getDbType() === 'pgsql') {
            return Util::pgBinEscape($binary);
        } else {
            return $binary;
        }
    }

    protected function calculateChecksum()
    {
        $files = array($this->getLastActivityHexChecksum());
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

    protected function store()
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

            /** @var IcingaConfigFile $file */
            foreach ($this->files as $name => $file) {
                $checksum = $file->getChecksum();
                if (! in_array($checksum, $missing)) {
                    continue;
                }

                $this->db->insert(
                    $fileTable,
                    array(
                        $fileKey  => $this->dbBin($checksum),
                        'content' => $file->getContent()
                    )
                );
            }

            $this->db->insert(
                self::$table,
                array(
                    'duration'               => $this->generationTime,
                    'last_activity_checksum' => $this->dbBin($this->getLastActivityChecksum()),
                    'checksum'               => $this->dbBin($this->getChecksum()),
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
            var_dump($e->getMessage());
        }

        return $this;
    }

    protected function generateFromDb()
    {
        $start = microtime(true);

        $this->configFile('conf.d/001-director-basics')->prepend(
            "\nconst DirectorStageDir = dirname(dirname(current_filename))\n"
          . "\nobject Zone \"director-global\" {\n    global = true\n}\n"

        );

        // TODO: combine this with real fetches, this is a test right now
        IcingaEndpoint::prefetchAll($this->connection);

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

        $this->checksum = $result->checksum;
        $this->duration = $result->duration;
        $this->lastActivityChecksum = $result->last_activity_checksum;

        if (is_resource($this->checksum)) {
            $this->checksum = stream_get_contents($this->checksum);
        }

        if (is_resource($this->lastActivityChecksum)) {
            $this->lastActivityChecksum = stream_get_contents($this->lastActivityChecksum);
        }

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

    protected function createFileFromDb($type)
    {
        $class = 'Icinga\\Module\\Director\\Objects\\Icinga' . ucfirst($type);
        $objects = $class::loadAll($this->connection);
        if (empty($objects)) return $this;
        $ourGlobalZone = 'director-global';
        $file = null;

        foreach ($objects as $object) {
            if ($object->isExternal()) {
                if ($type === 'zone') {
                    $this->zoneMap[$object->id] = $object->object_name;
                }
                continue;
            } elseif ($object->isTemplate()) {
                $filename = strtolower($type) . '_templates';
                $zone = $ourGlobalZone;
            } else {
                $filename = strtolower($type) . 's';
                $zone = $ourGlobalZone;
            }

            if ($type === 'zone') {
                $this->zoneMap[$object->id] = $object->object_name;
            } elseif ($object->hasProperty('zone_id') && ($zone_id = $object->zone_id)) {
                $zone = $this->getZoneName($zone_id);
            } else {
                $zone = $ourGlobalZone;
            }

            if (in_array($type, array('command', 'zone'))) {
                $filename = 'zones.d/' . $ourGlobalZone . '/' . $filename;
            } else {
                $filename = 'zones.d/' . $zone . '/' . $filename;
            }
            $file = $this->configFile($filename);
            $file->addObject($object);
        }
        if ($file && $type === 'command') {
            $file->prepend("library \"methods\"\n\n");
        }

        return $this;
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
                ->order('change_time DESC')
                ->limit(1);

            $this->lastActivityChecksum = $this->db->fetchOne($query);

            // PgSQL workaround:
            if (is_resource($this->lastActivityChecksum)) {
                $this->lastActivityChecksum = stream_get_contents($this->lastActivityChecksum);
            }
        }

        return $this->lastActivityChecksum;
    }

    // TODO: wipe unused files
    // DELETE f FROM director_generated_file f left join director_generated_config_file cf ON f.checksum = cf.file_checksum WHERE cf.file_checksum IS NULL;

}

<?php

namespace Icinga\Module\Director\IcingaConfig;

use Icinga\Data\Db\DbConnection;
use Icinga\Module\Director\Objects\IcingaCommand;
use Icinga\Module\Director\Objects\IcingaHost;

class IcingaConfig
{
    protected $files = array();

    protected $checksum;

    protected $lastActivityChecksum;

    /**
     * @var \Zend_Db_Adapter_Abstract
     */
    protected $db;

    protected $connection;

    protected $generationTime;

    public static $table = 'director_generated_config';

    protected function __construct(DbConnection $connection)
    {
        $this->connection = $connection;
        $this->db = $connection->getDbAdapter();
    }

    public function getChecksum()
    {
        return $this->checksum;
    }

    public function getHexChecksum()
    {
        return current(unpack('H*', $this->checksum));
    }

    public function getFiles()
    {
        return $this->files;
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

    public static function fromDb($checksum, DbConnection $connection)
    {
        $config = new static($connection);
        $config->loadFromDb($checksum);
        return $config;
    }

    public static function generate(DbConnection $connection)
    {
        $config = new static($connection);
        return $config->storeIfModified();
    }

    protected function storeIfModified()
    {
        $this->generateFromDb();
        $checksum = $this->calculateChecksum();
        $exists = $this->db->fetchOne(
            $this->db->select()->from(self::$table, 'COUNT(*)')->where('checksum = ?', $checksum)
        );
        if ((int) $exists === 0) {
            $this->store();
        }
        return $this;
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

    protected function store()
    {

        $fileTable = IcingaConfigFile::$table;
        $fileKey = IcingaConfigFile::$keyName;

        $this->db->beginTransaction();
        try {
            $existingQuery = $this->db->select()
                ->from($fileTable, 'checksum')
                ->where('checksum IN (?)', $this->getFilesChecksums());

            $existing = $this->db->fetchCol($existingQuery);

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
                        $fileKey  => $checksum,
                        'content' => $file->getContent()
                    )
                );
            }

            $this->db->insert(
                self::$table,
                array(
                    'duration'                  => $this->generationTime,
                    'last_activity_checksum'    => $this->getLastActivityChecksum(),
                    'checksum'                  => $this->getChecksum(),
                )
            );

            /** @var IcingaConfigFile $file */
            foreach ($this->files as $name => $file) {
                $this->db->insert(
                    'director_generated_config_file',
                    array(
                        'config_checksum'     => $this->getChecksum(),
                        'file_checksum' => $file->getChecksum(),
                        'file_path'     => $name,
                    )
                );
            }

            $this->db->commit();
        } catch (\Exception $e) {
            $this->db->rollBack();
            var_dump($e->getMessage());
        }

        return $this;
    }

    protected function generateFromDb()
    {
        $start = microtime(true);

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

        $this->generationTime = (microtime(true) - $start) * 1000;

        return $this;
    }

    protected function loadFromDb($checksum)
    {
        $query = $this->db->select()->from(
            self::$table,
            array('checksum', 'last_activity_checksum')
        )->where('checksum = ?', $checksum);
        $result = $this->db->fetchRow($query);
        $this->checksum = $result->checksum;
        $this->last_activity_checksum = $result->last_activity_checksum;
        $query = $this->db->select()->from(
            array('cf' => 'director_generated_config_file'),
            array(
                'file_path' => 'cf.file_path',
                'checksum'  => 'f.checksum',
                'content'   => 'f.content',
            )
        )->join(
            array('f' => 'director_generated_file'),
            'cf.file_checksum = f.checksum',
            array()
        )->where('cf.config_checksum = ?', $checksum);

        foreach ($this->db->fetchAll($query) as $row) {
            $file = new IcingaConfigFile();
            $this->files[$row->file_path] = $file->setContent($row->content);
        }

        return $this;
    }

    protected function createFileFromDb($type)
    {
        $class = 'Icinga\\Module\\Director\\Objects\\Icinga' . ucfirst($type);
        $objects = $class::loadAll($this->connection);

        if (! empty($objects)) {
            $file = new IcingaConfigFile();
            if ($type === 'command') {
                $file->prepend("library \"methods\"\n\n");
            }
            $file->addObjects($objects);
            $this->files[strtolower($type) . 's.conf'] = $file;
        }

        return $this;
    }

    public function getLastActivityHexChecksum()
    {
        return current(unpack('H*', $this->getLastActivityChecksum()));
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
        }

        return $this->lastActivityChecksum;
    }

    // TODO: wipe unused files
    // DELETE f FROM director_generated_file f left join director_generated_config_file cf ON f.checksum = cf.file_checksum WHERE cf.file_checksum IS NULL;

}

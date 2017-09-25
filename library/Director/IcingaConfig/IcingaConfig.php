<?php

namespace Icinga\Module\Director\IcingaConfig;

use Icinga\Application\Benchmark;
use Icinga\Application\Hook;
use Icinga\Application\Icinga;
use Icinga\Exception\ConfigurationError;
use Icinga\Exception\IcingaException;
use Icinga\Exception\NotFoundError;
use Icinga\Exception\ProgrammingError;
use Icinga\Module\Director\Db\Cache\PrefetchCache;
use Icinga\Module\Director\Db;
use Icinga\Module\Director\Hook\ShipConfigFilesHook;
use Icinga\Module\Director\Objects\IcingaObject;
use Icinga\Module\Director\Util;
use Icinga\Module\Director\Objects\IcingaHost;
use Icinga\Module\Director\Objects\IcingaZone;

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

    protected $configFormat;

    protected $deploymentModeV1;

    public static $table = 'director_generated_config';

    public function __construct(Db $connection)
    {
        // Make sure module hooks are loaded:
        Icinga::app()->getModuleManager()->loadEnabledModules();

        $this->connection = $connection;
        $this->db = $connection->getDbAdapter();
        $this->configFormat = $this->connection->settings()->config_format;
        $this->deploymentModeV1 = $this->connection->settings()->deployment_mode_v1;
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
        return $this->generationTime;
    }

    public function getFileCount()
    {
        return count($this->files);
    }

    public function getConfigFormat()
    {
        return $this->configFormat;
    }

    public function getDeploymentMode()
    {
        if ($this->isLegacy()) {
            return $this->deploymentModeV1;
        } else {
            throw new ProgrammingError('There is no deployment mode for Icinga 2 config format!');
        }
    }

    public function setConfigFormat($format)
    {
        if (! in_array($format, array('v1', 'v2'))) {
            throw new ConfigurationError(
                'Only Icinga v1 and v2 config format is supported, got "%s"',
                $format
            );
        }

        $this->configFormat = $format;

        return $this;
    }

    public function isLegacy()
    {
        return $this->configFormat === 'v1';
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

    public function getApplyCount()
    {
        $cnt = 0;
        foreach ($this->getFiles() as $file) {
            $cnt += $file->getApplyCount();
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

    /**
     * @return IcingaConfigFile[]
     */
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

    /**
     * @return array
     */
    public function getFileNames()
    {
        return array_keys($this->files);
    }

    /**
     * @param string $name
     *
     * @return IcingaConfigFile
     */
    public function getFile($name)
    {
        return $this->files[$name];
    }

    /**
     * @param string $checksum
     * @param Db $connection
     *
     * @return static
     */
    public static function load($checksum, Db $connection)
    {
        $config = new static($connection);
        $config->loadFromDb($checksum);
        return $config;
    }

    /**
     * @param string $checksum
     * @param Db $connection
     *
     * @return bool
     */
    public static function exists($checksum, Db $connection)
    {
        $db = $connection->getDbAdapter();
        $query = $db->select()->from(
            array('c' => self::$table),
            array('checksum' => $connection->dbHexFunc('c.checksum'))
        )->where(
            'checksum = ?',
            $connection->quoteBinary(Util::hex2binary($checksum))
        );

        return $db->fetchOne($query) === $checksum;
    }

    public static function loadByActivityChecksum($checksum, Db $connection)
    {
        $db = $connection->getDbAdapter();
        $query = $db->select()->from(
            array('c' => self::$table),
            array('checksum' => 'c.checksum')
        )->join(
            array('l' => 'director_activity_log'),
            'l.checksum = c.last_activity_checksum',
            array()
        )->where(
            'last_activity_checksum = ?',
            $connection->quoteBinary(Util::hex2binary($checksum))
        )->order('l.id DESC')->limit(1);

        return self::load($db->fetchOne($query), $connection);
    }

    public static function existsForActivityChecksum($checksum, Db $connection)
    {
        $db = $connection->getDbAdapter();
        $query = $db->select()->from(
            array('c' => self::$table),
            array('checksum' => $connection->dbHexFunc('c.checksum'))
        )->join(
            array('l' => 'director_activity_log'),
            'l.checksum = c.last_activity_checksum',
            array()
        )->where(
            'last_activity_checksum = ?',
            $connection->quoteBinary(Util::hex2binary($checksum))
        )->order('l.id DESC')->limit(1);

        return $db->fetchOne($query) === $checksum;
    }

    /**
     * @param Db $connection
     *
     * @return mixed
     */
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

    // TODO: prepare lookup cache if empty?
    public function getZoneName($id)
    {
        if (! array_key_exists($id, $this->zoneMap)) {
            $zone = IcingaZone::loadWithAutoIncId($id, $this->connection);
            $this->zoneMap[$id] = $zone->get('object_name');
        }

        return $this->zoneMap[$id];
    }

    /**
     * @return self
     */
    public function store()
    {
        $fileTable = IcingaConfigFile::$table;
        $fileKey = IcingaConfigFile::$keyName;

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

        return $this;
    }

    /**
     * @throws IcingaException
     *
     * @return self
     */
    protected function generateFromDb()
    {
        PrefetchCache::initialize($this->connection);

        $start = microtime(true);

        // Raise limits. TODO: do this in a failsafe way, and only if necessary
        if ((string) ini_get('memory_limit') !== '-1') {
            ini_set('memory_limit', '1024M');
        }

        ini_set('max_execution_time', 0);
        // Workaround for https://bugs.php.net/bug.php?id=68606 or similar
        ini_set('zend.enable_gc', 0);

        if (! $this->connection->isPgsql() && $this->db->quote("1\0") !== '\'1\\0\'') {
            throw new IcingaException(
                'Refusing to render the configuration, your DB layer corrupts binary data.'
                . ' You might be affected by Zend Framework bug #655'
            );
        }

        $this
            ->prepareGlobalBasics()
            ->createFileFromDb('zone')
            ->createFileFromDb('endpoint')
            ->createFileFromDb('command')
            ->createFileFromDb('timePeriod')
            ->createFileFromDb('hostGroup')
            ->createFileFromDb('host')
            ->createFileFromDb('serviceGroup')
            ->createFileFromDb('service')
            ->createFileFromDb('serviceSet')
            ->createFileFromDb('userGroup')
            ->createFileFromDb('user')
            ->createFileFromDb('notification')
            ->createFileFromDb('dependency')
            ;

        if (! $this->isLegacy()) {
            $this->configFile('zones.d/director-global/commands')
                ->prepend("library \"methods\"\n\n");
        }

        PrefetchCache::forget();
        IcingaHost::clearAllPrefetchCaches();

        $this->generationTime = (int) ((microtime(true) - $start) * 1000);

        return $this;
    }

    /**
     * @return self
     */
    protected function prepareGlobalBasics()
    {
        if ($this->isLegacy()) {
            $this->configFile(
                sprintf(
                    'director/%s/001-director-basics',
                    $this->connection->getDefaultGlobalZoneName()
                ),
                '.cfg'
            )->prepend(
                $this->renderLegacyDefaultNotification()
            );

            return $this;
        }

        $this->configFile(
            sprintf(
                'zones.d/%s/001-director-basics',
                $this->connection->getDefaultGlobalZoneName()
            )
        )->prepend(
            "\nconst DirectorStageDir = dirname(dirname(current_filename))\n"
            . $this->renderHostOverridableVars()
            . $this->renderMagicApplyFor()
        );

        return $this;
    }

    protected function renderHostOverridableVars()
    {
        $settings = $this->connection->settings();

        return sprintf(
            '
const DirectorOverrideVars = "%s"
const DirectorOverrideTemplate = "%s"

template Service DirectorOverrideTemplate {
  /**
   * Seems that host is missing when used in a service object, works fine for
   * apply rules
   */
  if (! host) {
    var host = get_host(host_name)
  }

  if (vars) {
    vars += host.vars[DirectorOverrideVars][name]
  } else {
    vars = host.vars[DirectorOverrideVars][name]
  }
}
',
            $settings->override_services_varname,
            $settings->override_services_templatename
        );
    }

    protected function renderMagicApplyFor()
    {
        if (! $this->usesMagicApplyFor()) {
            return '';
        }

        $varname = $this->getMagicApplyVarName();

        return sprintf(
            '
/* Warning: this is experimental and might be removed */
apply Service for (title => params in host.vars["%s"]) {

  var override = host.vars["%s_vars"][title]

  if (typeof(params["templates"]) in [Array, String]) {
    for (tpl in params["templates"]) {
      import tpl
    }
  } else {
    import title
  }

  if (typeof(params.vars) == Dictionary) {
    vars += params.vars
  }

  if (typeof(params["host_name"]) == String) {
    host_name = params["host_name"]
  }

  import DirectorOverrideTemplate
}
',
            $varname,
            $varname
        );
    }

    protected function getMagicApplyVarName()
    {
        return $this->connection->settings()->magic_apply_for;
    }

    protected function usesMagicApplyFor()
    {
        $db = $this->db;
        $query = $db->select()->from(
            array('hv' => 'icinga_host_var'),
            array('c' => 'COUNT(*)')
        )->where(
            'hv.varname = ?',
            $this->getMagicApplyVarName()
        );

        return $db->fetchOne($query);
    }

    /**
     * @param string $checksum
     *
     * @throws NotFoundError
     *
     * @return self
     */
    protected function loadFromDb($checksum)
    {
        $query = $this->db->select()->from(
            self::$table,
            array('checksum', 'last_activity_checksum', 'duration')
        )->where('checksum = ?', $this->dbBin($checksum));
        $result = $this->db->fetchRow($query);

        if (empty($result)) {
            throw new NotFoundError('Got no config for %s', Util::binary2hex($checksum));
        }

        $this->checksum = $this->binFromDb($result->checksum);
        $this->generationTime = $result->duration;
        $this->lastActivityChecksum = $this->binFromDb($result->last_activity_checksum);

        $query = $this->db->select()->from(
            array('cf' => 'director_generated_config_file'),
            array(
                'file_path'    => 'cf.file_path',
                'checksum'     => 'f.checksum',
                'content'      => 'f.content',
                'cnt_object'   => 'f.cnt_object',
                'cnt_template' => 'f.cnt_template',
                'cnt_apply'    => 'f.cnt_apply',
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
                ->setTemplateCount($row->cnt_template)
                ->setApplyCount($row->cnt_apply);
        }

        return $this;
    }

    protected function createFileFromDb($type)
    {
        /** @var IcingaObject $class */
        $class = 'Icinga\\Module\\Director\\Objects\\Icinga' . ucfirst($type);
        Benchmark::measure(sprintf('Prefetching %s', $type));
        $objects = $class::prefetchAll($this->connection);
        return $this->createFileForObjects($type, $objects);
    }

    /**
     * @param string         $type    Short object type, like 'service' or 'zone'
     * @param IcingaObject[] $objects
     *
     * @return self
     */
    protected function createFileForObjects($type, $objects)
    {
        if (empty($objects)) {
            return $this;
        }

        Benchmark::measure(sprintf('Generating %ss: %s', $type, count($objects)));
        foreach ($objects as $object) {
            if ($object->isExternal()) {
                if ($type === 'zone') {
                    $this->zoneMap[$object->get('id')] = $object->getObjectName();
                }
            }
            $object->renderToConfig($this);
        }

        Benchmark::measure(sprintf('%ss done', $type));
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
            'notification',
            'dependency'
        );

        return in_array($type, $types);
    }

    /**
     * @param string $name   Relative config file name
     * @param string $suffix Config file suffix, defaults to '.conf'
     *
     * @return IcingaConfigFile
     */
    public function configFile($name, $suffix = '.conf')
    {
        $filename = $name . $suffix;
        if (! array_key_exists($filename, $this->files)) {
            $this->files[$filename] = new IcingaConfigFile();
        }

        return $this->files[$filename];
    }

    protected function collectExtraFiles()
    {
        /** @var ShipConfigFilesHook $hook */
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

    protected function renderLegacyDefaultNotification()
    {
        return preg_replace('~^ {12}~m', '', '
            #
            # Default objects to avoid warnings
            #

            define contact {
                contact_name                   icingaadmin
                alias                          Icinga Admin
                host_notifications_enabled     0
                host_notification_commands     notify-never-default
                host_notification_period       notification_none
                service_notifications_enabled  0
                service_notification_commands  notify-never-default
                service_notification_period    notification_none
            }

            define contactgroup {
                contactgroup_name  icingaadmins
                members            icingaadmin
            }

            define timeperiod {
                timeperiod_name  notification_none
                alias            No Notifications
            }

            define command {
                command_name notify-never-default
                command_line /bin/echo "NOOP"
            }
        ');
    }
}

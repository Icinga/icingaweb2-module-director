<?php

namespace Icinga\Module\Director\Objects;

use Icinga\Module\Director\Db;
use Icinga\Module\Director\DirectorObject\Automation\ExportInterface;
use Icinga\Module\Director\Exception\DuplicateKeyException;
use Icinga\Module\Director\IcingaConfig\IcingaConfigHelper as c;
use Icinga\Module\Director\IcingaConfig\IcingaLegacyConfigHelper as c1;
use Icinga\Module\Director\Objects\Extension\Arguments;
use Zend_Db_Select as DbSelect;

class IcingaCommand extends IcingaObject implements ObjectWithArguments, ExportInterface
{
    use Arguments;

    protected $table = 'icinga_command';

    protected $type = 'CheckCommand';

    protected $uuidColumn = 'uuid';

    protected $defaultProperties = [
        'id'              => null,
        'uuid'            => null,
        'object_name'     => null,
        'object_type'     => null,
        'disabled'        => 'n',
        'methods_execute' => null,
        'command'         => null,
        'timeout'         => null,
        'zone_id'         => null,
        'is_string'       => null,
    ];

    protected $booleans = [
        'is_string' => 'is_string',
    ];

    protected $supportsCustomVars = true;

    protected $supportsFields = true;

    protected $supportsImports = true;

    protected $supportedInLegacy = true;

    protected $intervalProperties = [
        'timeout' => 'timeout',
    ];

    protected $relations = [
        'zone' => 'IcingaZone',
    ];

    protected static $pluginDir;

    protected $hiddenExecuteTemplates = [
        'PluginCheck'        => 'plugin-check-command',
        'PluginNotification' => 'plugin-notification-command',
        'PluginEvent'        => 'plugin-event-command',

        // Special, internal:
        'IcingaCheck'      => 'icinga-check-command',
        'ClusterCheck'     => 'cluster-check-command',
        'ClusterZoneCheck' => 'plugin-check-command',
        'IdoCheck'         => 'ido-check-command',
        'RandomCheck'      => 'random-check-command',
        'CrlCheck'         => 'clr-check-command',
    ];

    /**
     * Render the 'medhods_execute' property as 'execute'
     *
     * Execute is a reserved word in SQL, column name was prefixed
     *
     * Avoid complaints for method names with underscore:
     * @codingStandardsIgnoreStart
     *
     * @return string
     */
    protected function renderMethods_execute()
    {
        // @codingStandardsIgnoreEnd
        return '';
    }

    protected function renderObjectHeader()
    {
        if ($execute = $this->get('methods_execute')) {
            $itlImport = sprintf(
                '    import "%s"' . "\n",
                $this->hiddenExecuteTemplates[$execute]
            );
        } else {
            $itlImport = '';
        }

        $execute = $this->getSingleResolvedProperty('methods_execute');
        if ($execute === 'PluginNotification') {
            return $this->renderObjectHeaderWithType('NotificationCommand') . $itlImport;
        } elseif ($execute === 'PluginEvent') {
            return $this->renderObjectHeaderWithType('EventCommand') . $itlImport;
        } else {
            return parent::renderObjectHeader() . $itlImport;
        }
    }

    /**
     * @param $type
     * @return string
     */
    protected function renderObjectHeaderWithType($type)
    {
        return sprintf(
            "%s %s %s {\n",
            $this->getObjectTypeName(),
            $type,
            c::renderString($this->getObjectName())
        );
    }

    public function mungeCommand($value)
    {
        if (is_array($value)) {
            $value = implode(' ', $value);
        } elseif (is_object($value)) {
            // {  type => Function } -> really??
            return null;
            // return $value;
        }

        if (self::$pluginDir !== null) {
            if (($pos = strpos($value, self::$pluginDir)) === 0) {
                $value = substr($value, strlen(self::$pluginDir) + 1);
            }
        }

        return $value;
    }

    public function getNextSkippableKeyName()
    {
        $key = $this->makeSkipKey();
        $cnt = 1;
        while (isset($this->arguments()->$key)) {
            $cnt++;
            $key = $this->makeSkipKey($cnt);
        }

        return $key;
    }

    protected function makeSkipKey($num = null)
    {
        if ($num === null) {
            return '(no key)';
        }

        return sprintf('(no key.%d)', $num);
    }

    protected function prefersGlobalZone()
    {
        return true;
    }

    /**
     * @return string
     * @throws \Zend_Db_Select_Exception
     */
    public function countDirectUses()
    {
        $db = $this->getDb();
        $id = (int) $this->get('id');

        $qh = $db->select()->from(
            array('h' => 'icinga_host'),
            array('cnt' => 'COUNT(*)')
        )->where('h.check_command_id = ?', $id)
         ->orWhere('h.event_command_id = ?', $id);
        $qs = $db->select()->from(
            array('s' => 'icinga_service'),
            array('cnt' => 'COUNT(*)')
        )->where('s.check_command_id = ?', $id)
            ->orWhere('s.event_command_id = ?', $id);
        $qn = $db->select()->from(
            array('n' => 'icinga_notification'),
            array('cnt' => 'COUNT(*)')
        )->where('n.command_id = ?', $id);
        $query = $db->select()->union(
            [$qh, $qs, $qn],
            DbSelect::SQL_UNION_ALL
        );

        return $db->fetchOne($db->select()->from(
            ['all_cnts' => $query],
            ['cnt' => 'SUM(cnt)']
        ));
    }

    /**
     * @return bool
     * @throws \Zend_Db_Select_Exception
     */
    public function isInUse()
    {
        return $this->countDirectUses() > 0;
    }

    public function getUniqueIdentifier()
    {
        return $this->getObjectName();
    }

    /**
     * @return object
     * @deprecated please use \Icinga\Module\Director\Data\Exporter
     * @throws \Icinga\Exception\NotFoundError
     */
    public function export()
    {
        $props = (array) $this->toPlainObject();
        if (isset($props['arguments'])) {
            foreach ($props['arguments'] as $key => $argument) {
                if (property_exists($argument, 'command_id')) {
                    unset($props['arguments'][$key]->command_id);
                }
            }
        }
        $props['fields'] = $this->loadFieldReferences();
        ksort($props);

        return (object) $props;
    }

    /**
     * @param $plain
     * @param Db $db
     * @param bool $replace
     * @return IcingaCommand
     * @throws DuplicateKeyException
     * @throws \Icinga\Exception\NotFoundError
     */
    public static function import($plain, Db $db, $replace = false)
    {
        $properties = (array) $plain;
        $name = $properties['object_name'];
        $key = $name;

        if ($replace && static::exists($key, $db)) {
            $object = static::load($key, $db);
        } elseif (static::exists($key, $db)) {
            throw new DuplicateKeyException(
                'Command "%s" already exists',
                $name
            );
        } else {
            $object = static::create([], $db);
        }

        unset($properties['fields']);
        $object->setProperties($properties);

        return $object;
    }

    /**
     * @deprecated please use \Icinga\Module\Director\Data\FieldReferenceLoader
     * @return array
     */
    protected function loadFieldReferences()
    {
        $db = $this->getDb();

        $res = $db->fetchAll(
            $db->select()->from([
                'cf' => 'icinga_command_field'
            ], [
                'cf.datafield_id',
                'cf.is_required',
                'cf.var_filter',
            ])->join(['df' => 'director_datafield'], 'df.id = cf.datafield_id', [])
                ->where('command_id = ?', $this->get('id'))
                ->order('varname ASC')
        );

        if (empty($res)) {
            return [];
        } else {
            foreach ($res as $field) {
                $field->datafield_id = (int) $field->datafield_id;
            }

            return $res;
        }
    }

    protected function renderCommand()
    {
        $command = $this->get('command');
        $prefix = '';
        if (preg_match('~^([A-Z][A-Za-z0-9_]+\s\+\s)(.+?)$~', $command, $m)) {
            $prefix = $m[1];
            $command = $m[2];
        } elseif (! $this->isAbsolutePath($command)) {
            $prefix = 'PluginDir + ';
            $command = '/' . $command;
        }

        $inherited = $this->getInheritedProperties();

        if ($this->get('is_string') === 'y' || ($this->get('is_string') === null
                && property_exists($inherited, 'is_string') && $inherited->is_string === 'y')) {
            return c::renderKeyValue('command', $prefix . c::renderString($command));
        } else {
            $parts = preg_split('/\s+/', $command, -1, PREG_SPLIT_NO_EMPTY);
            array_unshift($parts, c::alreadyRendered($prefix . c::renderString(array_shift($parts))));

            return c::renderKeyValue('command', c::renderArray($parts));
        }
    }

    /**
     * @codingStandardsIgnoreStart
     */
    protected function renderIs_string()
    {
        // @codingStandardsIgnoreEnd
        return '';
    }

    protected function isAbsolutePath($path)
    {
         return $path[0] === '/'
            || $path[0] === '\\'
            || preg_match('/^[A-Za-z]:\\\/', substr($path, 0, 3))
            || preg_match('/^%[A-Z][A-Za-z0-9\(\)-]*%/', $path);
    }

    public static function setPluginDir($pluginDir)
    {
        self::$pluginDir = $pluginDir;
    }

    public function getLegacyObjectType()
    {
        // there is only one type of command in Icinga 1.x
        return 'command';
    }

    protected function renderLegacyCommand()
    {
        $command = $this->get('command');
        if (preg_match('~^(\$USER\d+\$/?)(.+)$~', $command)) {
            // should be fine, since the user decided to use a macro
        } elseif (! $this->isAbsolutePath($command)) {
            $command = '$USER1$/'.$command;
        }

        return c1::renderKeyValue(
            $this->getLegacyObjectType().'_line',
            c1::renderString($command)
        );
    }
}

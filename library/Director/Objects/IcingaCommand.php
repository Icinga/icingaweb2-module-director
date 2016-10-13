<?php

namespace Icinga\Module\Director\Objects;

use Icinga\Module\Director\IcingaConfig\IcingaConfigHelper as c;

class IcingaCommand extends IcingaObject
{
    protected $table = 'icinga_command';

    protected $type = 'CheckCommand';

    protected $defaultProperties = array(
        'id'                    => null,
        'object_name'           => null,
        'object_type'           => null,
        'disabled'              => 'n',
        'methods_execute'       => null,
        'command'               => null,
        'timeout'               => null,
        'zone_id'               => null,
    );

    protected $supportsCustomVars = true;

    protected $supportsFields = true;

    protected $supportsImports = true;

    protected $supportsArguments = true;

    protected $supportedInLegacy = true;

    protected $intervalProperties = array(
        'timeout' => 'timeout',
    );

    protected $relations = array(
        'zone'             => 'IcingaZone',
    );

    protected static $pluginDir;

    protected $hiddenExecuteTemplates = array(
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
    );

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
        if ($this->methods_execute) {
            $itlImport = sprintf(
                '    import "%s"' . "\n",
                $this->hiddenExecuteTemplates[$this->methods_execute]
            );
        } else {
            $itlImport = '';
        }

        $execute = $this->getResolvedProperty('methods_execute');
        if ($execute === 'PluginNotification') {
            return $this->renderObjectHeaderWithType('NotificationCommand') . $itlImport;
        } elseif ($execute === 'PluginEvent') {
            return $this->renderObjectHeaderWithType('EventCommand') . $itlImport;
        } else {
            return parent::renderObjectHeader() . $itlImport;
        }
    }

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
            return $value;
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

    protected function renderCommand()
    {
        $command = $this->command;
        $prefix = '';
        if (preg_match('~^([A-Z][A-Za-z0-9_]+\s\+\s)(.+?)$~', $command, $m)) {
            $prefix  = $m[1];
            $command = $m[2];
        } elseif (! $this->isAbsolutePath($command)) {
            $prefix = 'PluginDir + ';
            $command = '/' . $command;
        }
        $parts = preg_split('/\s+/', $command, -1, PREG_SPLIT_NO_EMPTY);
        array_unshift($parts, c::alreadyRendered($prefix . c::renderString(array_shift($parts))));
        
        return c::renderKeyValue('command', c::renderArray($parts));
    }

    protected function isAbsolutePath($path)
    {
         return $path[0] === '/'
            || $path[0] === '\\'
            || preg_match('/^[A-Za-z]:\\\/', substr($path, 0, 3));
    }

    public static function setPluginDir($pluginDir)
    {
        self::$pluginDir = $pluginDir;
    }
}

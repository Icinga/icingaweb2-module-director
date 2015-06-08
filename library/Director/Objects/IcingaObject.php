<?php

namespace Icinga\Module\Director\Objects;

use Icinga\Module\Director\Data\Db\DbObject;
use Icinga\Exception\ProgrammingError;
use Exception;

abstract class IcingaObject extends DbObject
{
    protected $keyName = 'id';

    protected $autoincKeyName = 'id';

    protected $supportsCustomVars = false;

    private $type;

    public function supportsCustomVars()
    {
        return $this->supportsCustomVars;
    }

    public function isTemplate()
    {
        return $this->hasProperty('object_type')
            && $this->object_type === 'template';
    }

    public function isApplyRule()
    {
        return $this->hasProperty('object_type')
            && $this->object_type === 'apply';
    }

    public function onInsert()
    {
        DirectorActivityLog::logCreation($this, $this->connection);
    }

    public function onUpdate()
    {
        DirectorActivityLog::logModification($this, $this->connection);
    }

    public function onDelete()
    {
        DirectorActivityLog::logRemoval($this, $this->connection);
    }

    protected function renderImports()
    {
        // TODO: parent_host ORDERed by weigth...
        return '';
    }

    protected function renderProperties()
    {
        $out = '';
        $blacklist = array(
            'id',
            'object_name',
            'object_type',
        );

        foreach ($this->properties as $key => $value) {

            if ($value === null) continue;
            if (in_array($key, $blacklist)) continue;

            $method = 'render' . ucfirst($key);
            if (method_exists($this, $method)) {
                $out .= $this->$method($value);
            } else {
                $out .= $this->renderKeyValue($key, $this->renderString($value));
            }
        }

        return $out;
    }

    protected function renderKeyValue($key, $value, $prefix = '')
    {
        return sprintf(
            "%s    %s = %s\n",
            $prefix,
            $key,
            $value
        );
    }

    protected function renderBoolean($value)
    {
        if ($value === 'y') {
            return 'true';
        } elseif ($value === 'n') {
            return 'false';
        } else {
            throw new ProgrammingError('%s is not a valid boolean', $value);
        }
    }

    protected function renderBooleanProperty($key)
    {
        return $this->renderKeyValue($key, $this->renderBoolean($this->$key));
    }

    protected function renderSuffix()
    {
        return "}\n";
    }

    protected function renderCustomVars()
    {
        if ($this->supportsCustomVars()) {
            // TODO
        }

        return '';
    }

    protected function renderCommandProperty($commandId, $propertyName = 'check_command')
    {
        return $this->renderKeyValue(
            $propertyName,
            $this->renderString($this->connection->getCommandName($commandId))
        );
    }

    protected function renderZoneProperty($zoneId, $propertyName = 'zone')
    {
        return $this->renderKeyValue(
            $propertyName,
            $this->renderString($this->connection->getZoneName($zoneId))
        );
    }

    protected function renderZone_id()
    {
        return $this->renderZoneProperty($this->zone_id);
    }

    protected function renderObjectHeader()
    {
        return sprintf(
            "%s %s %s {\n",
            $this->getObjectTypeName(),
            $this->getType(),
            $this->renderString($this->getObjectName())
        );
    }

    public function toConfigString()
    {
        return implode(array(
            $this->renderObjectHeader(),
            $this->renderImports(),
            $this->renderProperties(),
            $this->renderCustomVars(),
            $this->renderSuffix()
        ));
    }

    protected function renderString($string)
    {
        $string = preg_replace('~\\\~', '\\\\', $string);
        $string = preg_replace('~"~', '\\"', $string);

        return sprintf('"%s"', $string);
    }

    protected function getType()
    {
        if ($this->type === null) {
            $parts = explode('\\', get_class($this));
            // 6 = strlen('Icinga');
            $this->type = substr(end($parts), 6);
        }

        return $this->type;
    }

    protected function getObjectTypeName()
    {
        if ($this->isTemplate()) {
            return 'template';
        } elseif ($this->isApplyRule()) {
            return 'apply';
        } else {
            return 'object';
        }
    }

    protected function getObjectName()
    {
        if ($this->hasProperty('object_name')) {
            return $this->object_name;
        } else {
            // TODO: replace with an exception once finished
            return 'ERROR: NO NAME';
        }
    }

    public function __toString()
    {
        try {
            return $this->toConfigString();
        } catch (Exception $e) {
            trigger_error($e);
            $previousHandler = set_exception_handler(function () {});
            restore_error_handler();
            call_user_func($previousHandler, $e);
            die();
        }
    }
}

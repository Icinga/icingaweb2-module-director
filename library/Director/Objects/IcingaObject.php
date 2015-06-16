<?php

namespace Icinga\Module\Director\Objects;

use Icinga\Module\Director\CustomVariable\CustomVariables;
use Icinga\Module\Director\Data\Db\DbObject;
use Icinga\Module\Director\IcingaConfig\IcingaConfigRenderer;
use Icinga\Module\Director\IcingaConfig\IcingaConfigHelper as c;
use Icinga\Exception\ProgrammingError;
use Exception;

abstract class IcingaObject extends DbObject implements IcingaConfigRenderer
{
    protected $keyName = 'id';

    protected $autoincKeyName = 'id';

    protected $supportsCustomVars = false;

    protected $supportsGroups = false;

    private $type;

    private $vars;

    private $groups;

    public function supportsCustomVars()
    {
        return $this->supportsCustomVars;
    }

    public function supportsGroups()
    {
        return $this->supportsGroups;
    }

    public function groups()
    {
        $this->assertGroupsSupport();
        if ($this->groups === null) {
            if ($this->hasBeenLoadedFromDb()) {
                $this->groups = IcingaObjectGroups::loadForStoredObject($this);
            } else {
                $this->groups = new IcingaObjectGroups($this);
            }
        }

        return $this->groups;
    }

    protected function assertCustomVarsSupport()
    {
        if (! $this->supportsCustomVars()) {
            throw new ProgrammingError(
                'Objects of type "%s" have no custom vars',
                $this->getType()
            );
        }

        return $this;
    }

    protected function assertGroupsSupport()
    {
        if (! $this->supportsGroups()) {
            throw new ProgrammingError(
                'Objects of type "%s" have no groups',
                $this->getType()
            );
        }

        return $this;
    }

    public function vars()
    {
        $this->assertCustomVarsSupport();
        if ($this->vars === null) {
            if ($this->hasBeenLoadedFromDb()) {
                $this->vars = CustomVariables::loadForStoredObject($this);
            } else {
                $this->vars = new CustomVariables();
            }
        }

        return $this->vars;
    }

    public function getVarsTableName()
    {
        return $this->getTableName() . '_var';
    }

    public function getShortTableName()
    {
        // strlen('icinga_') = 7
        return substr($this->getTableName(), 7);
    }

    public function getVarsIdColumn()
    {
        return $this->getShortTableName() . '_id';
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
                $out .= c::renderKeyValue($key, c::renderString($value));
            }
        }

        return $out;
    }

    protected function renderBooleanProperty($key)
    {
        return c::renderKeyValue($key, c::renderBoolean($this->$key));
    }

    protected function renderSuffix()
    {
        return "}\n\n";
    }

    /**
     * @return string
     */
    protected function renderCustomVars()
    {
        if ($this->supportsCustomVars()) {
            return $this->vars()->toConfigString();
        } else {
            return '';
        }
    }

    /**
     * @return string
     */
    protected function renderGroups()
    {
        if ($this->supportsGroups()) {
            return $this->groups()->toConfigString();
        } else {
            return '';
        }
    }

    protected function renderCommandProperty($commandId, $propertyName = 'check_command')
    {
        return c::renderKeyValue(
            $propertyName,
            c::renderString($this->connection->getCommandName($commandId))
        );
    }

    protected function renderZoneProperty($zoneId, $propertyName = 'zone')
    {
        return c::renderKeyValue(
            $propertyName,
            c::renderString($this->connection->getZoneName($zoneId))
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
            c::renderString($this->getObjectName())
        );
    }

    public function toConfigString()
    {
        return implode(array(
            $this->renderObjectHeader(),
            $this->renderImports(),
            $this->renderProperties(),
            $this->renderGroups(),
            $this->renderCustomVars(),
            $this->renderSuffix()
        ));
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
            if ($previousHandler !== null) {
                call_user_func($previousHandler, $e);
                die();
            } else {
                die($e->getMessage());
            }
        }
    }
}

<?php

namespace Icinga\Module\Director\ConfigRenderer;

use Icinga\Module\Director\IcingaConfig\IcingaConfigHelper as c;
use Icinga\Module\Director\Objects\IcingaCommand;
use Icinga\Module\Director\Objects\IcingaDependency;
use Icinga\Module\Director\Objects\IcingaObject;
use Icinga\Module\Director\Objects\IcingaService;
use Icinga\Module\Director\Objects\IcingaTimePeriod;

class IcingaConfigRenderer
{
    protected static $hiddenExecuteTemplates = [
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

    // TODO: Consider using a static map
    protected static $classTypeMap = [];

    public static function renderObject(IcingaObject $object)
    {
        $dynamicName = static::eventuallyGetDynamicNameProperty($object);

        return self::renderObjectDeclaration($object, $dynamicName === null)
            . static::eventuallyRenderApplyForHeader($object)
            . static::eventuallyRenderApplyToHeader($object)
            . " {\n"
            . static::renderDynamicNameProperty($dynamicName)
            . static::renderSpecialIcingaImports($object);
    }

    // assign_filter: service, hostgroup, service_group, notification, dependency, scheduled_downtime
    //                service_set (=service)

    protected static function renderDynamicNameProperty($dynamicName)
    {
        if ($dynamicName === null) {
            return '';
        }

        return c::renderKeyValue('name', $dynamicName);
    }

    protected static function renderSpecialIcingaImports(IcingaObject $object)
    {
        if ($object instanceof IcingaTimePeriod) {
            return static::renderAdditionalImport('legacy-timeperiod');
        }
        if (($object instanceof IcingaCommand) && $execute = $object->get('methods_execute')) {
            return static::renderAdditionalImport(self::$hiddenExecuteTemplates[$execute]);
        }

        return '';
    }

    protected static function eventuallyRenderApplyToHeader(IcingaObject $object)
    {
        if ($object->isApplyRule() && $object->hasProperty('apply_to')) {
            return ' to ' . static::requireApplyTo($object);
        }

        return '';
    }

    protected static function eventuallyRenderApplyForHeader(IcingaObject $object)
    {
        if (! $object->isApplyRule()) {
            return '';
        }

        if ($object instanceof IcingaService && $object->hasBeenAssignedToHostTemplate()) {
            return '';
        }

        if ($object instanceof IcingaDependency && $object->renderForArray) { // TODO: this is the clone?!
            return sprintf(
                " for (host_parent_name in %s)",
                $object->get('parent_host_var')
            );
        }

        if ($object->hasProperty('apply_for') && ($for = $object->get('apply_for')) !== null) {
            return " for (config in $for)";
        }

        return '';
    }

    protected static function requireApplyTo(IcingaObject $object)
    {
        $to = $object->get('apply_to');
        if ($to === null) {
            throw new IcingaConfigurationError(
                'Applied %s "%s" has no valid object type',
                static::getObjectType($object),
                $object->getObjectName()
            );
        }

        return ucfirst($to);
    }

    protected static function renderAdditionalImport($import)
    {
        // Hint: unescaped, internal use only.
        return "    import \"$import\"\n";
    }

    protected static function getObjectType(IcingaObject $object)
    {
        if ($object instanceof IcingaCommand) {
            return self::getCommandSpecificObjectType($object);
        }

        return static::determineObjectType($object);
    }

    protected static function determineObjectType(IcingaObject $object)
    {
        $class = \get_class($object);
        if (! isset(self::$classTypeMap[$class])) {
            $parts = \explode('\\', $class);
            // 6 = strlen('Icinga');
            self::$classTypeMap[$class] = \substr(\end($parts), 6);
        }

        return self::$classTypeMap[$class];
    }

    protected static function getCommandSpecificObjectType(IcingaCommand $command)
    {
        $execute = $command->getSingleResolvedProperty('methods_execute');
        switch ($execute) {
            case 'PluginNotification':
                return 'NotificationCommand';
            case 'PluginEvent':
                return 'EventCommand';
            default:
                return 'CheckCommand';
        }
    }

    protected static function getObjectTypeName(IcingaObject $object)
    {
        if ($object->isTemplate()) {
            return 'template';
        }
        if ($object->isApplyRule()) {
            return 'apply';
        }

        return 'object';
    }

    protected static function renderObjectDeclaration(IcingaObject $object, $renderName)
    {
        $header = static::getObjectTypeName($object) . ' ' . static::getObjectType($object);
        if ($renderName) {
            $header .= ' ' . c::renderString($object->getObjectName());
        }

        return $header;
    }

    protected static function eventuallyGetDynamicNameProperty(IcingaObject $object)
    {
        if ($object instanceof IcingaService) {
            if ($object->isApplyRule()
                && !$object->hasBeenAssignedToHostTemplate()
                && $object->get('apply_for') !== null
            ) {
                $name = $object->getObjectName();
                if (c::stringHasMacro($name)) {
                    return c::renderStringWithVariables($name);
                }
            }
        }

        return null;
    }
}

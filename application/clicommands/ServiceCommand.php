<?php

namespace Icinga\Module\Director\Clicommands;

use Icinga\Cli\Params;
use Icinga\Module\Director\Cli\ObjectCommand;
use Icinga\Module\Director\DirectorObject\Lookup\ServiceFinder;
use Icinga\Module\Director\Objects\IcingaHost;
use RuntimeException;

/**
 * Manage Icinga Services
 *
 * Use this command to show, create, modify or delete Icinga Service
 * objects
 */
class ServiceCommand extends ObjectCommand
{
    public function setAction()
    {
        if (($host = $this->params->get('host')) && $this->params->shift('allow-overrides')) {
            $this->setServiceProperties($host);
        }

        parent::setAction();
    }

    protected function setServiceProperties($hostname)
    {
        $name = $this->getName();
        $host = IcingaHost::load($hostname, $this->db());
        $service = ServiceFinder::find($host, $name);
        if ($service->requiresOverrides()) {
            $this->params->shift('host');
            self::checkForOverrideSafety($this->params);
            $properties = $this->remainingParams();
            self::applyOverriddenVars($host, $name, $properties);
            $this->persistChanges($host, 'Host', $host->getObjectName() . " (Overrides for $name)", 'modified');
        }
    }

    protected static function applyOverriddenVars(IcingaHost $host, $serviceName, $properties)
    {
        self::assertVarsForOverrides($properties);
        $current = $host->getOverriddenServiceVars($serviceName);
        foreach ($properties as $key => $value) {
            if ($key === 'vars') {
                foreach ($value as $k => $v) {
                    $current->$k = $v;
                }
            } else {
                $current->{substr($key, 5)} = $value;
            }
        }
        $host->overrideServiceVars($serviceName, $current);
    }

    protected static function checkForOverrideSafety(Params $params)
    {
        if ($params->shift('replace')) {
            throw new RuntimeException('--replace is not available for Variable Overrides');
        }
        $appends = self::stripPrefixedProperties($params, 'append-');
        $remove = self::stripPrefixedProperties($params, 'remove-');
        self::assertVarsForOverrides($appends);
        self::assertVarsForOverrides($remove);
        if (!empty($appends)) {
            throw new RuntimeException('--append- is not available for Variable Overrides');
        }
        if (!empty($remove)) {
            throw new RuntimeException('--remove- is not available for Variable Overrides');
        }
        // Alternative, untested:
        // $this->appendToArrayProperties($object, $appends);
        // $this->removeProperties($object, $remove);
    }

    protected static function assertVarsForOverrides($properties)
    {
        if (empty($properties)) {
            return;
        }

        foreach ($properties as $key => $value) {
            if ($key !== 'vars' && substr($key, 0, 5) !== 'vars.') {
                throw new RuntimeException("Only Custom Variables can be set based on Variable Overrides");
            }
        }
    }

    protected function load($name)
    {
        return parent::load($this->makeServiceKey($name));
    }

    protected function exists($name)
    {
        return parent::exists($this->makeServiceKey($name));
    }

    protected function makeServiceKey($name)
    {
        if ($host = $this->params->get('host')) {
            return [
                'object_name' => $name,
                'host_id'     => IcingaHost::load($host, $this->db())->get('id'),
            ];
        } else {
            return [
                'object_name' => $name,
                'object_type' => 'template',
            ];
        }
    }
}

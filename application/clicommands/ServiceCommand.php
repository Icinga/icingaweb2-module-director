<?php

namespace Icinga\Module\Director\Clicommands;

use Icinga\Cli\Params;
use Icinga\Module\Director\Cli\ObjectCommand;
use Icinga\Module\Director\DirectorObject\Lookup\ServiceFinder;
use Icinga\Module\Director\Objects\IcingaHost;
use Icinga\Module\Director\Resolver\OverrideHelper;
use InvalidArgumentException;

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
        $serviceName = $this->getName();
        $host = IcingaHost::load($hostname, $this->db());
        $service = ServiceFinder::find($host, $serviceName);
        if ($service->requiresOverrides()) {
            self::checkForOverrideSafety($this->params);
            $properties = $this->remainingParams();
            unset($properties['host']);
            OverrideHelper::applyOverriddenVars($host, $serviceName, $properties);
            $this->persistChanges($host, 'Host', $hostname . " (Overrides for $serviceName)", 'modified');
        }
    }

    protected static function checkForOverrideSafety(Params $params)
    {
        if ($params->shift('replace')) {
            throw new InvalidArgumentException('--replace is not available for Variable Overrides');
        }
        $appends = self::stripPrefixedProperties($params, 'append-');
        $remove = self::stripPrefixedProperties($params, 'remove-');
        OverrideHelper::assertVarsForOverrides($appends);
        OverrideHelper::assertVarsForOverrides($remove);
        if (!empty($appends)) {
            throw new InvalidArgumentException('--append- is not available for Variable Overrides');
        }
        if (!empty($remove)) {
            throw new InvalidArgumentException('--remove- is not available for Variable Overrides');
        }
        // Alternative, untested:
        // $this->appendToArrayProperties($object, $appends);
        // $this->removeProperties($object, $remove);
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

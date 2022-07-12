<?php

namespace Icinga\Module\Director\Clicommands;

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
            $name = $this->getName();
            $host = IcingaHost::load($host, $this->db());
            $service = ServiceFinder::find($host, $name);
            if ($service->requiresOverrides()) {
                $this->params->shift('host');
                if ($this->params->shift('replace')) {
                    throw new RuntimeException('--replace is not available for Variable Overrides');
                }
                $appends = $this->stripPrefixedProperties($this->params, 'append-');
                $remove = $this->stripPrefixedProperties($this->params, 'remove-');
                $properties = $this->remainingParams();
                self::assertVarsForOverrides($appends);
                self::assertVarsForOverrides($remove);
                self::assertVarsForOverrides($properties);
                $current = $host->getOverriddenServiceVars($name);
                foreach ($properties as $key => $value) {
                    if ($key === 'vars') {
                        foreach ($value as $k => $v) {
                            $current->$k = $v;
                        }
                    } else {
                        $current->{substr($key, 5)} = $value;
                    }
                }

                if (! empty($appends)) {
                    throw new RuntimeException('--append- is not available for Variable Overrides');
                }
                if (! empty($remove)) {
                    throw new RuntimeException('--remove- is not available for Variable Overrides');
                }
                // Alternative, untested:
                // $this->appendToArrayProperties($object, $appends);
                // $this->removeProperties($object, $remove);

                $host->overrideServiceVars($name, $current);
                $this->persistChanges($host, 'Host', $host->getObjectName() . " (Overrides for $name)", 'modified');
            }
        }

        parent::setAction();
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

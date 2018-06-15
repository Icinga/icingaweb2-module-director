<?php

namespace Icinga\Module\Director\Objects;

use Icinga\Exception\ConfigurationError;
use Icinga\Module\Director\IcingaConfig\IcingaConfigHelper as c;

class IcingaScheduledDowntime extends IcingaObject
{
    protected $table = 'icinga_scheduled_downtime';

    protected $defaultProperties = [
        'id'                => null,
        'zone_id'           => null,
        'object_name'       => null,
        'object_type'       => null,
        'disabled'          => 'n',
        'display_name'      => null,
        'author'            => null,
        'comment'           => null,
        'fixed'             => null,
        'duration'          => null,
        'apply_to'          => null,
        'assign_filter'     => null,
    ];

    protected $supportsImports = true;

    protected $supportsRanges = true;

    protected $supportsApplyRules = true;

    protected $relations = [
        'zone' => 'IcingaZone',
    ];

    protected $booleans = [
        'fixed' => 'fixed',
    ];

    protected $intervalProperties = [
        'duration' => 'duration',
    ];

    /**
     * Render update property
     *
     * Avoid complaints for method names with underscore:
     * @codingStandardsIgnoreStart
     *
     * @return string
     */
    public function renderUpdate_method()
    {
        // @codingStandardsIgnoreEnd
        return '';
    }

    /**
     * @return string
     * @throws ConfigurationError
     */
    protected function renderObjectHeader()
    {
        if ($this->isApplyRule()) {
            if (($to = $this->get('apply_to')) === null) {
                throw new ConfigurationError(
                    'Applied notification "%s" has no valid object type',
                    $this->getObjectName()
                );
            }

            return sprintf(
                "%s %s %s to %s {\n",
                $this->getObjectTypeName(),
                $this->getType(),
                c::renderString($this->getObjectName()),
                ucfirst($to)
            );
        } else {
            return parent::renderObjectHeader();
        }
    }

    public function isActive($now = null)
    {
        if ($now === null) {
            $now = time();
        }

        foreach ($this->ranges()->getRanges() as $range) {
            if ($range->isActive($now)) {
                return true;
            }
        }

        // TODO: no range currently means (and renders) "never", Icinga behaves
        //       different. Figure out whether and how we should support this
        return false;
    }

    protected function prefersGlobalZone()
    {
        return true;
    }
}

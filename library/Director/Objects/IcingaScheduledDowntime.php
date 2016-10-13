<?php

namespace Icinga\Module\Director\Objects;

use Icinga\Module\Director\IcingaConfig\IcingaConfigHelper as c;

class IcingaScheduledDowntime extends IcingaObject
{
    protected $table = 'icinga_scheduled_downtime';

    protected $defaultProperties = array(
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
    );

    protected $supportsImports = true;

    protected $supportsRanges = true;

    protected $relations = array(
        'zone' => 'IcingaZone',
    );

    protected $booleans = array(
        'fixed' => 'fixed',
    );

    protected $intervalProperties = array(
        'duration' => 'duration',
    );

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

    protected function renderObjectHeader()
    {
        return parent::renderObjectHeader()
            . '    import "legacy-timeperiod"' . "\n";
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

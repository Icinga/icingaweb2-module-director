<?php

namespace Icinga\Module\Director\Objects;

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

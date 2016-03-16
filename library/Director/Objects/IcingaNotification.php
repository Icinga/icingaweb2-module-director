<?php

namespace Icinga\Module\Director\Objects;

use Icinga\Module\Director\IcingaConfig\IcingaConfigHelper as c;

class IcingaNotification extends IcingaObject
{
    protected $table = 'icinga_notification';

    protected $defaultProperties = array(
        'id'                    => null,
        'object_name'           => null,
        'object_type'           => null,
        'disabled'              => 'n',
        'host_id'               => null,
        'service_id'            => null,
        // 'users'                 => null,
        // 'user_groups'           => null,
        'times_begin'           => null,
        'times_end'             => null,
        'command_id'            => null,
        'notification_interval' => null,
        'period_id'             => null,
        'zone_id'               => null,
    );

    protected $supportsCustomVars = true;

    protected $supportsImports = true;

    protected $supportsApplyRules = true;

    protected $relatedSets = array(
        'states' => 'StateFilterSet',
        'types'  => 'TypeFilterSet',
    );

    protected $multiRelations = array(
        'users'       => 'IcingaUser',
        'user_groups' => 'IcingaUserGroup',
    );

    protected $relations = array(
        'zone'    => 'IcingaZone',
        'host'    => 'IcingaHost',
        'service' => 'IcingaService',
        'command' => 'IcingaCommand',
        'period'  => 'IcingaTimePeriod',
    );

    protected $intervalProperties = array(
        'notification_interval' => 'notification_interval',
        'times_begin'           => 'times_begin',
        'times_end'             => 'times_end',
    );

    /**
     * We have distinct properties in the db
     *
     * ...but render times only once
     *
     * And we skip warnings about underscores in method names:
     * @codingStandardsIgnoreStart
     *
     * @return string
     */
    protected function renderTimes_begin()
    {
        // @codingStandardsIgnoreEnd
        $times = (object) array(
            'begin' => c::renderInterval($this->times_begin)
        );

        if ($this->times_end !== null) {
            $times->end = c::renderInterval($this->times_end);
        }

        return c::renderKeyValue(
            'times',
            c::renderDictionary($times)
        );
    }

    /**
     * We have distinct properties in the db
     *
     * ...but render times only once
     *
     * And we skip warnings about underscores in method names:
     * @codingStandardsIgnoreStart
     *
     * @return string
     */
    protected function renderTimes_end()
    {
        // @codingStandardsIgnoreEnd

        if ($this->times_begin !== null) {
            return '';
        }

        $times = (object) array(
            'end' => c::renderInterval($this->times_end)
        );

        return c::renderKeyValue(
            'times',
            c::renderDictionary($times)
        );
    }

    protected function setKey($key)
    {
        if (is_int($key)) {
            $this->id = $key;
        } elseif (is_array($key)) {
            foreach (array('id', 'host_id', 'service_id', 'object_name') as $k) {
                if (array_key_exists($k, $key)) {
                    $this->set($k, $key[$k]);
                }
            }
        } else {
            return parent::setKey($key);
        }

        return $this;
    }
}

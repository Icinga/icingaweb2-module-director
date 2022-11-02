<?php

namespace Icinga\Module\Director\Objects;

use Icinga\Module\Director\Db;
use Icinga\Module\Director\DirectorObject\Automation\ExportInterface;
use Icinga\Module\Director\Exception\DuplicateKeyException;
use Icinga\Module\Director\IcingaConfig\IcingaConfigHelper as c;
use RuntimeException;

class IcingaNotification extends IcingaObject implements ExportInterface
{
    protected $table = 'icinga_notification';

    protected $defaultProperties = [
        'id'                    => null,
        'uuid'                  => null,
        'object_name'           => null,
        'object_type'           => null,
        'disabled'              => 'n',
        'apply_to'              => null,
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
        'assign_filter'         => null,
    ];

    protected $uuidColumn = 'uuid';

    protected $supportsCustomVars = true;

    protected $supportsFields = true;

    protected $supportsImports = true;

    protected $supportsApplyRules = true;

    protected $relatedSets = [
        'states' => 'StateFilterSet',
        'types'  => 'TypeFilterSet',
    ];

    protected $multiRelations = [
        'users'       => 'IcingaUser',
        'user_groups' => 'IcingaUserGroup',
    ];

    protected $relations = [
        'zone'    => 'IcingaZone',
        'host'    => 'IcingaHost',
        'service' => 'IcingaService',
        'command' => 'IcingaCommand',
        'period'  => 'IcingaTimePeriod',
    ];

    protected $intervalProperties = [
        'notification_interval' => 'interval',
        'times_begin'           => 'times_begin',
        'times_end'             => 'times_end',
    ];

    protected function prefersGlobalZone()
    {
        return false;
    }

    /**
     * @codingStandardsIgnoreStart
     * @return string
     */
    protected function renderTimes_begin()
    {
        // @codingStandardsIgnoreEnd
        return c::renderKeyValue('times.begin', c::renderInterval($this->get('times_begin')));
    }

    /**
     * @codingStandardsIgnoreStart
     * @return string
     */
    protected function renderTimes_end()
    {
        // @codingStandardsIgnoreEnd
        return c::renderKeyValue('times.end', c::renderInterval($this->get('times_end')));
    }

    public function getUniqueIdentifier()
    {
        return $this->getObjectName();
    }

    /**
     * @return \stdClass
     * @deprecated please use \Icinga\Module\Director\Data\Exporter
     * @throws \Icinga\Exception\NotFoundError
     */
    public function export()
    {
        // TODO: ksort in toPlainObject?
        $props = (array) $this->toPlainObject();
        $props['fields'] = $this->loadFieldReferences();
        ksort($props);

        return (object) $props;
    }

    /**
     * @param $plain
     * @param Db $db
     * @param bool $replace
     * @return static
     * @throws DuplicateKeyException
     * @throws \Icinga\Exception\NotFoundError
     */
    public static function import($plain, Db $db, $replace = false)
    {
        $properties = (array) $plain;
        $name = $properties['object_name'];
        $key = $name;

        if ($replace && static::exists($key, $db)) {
            $object = static::load($key, $db);
        } elseif (static::exists($key, $db)) {
            throw new DuplicateKeyException(
                'Notification "%s" already exists',
                $name
            );
        } else {
            $object = static::create([], $db);
        }

        // $object->newFields = $properties['fields'];
        unset($properties['fields']);
        $object->setProperties($properties);

        return $object;
    }

    /**
     * @deprecated please use \Icinga\Module\Director\Data\FieldReferenceLoader
     * @return array
     */
    protected function loadFieldReferences()
    {
        $db = $this->getDb();

        $res = $db->fetchAll(
            $db->select()->from([
                'nf' => 'icinga_notification_field'
            ], [
                'nf.datafield_id',
                'nf.is_required',
                'nf.var_filter',
            ])->join(['df' => 'director_datafield'], 'df.id = nf.datafield_id', [])
                ->where('notification_id = ?', $this->get('id'))
                ->order('varname ASC')
        );

        if (empty($res)) {
            return [];
        } else {
            foreach ($res as $field) {
                $field->datafield_id = (int) $field->datafield_id;
            }
            return $res;
        }
    }

    /**
     * Do not render internal property apply_to
     *
     * Avoid complaints for method names with underscore:
     * @codingStandardsIgnoreStart
     *
     * @return string
     */
    public function renderApply_to()
    {
        // @codingStandardsIgnoreEnd
        return '';
    }

    protected function renderObjectHeader()
    {
        if ($this->isApplyRule()) {
            if (($to = $this->get('apply_to')) === null) {
                throw new RuntimeException(sprintf(
                    'No "apply_to" object type has been set for Applied notification "%s"',
                    $this->getObjectName()
                ));
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

    /**
     * Render host_id as host_name
     *
     * Avoid complaints for method names with underscore:
     * @codingStandardsIgnoreStart
     *
     * @return string
     */
    public function renderHost_id()
    {
        // @codingStandardsIgnoreEnd
        return $this->renderRelationProperty('host', $this->get('host_id'), 'host_name');
    }

    /**
     * Render service_id as service_name
     *
     * @codingStandardsIgnoreStart
     * @return string
     */
    public function renderService_id()
    {
        // @codingStandardsIgnoreEnd
        return $this->renderRelationProperty('service', $this->get('service_id'), 'service_name');
    }

    protected function setKey($key)
    {
        if (is_int($key)) {
            $this->id = $key;
        } elseif (is_array($key)) {
            foreach (['id', 'host_id', 'service_id', 'object_name'] as $k) {
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

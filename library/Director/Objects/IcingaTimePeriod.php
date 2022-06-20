<?php

namespace Icinga\Module\Director\Objects;

use Icinga\Module\Director\Db;
use Icinga\Module\Director\DirectorObject\Automation\ExportInterface;
use Icinga\Module\Director\Exception\DuplicateKeyException;

class IcingaTimePeriod extends IcingaObject implements ExportInterface
{
    protected $table = 'icinga_timeperiod';

    protected $uuidColumn = 'uuid';

    protected $defaultProperties = [
        'id'                => null,
        'uuid'              => null,
        'zone_id'           => null,
        'object_name'       => null,
        'object_type'       => null,
        'disabled'          => 'n',
        'prefer_includes'   => null,
        'display_name'      => null,
        'update_method'     => null,
    ];

    protected $booleans = [
        'prefer_includes'  => 'prefer_includes',
    ];

    protected $supportsImports = true;

    protected $supportsRanges = true;

    protected $supportedInLegacy = true;

    protected $relations = array(
        'zone' => 'IcingaZone',
    );

    protected $multiRelations = [
        'includes' => [
            'relatedObjectClass' => 'IcingaTimeperiod',
            'relatedShortName'   => 'include',
        ],
        'excludes' => [
            'relatedObjectClass' => 'IcingaTimeperiod',
            'relatedShortName'   => 'exclude',
            'legacyPropertyName' => 'exclude'
        ],
    ];

    public function getUniqueIdentifier()
    {
        return $this->getObjectName();
    }

    /**
     * @deprecated please use \Icinga\Module\Director\Data\Exporter
     * @return object
     * @throws \Icinga\Exception\NotFoundError
     */
    public function export()
    {
        $props = (array) $this->toPlainObject();
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
                'Time Period "%s" already exists',
                $name
            );
        } else {
            $object = static::create([], $db);
        }
        $object->setProperties($properties);

        return $object;
    }

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

    protected function checkPeriodInRange($now, $name = null)
    {
        if ($name !== null) {
            $period = static::load($name, $this->connection);
        } else {
            $period = $this;
        }

        foreach ($period->ranges()->getRanges() as $range) {
            if ($range->isActive($now)) {
                return true;
            }
        }

        return false;
    }

    public function isActive($now = null)
    {
        if ($now === null) {
            $now = time();
        }

        $preferIncludes = $this->get('prefer_includes') !== 'n';

        $active = $this->checkPeriodInRange($now);
        $included = false;
        $excluded = false;

        $variants = [
            'includes' => &$included,
            'excludes' => &$excluded
        ];

        foreach ($variants as $key => &$var) {
            foreach ($this->get($key) as $name) {
                if ($this->checkPeriodInRange($now, $name)) {
                    $var = true;
                    break;
                }
            }
        }

        if ($preferIncludes) {
            if ($included) {
                return true;
            } elseif ($excluded) {
                return false;
            } else {
                return $active;
            }
        } else {
            if ($excluded) {
                return false;
            } elseif ($included) {
                return true;
            } else {
                return $active;
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

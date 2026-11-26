<?php

// SPDX-FileCopyrightText: 2026 Icinga GmbH <https://icinga.com>
// SPDX-License-Identifier: GPL-3.0-or-later

namespace Icinga\Module\Director\CustomVariable;

use gipfl\ZfDb\Adapter\Adapter;
use Icinga\Module\Director\Db\DbUtil;
use InvalidArgumentException;
use Zend_Db_Adapter_Abstract;

/**
 * The "director-object" custom variable type, a reference to other Director objects
 *
 * The new counterpart of the legacy DirectorObject Data Field. A property of this type
 * holds the name of one object, or a list of names, of a single object type. The object
 * type sits in director_property_object, the same way a datalist property keeps its list
 * in director_property_datalist. Single value vs. list is decided by the item type child
 * ('string' or 'dynamic-array'), exactly like a datalist does it.
 *
 * Values are always strict: only names of existing objects of that type are accepted.
 */
class DirectorObjectReference
{
    /** The value_type of a property referencing Director objects */
    public const VALUE_TYPE = 'director-object';

    /** The table linking such a property to the object type it references */
    public const TABLE = 'director_property_object';

    /** Object types that can be referenced, same choice as the legacy Data Field offers */
    private const OBJECT_TYPES = [
        'host',
        'hostgroup',
        'notification',
        'service',
        'servicegroup',
        'service_set',
        'user',
        'usergroup',
    ];

    /**
     * Get the object types that can be referenced, with a label for each
     *
     * @return array<string, string>
     */
    public static function enumObjectTypes(): array
    {
        return [
            'host'         => mt('director', 'Hosts'),
            'hostgroup'    => mt('director', 'Host groups'),
            'notification' => mt('director', 'Notification Apply Rules'),
            'service'      => mt('director', 'Services'),
            'servicegroup' => mt('director', 'Service groups'),
            'service_set'  => mt('director', 'Service Set'),
            'user'         => mt('director', 'Users'),
            'usergroup'    => mt('director', 'User groups'),
        ];
    }

    /**
     * Whether the given object type can be referenced
     *
     * @param mixed $objectType
     *
     * @return bool
     */
    public static function isValidObjectType($objectType): bool
    {
        return in_array($objectType, self::OBJECT_TYPES, true);
    }

    /**
     * Get the object type the given property references
     *
     * @param Zend_Db_Adapter_Abstract|Adapter $db
     * @param string $propertyUuid Raw binary UUID of the property
     *
     * @return ?string null if the property has no object type linked
     */
    public static function fetchObjectType($db, string $propertyUuid): ?string
    {
        $objectType = $db->fetchOne(
            $db->select()
                ->from(self::TABLE, ['object_type'])
                ->where('property_uuid = ?', DbUtil::quoteBinaryCompat($propertyUuid, $db))
        );

        return $objectType === false ? null : $objectType;
    }

    /**
     * Link the given property to the given object type, replacing any existing link
     *
     * @param Zend_Db_Adapter_Abstract|Adapter $db
     * @param string $propertyUuid Raw binary UUID of the property
     * @param string $objectType
     *
     * @return void
     */
    public static function link($db, string $propertyUuid, string $objectType): void
    {
        self::assertValidObjectType($objectType);

        $quotedUuid = DbUtil::quoteBinaryCompat($propertyUuid, $db);
        $db->delete(self::TABLE, $db->quoteInto('property_uuid = ?', $quotedUuid));
        $db->insert(self::TABLE, [
            'property_uuid' => $quotedUuid,
            'object_type'   => $objectType,
        ]);
    }

    /**
     * Remove the object type link of the given properties
     *
     * @param Zend_Db_Adapter_Abstract|Adapter $db
     * @param string[] $propertyUuids Raw binary UUIDs of the properties
     *
     * @return void
     */
    public static function unlink($db, array $propertyUuids): void
    {
        if (empty($propertyUuids)) {
            return;
        }

        $db->delete(
            self::TABLE,
            $db->quoteInto('property_uuid IN (?)', DbUtil::quoteBinaryCompat($propertyUuids, $db))
        );
    }

    /**
     * Get the objects of the given type that can be referenced, name => display name
     *
     * Same choice the legacy DirectorObject Data Field offered: real objects only, no
     * templates. Service sets must not belong to a single host, notifications are apply
     * rules. Sorted by display name.
     *
     * @param Zend_Db_Adapter_Abstract|Adapter $db
     * @param string $objectType
     *
     * @return array<string, string>
     */
    public static function enumObjects($db, string $objectType): array
    {
        self::assertValidObjectType($objectType);

        $display = in_array($objectType, ['service_set', 'notification'], true)
            ? 'object_name'
            : 'COALESCE(display_name, object_name)';

        $query = $db->select()
            ->from("icinga_$objectType", [
                'object_name'  => 'object_name',
                'display_name' => $display
            ])
            ->order($display);

        if ($objectType === 'service_set') {
            $query->where('host_id IS NULL');
        } elseif ($objectType === 'notification') {
            $query->where('object_type = ?', 'apply');
        } else {
            $query->where('object_type = ?', 'object');
        }

        return $db->fetchPairs($query);
    }

    /**
     * Get the objects the given property can reference, name => display name
     *
     * @param Zend_Db_Adapter_Abstract|Adapter $db
     * @param string $propertyUuid Raw binary UUID of the property
     *
     * @return array<string, string> Empty if the property has no object type linked
     */
    public static function enumObjectsForProperty($db, string $propertyUuid): array
    {
        $objectType = self::fetchObjectType($db, $propertyUuid);
        if ($objectType === null) {
            return [];
        }

        return self::enumObjects($db, $objectType);
    }

    /**
     * @param mixed $objectType
     *
     * @throws InvalidArgumentException
     */
    private static function assertValidObjectType($objectType): void
    {
        if (! self::isValidObjectType($objectType)) {
            throw new InvalidArgumentException(sprintf(
                "'%s' is not an object type a custom variable can reference",
                is_scalar($objectType) ? (string) $objectType : get_debug_type($objectType)
            ));
        }
    }
}

<?php

namespace Icinga\Module\Director\CustomVariable;

use Icinga\Module\Director\Data\Db\DbObjectTypeRegistry;
use Icinga\Module\Director\Db;
use Icinga\Module\Director\Objects\DirectorActivityLog;
use Icinga\Module\Director\Objects\IcingaObject;
use Icinga\Module\Director\Repository\IcingaTemplateRepository;
use stdClass;

/**
 * Cleans up leftover custom variable values once a property gets detached
 * from a template
 *
 * An object that imports a template can still keep its own saved value for
 * a variable it once inherited, even after overriding it locally. Detaching
 * the property from the template used to leave that saved value behind, so
 * it kept showing up and rendering even though it could no longer be
 * reached or edited through the usual property-attachment UI.
 */
class PropertyDetachmentCleaner
{
    /**
     * Wipe the saved value for the given properties off the object itself
     * and off every object underneath it that had stored its own copy
     *
     * Each object underneath that actually loses a value gets its own
     * activity log entry first, or that loss would happen with no trace of
     * why a value it never touched directly suddenly disappeared.
     *
     * @param string[] $quotedPropertyUuids Already quoted with DbUtil::quoteBinaryCompat()
     */
    public static function removeStaleValues(IcingaObject $object, array $quotedPropertyUuids, Db $db): void
    {
        if (empty($quotedPropertyUuids)) {
            return;
        }

        $type = $object->getShortTableName();
        $dbAdapter = $db->getDbAdapter();
        $objectId = (int) $object->get('id');
        $class = DbObjectTypeRegistry::classByType($type);

        $rows = $dbAdapter->fetchAll(
            $dbAdapter->select()
                ->from('icinga_' . $type . '_var')
                ->where('property_uuid IN (?)', $quotedPropertyUuids)
        );

        $removedNamesByHolderId = [];
        foreach ($rows as $row) {
            $holderId = (int) $row->{$type . '_id'};
            if ($holderId === $objectId) {
                continue;
            }

            $removedNamesByHolderId[$holderId][] = $row->varname;
        }

        $objectsToCleanUp = [$objectId];

        if (! empty($removedNamesByHolderId)) {
            // check the inheritance table directly first, no need to load an
            // object in full just to find out it isn't even a descendant
            $tree = IcingaTemplateRepository::instanceByType($type, $db)->tree();

            foreach ($removedNamesByHolderId as $holderId => $removedNames) {
                if (! array_key_exists($objectId, $tree->getAncestorsById($holderId))) {
                    continue;
                }

                $holder = $class::loadWithAutoIncId($holderId, $object->getConnection());
                $objectsToCleanUp[] = $holderId;

                $oldVars = self::plainVars($holder->vars());
                $newVars = clone $oldVars;
                foreach ($removedNames as $removedName) {
                    unset($newVars->$removedName);
                }

                DirectorActivityLog::logCustomVariableModification($holder, $oldVars, $newVars, $db);
            }
        }

        $propertyWhere = $dbAdapter->quoteInto('property_uuid IN (?)', $quotedPropertyUuids);
        $objectsWhere = $dbAdapter->quoteInto($type . '_id IN (?)', $objectsToCleanUp);
        $dbAdapter->delete('icinga_' . $type . '_var', $propertyWhere . ' AND ' . $objectsWhere);
    }

    /**
     * Turn a set of custom variables into a plain key/value object, the
     * same shape the activity log expects for its before/after snapshots
     *
     * @return stdClass
     */
    private static function plainVars(iterable $vars): stdClass
    {
        $plain = [];
        foreach ($vars as $key => $var) {
            if ($var->hasBeenDeleted()) {
                continue;
            }

            $plain[$key] = $var->getValue();
        }

        ksort($plain);

        return (object) $plain;
    }
}

<?php

namespace Icinga\Module\Director\RestApi;

use Icinga\Exception\NotFoundError;
use Icinga\Module\Director\CustomVariable\CustomVariables;
use Icinga\Module\Director\CustomVariable\CustomVariableValueValidator;
use Icinga\Module\Director\Db;
use Icinga\Module\Director\Db\DbUtil;
use Icinga\Module\Director\Objects\DirectorActivityLog;
use Icinga\Module\Director\Objects\IcingaObject;
use PDO;
use Ramsey\Uuid\Uuid;
use stdClass;
use Throwable;

/**
 * Applies a map of custom variable values submitted through the REST
 * API to an IcingaObject and persists the result
 */
class CustomVariableValueApplier
{
    public function __construct(private Db $db)
    {
    }

    /**
     * Apply the given custom variable overrides to the given object and store them
     *
     * When the method is PUT, every custom variable currently set directly on
     * the object is removed first and only the given overrides survive.
     * When replaceAll is set for a non PUT request, the same end result
     * is reached without touching rows that are not affected, which is
     * used for a POST carrying a full "vars" dictionary at the base
     * object endpoint.
     *
     * @return bool Whether anything was actually wiped or written. A PUT always
     *              counts as a change since it replaces the full set regardless
     *              of the end result. Otherwise this reflects CustomVariables'
     *              own modification tracking, checked before storeToDb() resets it.
     *
     * @throws NotFoundError
     */
    public function apply(CustomVarApplyRequest $request): bool
    {
        $object = $request->object;
        $dbAdapter = $this->db->getDbAdapter();
        $type = $object->getShortTableName();
        $objectVars = $object->vars();
        // save the original values now, once the new ones are written to the
        // database there is no way to recover what they used to be
        $oldVars = $this->plainVars($objectVars->getOriginalVars());
        $wipeValuesInDb = $request->method === 'PUT' && $object->get('id');
        // only templates allow attach/detach, concrete objects just replace values
        $wipePropertyAttachmentsInDb = $wipeValuesInDb
            && $request->actionName === 'variables'
            && $object->isTemplate();

        // If a caller already opened a transaction (e.g. IcingaObjectHandler wrapping
        // object persistence and this call together), let it own the commit/rollback.
        // A nested beginTransaction() call on the same PDO connection throws
        // "There is already an active transaction" instead of nesting.
        $manageTransaction = $wipeValuesInDb && ! $dbAdapter->getConnection()->inTransaction();

        if ($manageTransaction) {
            $dbAdapter->beginTransaction();
        }

        $preservedDirectAttachments = [];

        try {
            if ($wipeValuesInDb) {
                $objectWhere = $dbAdapter->quoteInto("{$type}_id = ?", $object->get('id'));
                $dbAdapter->delete('icinga_' . $type . '_var', $objectWhere);

                if ($wipePropertyAttachmentsInDb) {
                    // Snapshot direct attachments before wiping them below, or a property
                    // attached only here looks unattached once gone and gets rejected as new.
                    $preservedDirectAttachments = $this->getDirectPropertyAttachments($object);

                    $uuidExpr = DbUtil::quoteBinaryCompat(
                        DbUtil::binaryResult($object->get('uuid')),
                        $dbAdapter
                    );
                    $dbAdapter->delete(
                        'icinga_' . $type . '_property',
                        $dbAdapter->quoteInto("{$type}_uuid = ?", $uuidExpr)
                    );
                }

                $objectVars = new CustomVariables();
            } elseif ($request->replaceAll) {
                $obsoleteKeys = [];
                foreach ($objectVars as $key => $var) {
                    if (! array_key_exists($key, $request->overRiddenCustomVars)) {
                        $obsoleteKeys[] = $key;
                    }
                }

                foreach ($obsoleteKeys as $key) {
                    unset($objectVars->$key);
                }
            }

            $customProperties = $this->getObjectCustomProperties($object);

            foreach ($request->overRiddenCustomVars as $key => $value) {
                $this->applySingleVar(
                    $request,
                    $objectVars,
                    $customProperties,
                    $key,
                    $value,
                    $preservedDirectAttachments
                );
            }

            $hasChanged = $wipeValuesInDb || $objectVars->hasBeenModified();
            $newVars = $this->plainVars($objectVars);
            // the changed flag can say yes even when a value was just resubmitted
            // unchanged, so compare the real values instead of trusting that flag.
            // a full replace can also swap which attachment a key points to
            // while keeping the same value, so that always counts too
            if ($wipePropertyAttachmentsInDb || json_encode($oldVars) !== json_encode($newVars)) {
                // sensitive values are not masked here, same as the web form's
                // own save path, this is a known gap to close later
                DirectorActivityLog::logCustomVariableModification($object, $oldVars, $newVars, $this->db);
            }

            $objectVars->storeToDb($object);
        } catch (Throwable $e) {
            if ($manageTransaction) {
                $dbAdapter->rollBack();
            }

            throw $e;
        }

        if ($manageTransaction) {
            $dbAdapter->commit();
        }

        return $hasChanged;
    }

    /**
     * Apply a single key value override, attaching the underlying
     * director_property to a template on the fly when needed
     *
     * @return void
     *
     * @throws NotFoundError
     */
    private function applySingleVar(
        CustomVarApplyRequest $request,
        CustomVariables $objectVars,
        array $customProperties,
        string $key,
        mixed $value,
        array $preservedDirectAttachments = []
    ): void {
        $object = $request->object;
        $objectVars->set($key, $value);
        $var = $objectVars->get($key);
        if ($var === null) {
            // A null value for a variable that was never set is a no-op
            return;
        }

        $var->setModified();

        if (isset($customProperties[$key])) {
            CustomVariableValueValidator::assertMatchesType(
                $key,
                $value,
                $customProperties[$key]['value_type'],
                Uuid::fromBytes($customProperties[$key]['uuid']),
                $this->db
            );
            if ($customProperties[$key]['value_type'] === 'datalist-strict') {
                CustomVariableValueValidator::assertDatalistValueAllowed(
                    $key,
                    $value,
                    Uuid::fromBytes($customProperties[$key]['uuid']),
                    $this->db
                );
            }

            $objectVars->registerVarUuid($key, Uuid::fromBytes($customProperties[$key]['uuid']));

            return;
        }

        if ($request->actionName !== 'variables') {
            return;
        }

        // Attached here directly before this PUT wiped it, restore it rather than
        // rejecting this object for lacking a brand new attachment.
        if (isset($preservedDirectAttachments[$key])) {
            $this->reattachPreservedProperty($object, $key, $value, $preservedDirectAttachments[$key], $objectVars);

            return;
        }

        if (! $object->isTemplate()) {
            throw new NotFoundError(sprintf(
                'The custom variable %s should be first added to one of the imported templates for this object',
                $key
            ));
        }

        if ($request->method === 'POST') {
            throw new NotFoundError(sprintf(
                'The custom variable %s should be first added to the template',
                $key
            ));
        }

        $dbAdapter = $this->db->getDbAdapter();
        $query = $dbAdapter->select()
            ->from(['dp' => 'director_property'], ['uuid', 'value_type'])
            ->where('dp.key_name = ? AND dp.parent_uuid IS NULL', $key);
        $propertyRow = $dbAdapter->fetchRow($query, [], PDO::FETCH_ASSOC);

        if (! $propertyRow) {
            throw new NotFoundError(sprintf(
                "'%s' is not configured in Icinga Director as a custom variable",
                $key
            ));
        }

        $propertyRow = DbUtil::normalizeRow($propertyRow);
        $customPropertyUuid = $propertyRow['uuid'];

        CustomVariableValueValidator::assertMatchesType(
            $key,
            $value,
            $propertyRow['value_type'],
            Uuid::fromBytes($customPropertyUuid),
            $this->db
        );
        if ($propertyRow['value_type'] === 'datalist-strict') {
            CustomVariableValueValidator::assertDatalistValueAllowed(
                $key,
                $value,
                Uuid::fromBytes($customPropertyUuid),
                $this->db
            );
        }

        $type = $object->getShortTableName();
        $dbAdapter->insert('icinga_' . $type . '_property', [
            'property_uuid' => DbUtil::quoteBinaryCompat($customPropertyUuid, $dbAdapter),
            $type . '_uuid' => DbUtil::quoteBinaryCompat($object->get('uuid'), $dbAdapter),
            'required' => 'n'
        ]);

        $objectVars->registerVarUuid($key, Uuid::fromBytes($customPropertyUuid));
    }

    /**
     * Re-create a direct attachment wiped earlier in this request and store its value
     *
     * @param array{uuid: string, value_type: string, required: string} $attachment
     */
    private function reattachPreservedProperty(
        IcingaObject $object,
        string $key,
        mixed $value,
        array $attachment,
        CustomVariables $objectVars
    ): void {
        CustomVariableValueValidator::assertMatchesType(
            $key,
            $value,
            $attachment['value_type'],
            Uuid::fromBytes($attachment['uuid']),
            $this->db
        );
        if ($attachment['value_type'] === 'datalist-strict') {
            CustomVariableValueValidator::assertDatalistValueAllowed(
                $key,
                $value,
                Uuid::fromBytes($attachment['uuid']),
                $this->db
            );
        }

        $type = $object->getShortTableName();
        $dbAdapter = $this->db->getDbAdapter();
        $dbAdapter->insert('icinga_' . $type . '_property', [
            'property_uuid' => DbUtil::quoteBinaryCompat($attachment['uuid'], $dbAdapter),
            $type . '_uuid' => DbUtil::quoteBinaryCompat($object->get('uuid'), $dbAdapter),
            'required' => $attachment['required']
        ]);

        $objectVars->registerVarUuid($key, Uuid::fromBytes($attachment['uuid']));
    }

    /**
     * Get the properties attached directly to the object, keyed by key_name
     *
     * Tells "replacing an attached property" apart from "attaching a new one"
     * once a PUT has wiped this object's own attachment rows.
     *
     * @return array<string, array{uuid: string, value_type: string, required: string}>
     */
    private function getDirectPropertyAttachments(IcingaObject $object): array
    {
        if ($object->get('uuid') === null) {
            return [];
        }

        $type = $object->getShortTableName();
        $dbAdapter = $this->db->getDbAdapter();
        $uuidExpr = DbUtil::quoteBinaryCompat(
            DbUtil::binaryResult($object->get('uuid')),
            $dbAdapter
        );
        $query = $dbAdapter->select()
            ->from(['iop' => 'icinga_' . $type . '_property'], ['required' => 'iop.required'])
            ->join(['dp' => 'director_property'], 'dp.uuid = iop.property_uuid', [
                'key_name' => 'dp.key_name',
                'uuid' => 'dp.uuid',
                'value_type' => 'dp.value_type'
            ])
            ->where($dbAdapter->quoteInto("iop.{$type}_uuid = ?", $uuidExpr));

        $result = [];
        foreach ($dbAdapter->fetchAll($query, [], PDO::FETCH_ASSOC) as $row) {
            $result[$row['key_name']] = DbUtil::normalizeRow($row);
        }

        return $result;
    }

    /**
     * Get the custom properties linked to the given object, including
     * properties inherited from its ancestors
     *
     * @param IcingaObject $object
     *
     * @return array
     */
    private function getObjectCustomProperties(IcingaObject $object): array
    {
        if ($object->get('uuid') === null) {
            return [];
        }

        $type = $object->getShortTableName();
        $db = $object->getConnection();
        $ids = $object->listAncestorIds();
        $ids[] = $object->get('id');
        $query = $db->getDbAdapter()
            ->select()
            ->from(
                ['dp' => 'director_property'],
                [
                    'key_name' => 'dp.key_name',
                    'uuid' => 'dp.uuid',
                    'value_type' => 'dp.value_type',
                    'label' => 'dp.label'
                ]
            )
            ->join(['iop' => "icinga_$type" . '_property'], 'dp.uuid = iop.property_uuid', [])
            ->join(['io' => "icinga_$type"], 'io.uuid = iop.' . $type . '_uuid', [])
            ->where('io.id IN (?)', $ids)
            ->group(['dp.uuid', 'dp.key_name', 'dp.value_type', 'dp.label'])
            ->order('key_name');

        $result = [];
        foreach ($db->getDbAdapter()->fetchAll($query, [], PDO::FETCH_ASSOC) as $row) {
            $row = DbUtil::normalizeRow($row);
            $result[$row['key_name']] = $row;
        }

        return $result;
    }

    /**
     * Turn a set of custom variables, current or original, into a plain
     * key/value object, the same shape used for the activity log diff
     *
     * @param iterable $vars
     *
     * @return stdClass
     */
    private function plainVars(iterable $vars): stdClass
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

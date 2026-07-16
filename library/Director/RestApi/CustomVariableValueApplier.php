<?php

namespace Icinga\Module\Director\RestApi;

use Icinga\Exception\NotFoundError;
use Icinga\Module\Director\CustomVariable\CustomVariables;
use Icinga\Module\Director\CustomVariable\CustomVariableValueValidator;
use Icinga\Module\Director\Db;
use Icinga\Module\Director\Db\DbUtil;
use Icinga\Module\Director\Objects\IcingaObject;
use PDO;
use Ramsey\Uuid\Uuid;
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
     * When $replaceAll is set for a non PUT request, the same end result
     * is reached without touching rows that are not affected, which is
     * used for a POST carrying a full "vars" dictionary at the base
     * object endpoint.
     *
     * @param IcingaObject $object
     * @param array $overRiddenCustomVars 2-dimensional array of key => value
     * @param string $actionName Endpoint name
     * @param string $method POST or PUT
     * @param bool $replaceAll
     *
     * @throws NotFoundError
     */
    public function apply(
        IcingaObject $object,
        array $overRiddenCustomVars,
        string $actionName,
        string $method,
        bool $replaceAll
    ): void {
        $dbAdapter = $this->db->getDbAdapter();
        $type = $object->getShortTableName();
        $objectVars = $object->vars();
        $wipeValuesInDb = $method === 'PUT' && $object->get('id');
        // Full replacement of the attachment/required link is documented and tested
        // behavior only for the dedicated "variables" endpoint; a PUT on the base
        // object endpoint must still fully replace values but must not detach
        // properties that were not part of this request.
        $wipePropertyAttachmentsInDb = $wipeValuesInDb && $actionName === 'variables';

        // If a caller already opened a transaction (e.g. IcingaObjectHandler wrapping
        // object persistence and this call together), let it own the commit/rollback.
        // A nested beginTransaction() call on the same PDO connection throws
        // "There is already an active transaction" instead of nesting.
        $manageTransaction = $wipeValuesInDb && ! $dbAdapter->getConnection()->inTransaction();

        if ($manageTransaction) {
            $dbAdapter->beginTransaction();
        }

        try {
            if ($wipeValuesInDb) {
                $objectWhere = $dbAdapter->quoteInto("{$type}_id = ?", $object->get('id'));
                $dbAdapter->delete('icinga_' . $type . '_var', $objectWhere);

                if ($wipePropertyAttachmentsInDb) {
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
            } elseif ($replaceAll) {
                $obsoleteKeys = [];
                foreach ($objectVars as $key => $var) {
                    if (! array_key_exists($key, $overRiddenCustomVars)) {
                        $obsoleteKeys[] = $key;
                    }
                }

                foreach ($obsoleteKeys as $key) {
                    unset($objectVars->$key);
                }
            }

            $customProperties = $this->getObjectCustomProperties($object);

            foreach ($overRiddenCustomVars as $key => $value) {
                $this->applySingleVar(
                    $object,
                    $objectVars,
                    $customProperties,
                    $key,
                    $value,
                    $actionName,
                    $method,
                    $type
                );
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
        IcingaObject $object,
        CustomVariables $objectVars,
        array $customProperties,
        string $key,
        mixed $value,
        string $actionName,
        string $method,
        string $type
    ): void {
        $objectVars->set($key, $value);
        $var = $objectVars->get($key);
        if ($var === null) {
            // A null value for a variable that was never set is a no-op
            return;
        }

        $var->setModified();

        if (isset($customProperties[$key])) {
            CustomVariableValueValidator::assertMatchesType($key, $value, $customProperties[$key]['value_type']);
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

        if ($actionName !== 'variables') {
            return;
        }

        if (! $object->isTemplate()) {
            throw new NotFoundError(sprintf(
                'The custom variable %s should be first added to one of the imported templates for this object',
                $key
            ));
        }

        if ($method === 'POST') {
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

        CustomVariableValueValidator::assertMatchesType($key, $value, $propertyRow['value_type']);
        if ($propertyRow['value_type'] === 'datalist-strict') {
            CustomVariableValueValidator::assertDatalistValueAllowed(
                $key,
                $value,
                Uuid::fromBytes($customPropertyUuid),
                $this->db
            );
        }

        $dbAdapter->insert('icinga_' . $type . '_property', [
            'property_uuid' => DbUtil::quoteBinaryCompat($customPropertyUuid, $dbAdapter),
            $type . '_uuid' => DbUtil::quoteBinaryCompat($object->get('uuid'), $dbAdapter)
        ]);

        $objectVars->registerVarUuid($key, Uuid::fromBytes($customPropertyUuid));
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
}

<?php

namespace Icinga\Module\Director\Forms;

use Icinga\Module\Director\Data\Db\DbObjectTypeRegistry;
use Icinga\Module\Director\Db\DbUtil;
use Icinga\Module\Director\Forms\DictionaryElements\Dictionary;
use Icinga\Module\Director\Forms\DictionaryElements\DictionaryItem;
use Icinga\Module\Director\Objects\DirectorActivityLog;
use Icinga\Module\Director\Objects\IcingaHost;
use Icinga\Module\Director\Objects\IcingaObject;
use Icinga\Module\Director\Objects\IcingaService;
use Icinga\Module\Director\Objects\IcingaServiceSet;
use Icinga\Security\SecurityException;
use Icinga\Web\Session;
use ipl\Html\Attributes;
use ipl\Html\BaseHtmlElement;
use ipl\Html\HtmlElement;
use ipl\Html\Text;
use ipl\I18n\Translation;
use ipl\Web\Common\CsrfCounterMeasure;
use ipl\Web\Compat\CompatForm;
use LogicException;
use Ramsey\Uuid\Uuid;

class CustomVariablesForm extends CompatForm
{
    use CsrfCounterMeasure;
    use Translation;

    /** @var IcingaService|null Applied service for which the custom variables are being used */
    private ?IcingaService $applyGenerated = null;

    /** @var string|null Service from which the custom variables are being inherited from. */
    private ?string $inheritedServiceFrom = null;

    /** @var IcingaServiceSet|null Service set for which the custom variables are being used. */
    private ?IcingaServiceSet $set = null;

    /** @var IcingaHost|null Host for which the custom variables are being used. */
    private ?IcingaHost $host = null;

    /** @var bool Whether the custom variables have been modified */
    private bool $varsHasBeenModified = false;

    /** @var array UUIDs of custom variables that have been added */
    private array $addedVarUuids = [];

    /** @var array UUIDs of custom variables that have been marked required */
    private array $requiredVarUuids = [];

    /** @var bool Whether the current principal has director/admin */
    private bool $isAdmin = false;

    public function __construct(
        public readonly IcingaObject $object,
        protected array $objectProperties = []
    ) {
        $this->addAttributes(Attributes::create(['class' => 'custom-variables-form']));
    }

    /**
     * Check if the custom properties have been modified
     *
     * @return bool
     */
    public function varsHasBeenModified(): bool
    {
        return $this->varsHasBeenModified;
    }

    /**
     * Set the custom variable Uuid strings that were newly added to the form
     *
     * @param array $uuids
     *
     * @return $this
     */
    public function setAddedVarUuids(array $uuids): static
    {
        $this->addedVarUuids = $uuids;

        return $this;
    }

    /**
     * Set the custom variable Uuid strings that were marked required this session
     *
     * @param array $uuids
     *
     * @return $this
     */
    public function setRequiredVarUuids(array $uuids): static
    {
        $this->requiredVarUuids = $uuids;

        return $this;
    }

    /**
     * Set whether the current principal has director/admin
     *
     * @param bool $isAdmin
     *
     * @return $this
     */
    public function setIsAdmin(bool $isAdmin): static
    {
        $this->isAdmin = $isAdmin;

        return $this;
    }

    protected function assemble(): void
    {
        $this->addCsrfCounterMeasure(Session::getSession()->getId());

        $properties = $this->objectProperties;
        if ($this->object->isTemplate()) {
            // Templates define the schema, they don't fill it in, so required never applies here.
            // required_current keeps the real flag around so the row can still show it as a toggle.
            foreach ($properties as &$property) {
                $property['required_current'] = $property['required'] ?? false;
                $property['required'] = false;
            }

            unset($property);
        }

        $dictionary = (new Dictionary(
            'properties',
            $properties,
            ['class' => 'no-border']
        ))->setAllowItemRemoval($this->object->isTemplate());

        $saveButton = $this->createElement('submit', 'save', [
            'label' => $this->isOverrideServiceVars()
                ? $this->translate('Override Custom Variables')
                : $this->translate('Save Custom Variables')
        ]);

        $addedUuidsContainer = new HtmlElement(
            'div',
            Attributes::create(['id' => 'added-var-uuids', 'class' => 'added-var-uuids', 'tabindex' => -1])
        );

        $addedUuidsElement = $this->createElement(
            'hidden',
            'addedVarUuids',
            [
                'value' => implode(',', $this->addedVarUuids)
            ]
        );

        $this->registerElement($addedUuidsElement);
        $addedUuidsContainer->addHtml($addedUuidsElement);

        $requiredUuidsContainer = new HtmlElement(
            'div',
            Attributes::create(['id' => 'required-var-uuids', 'class' => 'required-var-uuids', 'tabindex' => -1])
        );

        $requiredUuidsElement = $this->createElement(
            'hidden',
            'requiredVarUuids',
            [
                'value' => implode(',', $this->requiredVarUuids)
            ]
        );

        $this->registerElement($requiredUuidsElement);
        $requiredUuidsContainer->addHtml($requiredUuidsElement);

        $this->addElement($this->duplicateSubmitButton($saveButton));
        $this->addElement($dictionary);
        if ($this->hasBeenSent()) {
            $dictionary->ensureAssembled();
        }

        $this->addHtml($addedUuidsContainer);
        $this->addHtml($requiredUuidsContainer);
        $this->registerElement($saveButton);

        $removedItems = $dictionary->getItemsToRemove();
        $removedUuids = [];
        foreach ($removedItems as $removedItem) {
            $removedUuids[] = Uuid::fromBytes($this->objectProperties[$removedItem]['uuid'])->toString();
        }

        $removedUuids = array_diff($removedUuids, $this->addedVarUuids);

        if (! empty($removedUuids)) {
            $this->addHtml(
                new HtmlElement('div', Attributes::create(['class' => 'message']), Text::create(
                    sprintf(
                        $this->translatePlural(
                            '(%d) property has been removed',
                            '(%d) properties have been removed',
                            count($removedUuids)
                        ),
                        count($removedUuids)
                    )
                ))
            );
        }

        $this->addElement($saveButton);
    }

    /**
     * Set the applied rule from where the custom variables are inherited from
     *
     * @param IcingaService $applyGenerated
     *
     * @return $this
     */
    public function setApplyGenerated(IcingaService $applyGenerated): static
    {
        $this->applyGenerated = $applyGenerated;

        return $this;
    }

    public function setInheritedServiceFrom(string $hostname): static
    {
        $this->inheritedServiceFrom = $hostname;

        return $this;
    }

    /**
     * Set the service set from where the custom variables are inherited from
     *
     * @param IcingaServiceSet $set
     *
     * @return $this
     */
    public function setServiceSet(IcingaServiceSet $set): static
    {
        $this->set = $set;

        return $this;
    }

    /**
     * Set host if the object is a service
     *
     * @param IcingaHost $host
     *
     * @return $this
     */
    public function setHostForService(IcingaHost $host): static
    {
        $this->host = $host;

        return $this;
    }

    /**
     * Are the populated values for custom properties a part of _override_servicevars
     *
     * @return bool
     */
    public function isOverrideServiceVars(): bool
    {
        return $this->applyGenerated
            || $this->inheritedServiceFrom
            || ($this->host && $this->set);
    }

    /**
     * Assert that a host has been set whenever an override is requested
     *
     * @return void
     *
     * @throws LogicException
     */
    private function assertOverrideHostIsSet(): void
    {
        if ($this->isOverrideServiceVars() && $this->host === null) {
            throw new LogicException(
                'CustomVariablesForm needs setHostForService() to be called before overriding service variables'
            );
        }
    }

    public function hasBeenSubmitted(): bool
    {
        $pressedButton = $this->getPressedSubmitElement();

        if ($pressedButton && $pressedButton->getName() === 'save') {
            return true;
        }

        return false;
    }

    /**
     * Load form with object properties
     *
     * @param array $objectProperties
     *
     * @return void
     */
    public function load(array $objectProperties): void
    {
        $this->populate([
            'properties' => Dictionary::prepare($objectProperties)
        ]);
    }

    /**
     * Build a standalone DictionaryItem row for use in a multipart update.
     *
     * @param array $propertyData  Row data as returned by getObjectCustomProperties()
     * @param int   $index         The slot index this item occupies
     *
     * @return BaseHtmlElement
     */
    public function prepareNewPropertyRow(array $propertyData, int $index): BaseHtmlElement
    {
        $this->ensureAssembled();
        /** @var Dictionary $dictionary */
        $dictionary = $this->getElement('properties');

        if ($this->object->isTemplate()) {
            // Only reachable from a template's own tab, which never enforces required.
            $propertyData['required_current'] = $propertyData['required'] ?? false;
            $propertyData['required'] = false;
        }

        if ($propertyData['allow_removal']) {
            $removeButton = $dictionary->createElement('submitButton', 'remove_' . $index, [
                'label' => 'Remove',
                'class' => ['remove-property'],
                'formnovalidate' => true
            ]);
            $dictionary->registerElement($removeButton);
        } else {
            $removeButton = null;
        }

        $propertyData['uuid'] = DbUtil::binaryResult($propertyData['uuid']);
        $newItem = new DictionaryItem((string) $index, $propertyData);

        $this->decorate($newItem);
        if ($removeButton !== null) {
            $newItem->setRemoveButton($removeButton);
        }

        $dictionary->registerElement($newItem);

        $newItem->populate(DictionaryItem::prepare($propertyData));

        return $newItem;
    }

    /**
     * Filter empty values from array
     *
     * @param array $array
     *
     * @return array
     */
    public static function filterEmpty(array $array): array
    {
        // Lists (sequential int keys) are positional, e.g., a fixed-array's own value. So we
        // never drop individual elements there, only decide to keep the list as-is or drop it
        // entirely if everything in it is empty. Otherwise, element removal would shift later items
        // into earlier slots. This holds at any nesting depth, not just the outermost array.
        if (array_is_list($array)) {
            foreach ($array as $item) {
                $checkedItem = is_array($item) ? self::filterEmpty($item) : $item;
                if (! self::isValueUnset($checkedItem)) {
                    return $array;
                }
            }

            return [];
        }

        return array_filter(
            array_map(function ($item) {
                if (! is_array($item)) {
                    return $item;
                }

                return self::filterEmpty($item);
            }, $array),
            function ($item) {
                return ! self::isValueUnset($item);
            }
        );
    }

    /**
     * Assert that a new custom variable may be attached to $this->object
     *
     * Custom variables are only meant to be attached directly to a
     * template, and needs director/admin.
     *
     * @return void
     *
     * @throws LogicException
     * @throws SecurityException
     */
    private function assertCanAttachNewVariable(): void
    {
        if (! $this->object->isTemplate()) {
            throw new LogicException(sprintf(
                'Custom Variables can only be attached directly to a template, got %s',
                $this->object->getObjectName()
            ));
        }

        if (! $this->isAdmin) {
            throw new SecurityException('Attaching a new custom variable requires the director/admin permission');
        }
    }

    /**
     * Assert that the required flag of a property attachment may be changed on $this->object
     *
     * The required toggle is only ever rendered for a template's own directly
     * attached properties, this is a defensive backstop for that same rule.
     *
     * @return void
     *
     * @throws LogicException
     */
    private function assertCanEditRequiredFlag(): void
    {
        if (! $this->object->isTemplate()) {
            throw new LogicException(sprintf(
                'The required flag can only be changed on the template it was set on, got %s',
                $this->object->getObjectName()
            ));
        }
    }

    /**
     * Whether the given value should be treated as unset
     *
     * @param mixed $value
     *
     * @return bool
     */
    public static function isValueUnset(mixed $value): bool
    {
        if (is_bool($value) || $value === 0 || $value === 0.0 || $value === '0') {
            return false;
        }

        return empty($value);
    }

    protected function onSuccess(): void
    {
        $this->assertOverrideHostIsSet();

        // The property (re)attachments below, the vars removal and the final
        // store() are multiple separate writes that only make sense together;
        // a failure partway through must not leave them half-applied.
        $this->object->getConnection()->runFailSafeTransaction(function () {
            $this->persistPropertyChanges();
        });
    }

    /**
     * Apply the submitted property values and attachments and persist them
     *
     * @return void
     */
    private function persistPropertyChanges(): void
    {
        $vars = $this->object->vars();

        /** @var Dictionary $propertiesElement */
        $propertiesElement = $this->getElement('properties');
        $values = $propertiesElement->getDictionary();
        $requiredFlags = $propertiesElement->getRequiredFlags();
        $itemsToRemove = $propertiesElement->getItemsToRemove();
        $type = $this->object->getShortTableName();
        $db = $this->object->getDb();
        $itemsToRemoveUuids = [];
        $overrideVars = [] ;
        $isOverrideServiceVars = $this->isOverrideServiceVars();
        if ($isOverrideServiceVars) {
            $overrideVars = (array) $this->host->getOverriddenServiceVars($this->object->getObjectName());
        }

        foreach ($this->objectProperties as $key => $property) {
            $propertyUuid = Uuid::fromBytes($property['uuid']);
            if (isset($property['removed'])) {
                $itemsToRemoveUuids[] = DbUtil::quoteBinaryCompat($property['uuid'], $db);
                continue;
            }

            if (in_array($key, $itemsToRemove)) {
                $itemsToRemoveUuids[] = DbUtil::quoteBinaryCompat($property['uuid'], $db);
                $this->varsHasBeenModified = true;

                continue;
            }

            $hasSubmittedValue = array_key_exists($key, $values);
            $value = $hasSubmittedValue ? $values[$key] : null;

            if (is_array($value) && ! empty($value)) {
                if ($property['value_type'] === 'dynamic-dictionary') {
                    // Preserve outer keys; only filter empty sub-field values within each entry
                    $value = array_map(function ($entry) {
                        if (! is_array($entry)) {
                            return $entry;
                        }

                        $filtered = self::filterEmpty($entry);

                        return empty($filtered) ? (object) [] : $filtered;
                    }, $value);
                } else {
                    $value = self::filterEmpty($value);
                }
            }

            if (isset($property['new'])) {
                $this->assertCanAttachNewVariable();
                $this->varsHasBeenModified = true;
                $this->object->getConnection()->insert(
                    "icinga_$type" . '_property',
                    [
                        $type . '_uuid' => DbUtil::quoteBinaryCompat($this->object->uuid, $db),
                        'property_uuid' => DbUtil::quoteBinaryCompat($propertyUuid->getBytes(), $db),
                        'required' => ($requiredFlags[$key] ?? ($property['required'] ?? false)) ? 'y' : 'n'
                    ]
                );
            } elseif (
                array_key_exists($key, $requiredFlags)
                && $requiredFlags[$key] !== (bool) ($property['required'] ?? false)
            ) {
                $this->assertCanEditRequiredFlag();
                $objectWhere = $db->quoteInto(
                    $type . '_uuid = ?',
                    DbUtil::quoteBinaryCompat($this->object->uuid, $db)
                );
                $propertyWhere = $db->quoteInto(
                    'property_uuid = ?',
                    DbUtil::quoteBinaryCompat($propertyUuid->getBytes(), $db)
                );
                $db->update(
                    "icinga_$type" . '_property',
                    ['required' => $requiredFlags[$key] ? 'y' : 'n'],
                    $objectWhere . ' AND ' . $propertyWhere
                );
                $this->varsHasBeenModified = true;
            }

            if (! $hasSubmittedValue) {
                // Fully inherited and untouched, leave the object's own vars alone.
                continue;
            }

            if (self::isValueUnset($value)) {
                if ($isOverrideServiceVars) {
                    if (isset($overrideVars[$key])) {
                        unset($overrideVars[$key]);
                        $this->varsHasBeenModified = true;
                    }
                } else {
                    $vars->set($key, null);
                }
            } else {
                if ($isOverrideServiceVars) {
                    $overrideVars[$key] = $value;
                } else {
                    $vars->set($key, $value);
                }
            }

            if (
                ! $isOverrideServiceVars
                && $vars->get($key)
                && $vars->get($key)->getUuid() === null
                && $vars->hasBeenModified()
                && isset($property['uuid'])
            ) {
                $vars->registerVarUuid($key, $propertyUuid);
            }

            if ($this->varsHasBeenModified === false && $vars->hasBeenModified()) {
                $this->varsHasBeenModified = true;
            }
        }

        if (! empty($itemsToRemove)) {
            $objectId = (int) $this->object->get('id');
            $db = $this->object->getDb();

            $objectsToCleanUp = [$objectId];
            $propertyAsObjectVar = $db->fetchAll(
                $db
                    ->select()
                    ->from('icinga_' . $type . '_var')
                    ->where('property_uuid IN (?)', $itemsToRemoveUuids)
            );

            foreach ($propertyAsObjectVar as $propertyAsObjectVarRow) {
                $class = DbObjectTypeRegistry::classByType($type);
                $object = $class::loadWithAutoIncId(
                    $propertyAsObjectVarRow->{$type . '_id'},
                    $this->object->getConnection()
                );

                if (in_array($objectId, $object->listAncestorIds(), true)) {
                    $objectsToCleanUp[] = (int) $object->get('id');
                }
            }

            $propertyWhere = $this->object->getDb()->quoteInto('property_uuid IN (?)', $itemsToRemoveUuids);
            $objectsWhere = $this->object->getDb()->quoteInto($type . '_id IN (?)', $objectsToCleanUp);
            $db->delete('icinga_' . $type . '_var', $propertyWhere . ' AND ' . $objectsWhere);

            $objectWhere = $this->object->getDb()->quoteInto(
                $type . '_uuid = ?',
                DbUtil::quoteBinaryCompat($this->object->get('uuid'), $db)
            );
            $db->delete(
                'icinga_' . $type . '_property',
                $propertyWhere . ' AND ' . $objectWhere
            );
        }

        if ($this->isOverrideServiceVars()) {
            $object = $this->host;
            $object->overrideServiceVars($this->object->getObjectName(), (object) $overrideVars);
            $this->varsHasBeenModified = $object->hasBeenModified();
            if ($object->hasBeenModified()) {
                DirectorActivityLog::logModification($object, $this->object->getConnection());
            }

            $object->store($this->object->getConnection());
        } else {
            $object = $this->object;
            if ($this->varsHasBeenModified) {
                DirectorActivityLog::logModification($object, $this->object->getConnection());
            }

            $vars->storeToDb($object);
        }
    }
}

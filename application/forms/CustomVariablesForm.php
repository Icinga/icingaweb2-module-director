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
use Icinga\Web\Session;
use ipl\Html\Attributes;
use ipl\Html\BaseHtmlElement;
use ipl\Html\Html;
use ipl\Html\HtmlElement;
use ipl\Html\Text;
use ipl\I18n\Translation;
use ipl\Web\Common\CsrfCounterMeasure;
use ipl\Web\Compat\CompatForm;
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

    protected function assemble(): void
    {
        $this->addCsrfCounterMeasure(Session::getSession()->getId());
        $dictionary = (new Dictionary(
            'properties',
            $this->objectProperties,
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

        $this->addElement($this->duplicateSubmitButton($saveButton));
        $this->addElement($dictionary);
        if ($this->hasBeenSent()) {
            $dictionary->ensureAssembled();
        }

        $this->addHtml($addedUuidsContainer);
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

        if ($propertyData['allow_removal']) {
            $removeButton = $dictionary->createElement('submitButton', 'remove_' . $index, [
                'label' => 'Remove Item',
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
        return array_filter(
            array_map(function ($item) {
                if (! is_array($item)) {
                    return $item;
                }

                return self::filterEmpty($item);
            }, $array),
            function ($item) {
                return is_bool($item) || ! empty($item);
            }
        );
    }

    protected function onSuccess(): void
    {
        $vars = $this->object->vars();

        /** @var Dictionary $propertiesElement */
        $propertiesElement = $this->getElement('properties');
        $values = $propertiesElement->getDictionary();
        $itemsToRemove = $propertiesElement->getItemsToRemove();
        $type = $this->object->getShortTableName();
        $db = $this->object->getDb();
        $itemsToRemoveUuids = [];
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

            $value = $values[$key] ?? null;

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
                    $filteredValue = self::filterEmpty($value);
                    // Store the fixed array as empty only if the filtered array is empty
                    if ($property['value_type'] !== 'fixed-array' || empty($filteredValue)) {
                        $value = $filteredValue;
                    }
                }
            }

            if (isset($property['new'])) {
                $this->varsHasBeenModified = true;
                $this->object->getConnection()->insert(
                    "icinga_$type" . '_property',
                    [
                        $type . '_uuid' => DbUtil::quoteBinaryCompat($this->object->uuid, $db),
                        'property_uuid' => DbUtil::quoteBinaryCompat($propertyUuid->getBytes(), $db)
                    ]
                );
            }

            if (! is_bool($value) && empty($value)) {
                $vars->set($key, null);
            } else {
                $vars->set($key, $value);
            }

            if ($vars->get($key) && $vars->get($key)->getUuid() === null && isset($property['uuid'])) {
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
                    ->where('property_uuid IN (?)', DbUtil::quoteBinaryCompat($itemsToRemoveUuids, $db))
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
            $overrideVars = (array) $this->host->getOverriddenServiceVars($this->object->getObjectName());
            foreach ($vars as $varName => $var) {
                if ($var->hasBeenModified()) {
                    $overrideVars[$varName] = $var->getValue();
                }
            }

            $object->overrideServiceVars($this->object->getObjectName(), (object) $overrideVars);
            DirectorActivityLog::logModification($object, $this->object->getConnection());

            $object->store($this->object->getConnection());
        } else {
            $object = $this->object;
            DirectorActivityLog::logModification($object, $this->object->getConnection());
            $vars->storeToDb($object);
        }
    }
}

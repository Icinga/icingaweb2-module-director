<?php

namespace Icinga\Module\Director\Forms;

use Icinga\Module\Director\Data\Db\DbObjectTypeRegistry;
use Icinga\Module\Director\Forms\DictionaryElements\Dictionary;
use Icinga\Module\Director\Objects\DirectorActivityLog;
use Icinga\Module\Director\Objects\IcingaHost;
use Icinga\Module\Director\Objects\IcingaObject;
use Icinga\Module\Director\Objects\IcingaService;
use Icinga\Module\Director\Objects\IcingaServiceSet;
use Icinga\Web\Notification;
use Icinga\Web\Session;
use ipl\Html\Attributes;
use ipl\Html\HtmlElement;
use ipl\Html\Text;
use ipl\I18n\Translation;
use ipl\Web\Common\CsrfCounterMeasure;
use ipl\Web\Compat\CompatForm;
use Ramsey\Uuid\Uuid;

class CustomPropertiesForm extends CompatForm
{
    use CsrfCounterMeasure;
    use Translation;

    private ?IcingaService $applyGenerated = null;

    private ?string $inheritedServiceFrom = null;

    private ?IcingaServiceSet $set = null;

    private ?IcingaHost $host = null;

    public function __construct(
        public readonly IcingaObject $object,
        protected array $objectProperties = [],
        private bool $hasAddedItems = false,
        private bool $hasChanges = false
    ) {
        $this->addAttributes(Attributes::create(['class' => 'custom-properties-form']));
    }

    protected function assemble(): void
    {
        $this->addElement($this->createCsrfCounterMeasure(Session::getSession()->getId()));
        $dictionary = (new Dictionary(
            'properties',
            $this->objectProperties,
            ['class' => 'no-border']
        ))->setAllowItemRemoval($this->object->isTemplate());

        $this->addElement($dictionary);

        $saveButton = $this->createElement('submit', 'save', [
            'label' => $this->isOverrideServiceVars()
                ? $this->translate('Override Custom Variables')
                : $this->translate('Save Custom Variables')
        ]);

        $message = '';
        if ($this->hasBeenSent()) {
            $properties = $this->getElement('properties');
            $this->hasChanges = json_encode((object) $properties->getDictionary())
                !== json_encode($this->object->getVars());
        }

        $removedItems = Session::getSession()
                               ->getNamespace('director.variables')->get('removed-properties', []);
        if (! empty($removedItems)) {
            $message .= sprintf($this->translatePlural(
                '(%d) property has been removed',
                '(%d) properties have been removed',
                count($removedItems)
            ), count($removedItems));
        }

        $hasChanges = $this->hasChanges || $this->hasAddedItems;
        $discardButton = $this->createElement(
            'submit',
            'discard',
            [
                'label' => $this->translate('Discard Changes'),
                'formnovalidate' => true,
                'disabled' => ! $hasChanges,
                'class' => 'btn-discard'
            ]
        );

        $this->registerElement($saveButton);
        $this->registerElement($discardButton);

        if (! empty($message)) {
            $this->addHtml(
                new HtmlElement('div', Attributes::create(['class' => 'message']), Text::create($message))
            );
        }

        $this->add(
            new HtmlElement(
                'footer',
                new Attributes(['class' => 'buttons']),
                ...[$discardButton, $saveButton]
            )
        );
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
                    // Recursively clean nested arrays
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
        $session = Session::getSession();
        $session->delete('properties');
        $session->delete('vars');
        $vars = $this->object->vars();
        $modified = false;

        /** @var Dictionary $propertiesElement */
        $propertiesElement = $this->getElement('properties');
        $values = $propertiesElement->getDictionary();
        $itemsToRemove = $propertiesElement->getItemsToRemove();
        $type = $this->object->getShortTableName();
        foreach ($this->objectProperties as $key => $property) {
            $propertyUuid = Uuid::fromBytes($property['uuid']);
            if (isset($property['removed'])) {
                $itemsToRemoveUuids[] = $property['uuid'];
                continue;
            }

            if (in_array($key, $itemsToRemove)) {
                $itemsToRemoveUuids[] = $property['uuid'];
                $modified = true;

                continue;
            }

            $value = $values[$key] ?? null;

            if (is_array($value)) {
                $filteredValue = self::filterEmpty($value);
                if ($property['value_type'] !== 'fixed-array' || empty($filteredValue)) {
                    $value = $filteredValue;
                }
            }

            if (isset($property['new'])) {
                $this->object->getConnection()->insert(
                    "icinga_$type" . '_property',
                    [
                        $type . '_uuid' => $this->object->uuid,
                        'property_uuid' => $propertyUuid->getBytes()
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

            if ($modified === false && $vars->hasBeenModified()) {
                $modified = true;
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

            $objectWhere = $this->object->getDb()->quoteInto($type . '_uuid = ?', $this->object->get('uuid'));
            $db->delete(
                'icinga_' . $type . '_property',
                $propertyWhere . ' AND ' . $objectWhere
            );
        }

        if ($this->isOverrideServiceVars()) {
            $object = $this->host;
            $overrideVars = [];
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

        if ($modified) {
            Notification::success(
                sprintf(
                    $this->translate('Custom variables have been successfully modified for %s'),
                    $object->getObjectName(),
                )
            );
        } else {
            Notification::success($this->translate('There is nothing to change.'));
        }
    }
}

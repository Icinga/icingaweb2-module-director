<?php

namespace Icinga\Module\Director\Forms;

use Icinga\Module\Director\Forms\DictionaryElements\Dictionary;
use Icinga\Module\Director\Objects\DirectorActivityLog;
use Icinga\Module\Director\Objects\IcingaHost;
use Icinga\Module\Director\Objects\IcingaObject;
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
            'label' => $this->translate('Save Custom Variables')
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
        $inheritedVars = $this->object->getInheritedVars();

        $modified = false;

        /** @var Dictionary $propertiesElement */
        $propertiesElement = $this->getElement('properties');
        $values = $propertiesElement->getDictionary();
        $itemsToRemove = $propertiesElement->getItemsToRemove();
        foreach ($this->objectProperties as $key => $property) {
            $propertyUuid = Uuid::fromBytes($property['uuid']);
            if (isset($property['removed'])) {
                continue;
            }

            if (in_array($key, $itemsToRemove)) {
                $itemsToRemoveUuids[] = $property['uuid'];
                $modified = true;

                continue;
            }

            $value = $values[$key] ?? null;

            if (
                is_array($value)
                && ($property['value_type'] !== 'fixed-array' || isset($inheritedVars->$key))
            ) {
                $value = self::filterEmpty($value);
            }

            if (isset($property['new'])) {
                $this->object->getConnection()->insert(
                    'icinga_host_property',
                    [
                        'host_uuid' => $this->object->uuid,
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

        DirectorActivityLog::logModification($this->object, $this->object->getConnection());
        if (! empty($itemsToRemove)) {
            $objectId = (int) $this->object->get('id');
            $db = $this->object->getDb();

            $objectsToCleanUp = [$objectId];
            $propertyAsHostVar = $db->fetchAll(
                $db
                    ->select()
                    ->from('icinga_host_var')
                    ->where('property_uuid IN (?)', $itemsToRemoveUuids)
            );

            foreach ($propertyAsHostVar as $propertyAsHostVarRow) {
                $host = IcingaHost::loadWithAutoIncId($propertyAsHostVarRow->host_id, $this->object->getConnection());

                if (in_array($objectId, $host->listAncestorIds(), true)) {
                    $objectsToCleanUp[] = (int) $host->get('id');
                }
            }

            $propertyWhere = $this->object->getDb()->quoteInto('property_uuid IN (?)', $itemsToRemoveUuids);
            $objectsWhere = $this->object->getDb()->quoteInto('host_id IN (?)', $objectsToCleanUp);
            $db->delete('icinga_host_var', $propertyWhere . ' AND ' . $objectsWhere);

            $objectWhere = $this->object->getDb()->quoteInto('host_uuid = ?', $this->object->get('uuid'));
            $db->delete(
                'icinga_host_property',
                $propertyWhere . ' AND ' . $objectWhere
            );
        }

        $vars->storeToDb($this->object);

        if ($modified) {
            Notification::success(
                sprintf(
                    $this->translate('Custom variables have been successfully modified for %s'),
                    $this->object->getObjectName(),
                )
            );
        } else {
            Notification::success($this->translate('There is nothing to change.'));
        }
    }
}

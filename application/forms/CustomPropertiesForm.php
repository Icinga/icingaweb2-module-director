<?php

namespace Icinga\Module\Director\Forms;

use Icinga\Module\Director\Forms\DictionaryElements\Dictionary;
use Icinga\Module\Director\Objects\DirectorActivityLog;
use Icinga\Module\Director\Objects\IcingaHost;
use Icinga\Module\Director\Objects\IcingaObject;
use Icinga\Web\Notification;
use Icinga\Web\Session;
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
        protected array $objectProperties = []
    ) {
        $this->addAttributes(['class' => ['custom-properties-form']]);
    }

    protected function assemble(): void
    {
        $this->addElement($this->createCsrfCounterMeasure(Session::getSession()->getId()));
        $this->addElement((new Dictionary(
            'properties',
            $this->objectProperties,
            ['class' => 'no-border']
        ))->setAllowItemRemoval($this->object->isTemplate()));

        $this->addElement('submit', 'save', [
            'label' => $this->translate('Save')
        ]);
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
     * @param string $propertyType
     *
     * @return array
     */
    private function filterEmpty(array $array): array
    {
        return array_filter(
            array_map(function ($item) {
                if (! is_array($item)) {
                    // Recursively clean nested arrays
                    return $item;
                }

                return $this->filterEmpty($item);
            }, $array),
            function ($item) {
                return is_bool($item) || ! empty($item);
            }
        );
    }

    protected function onSuccess(): void
    {
        $vars = $this->object->vars();

        $modified = false;
        $values = $this->getElement('properties')->getDictionary();
        $itemsToRemove = $this->getElement('properties')->getItemsToRemove();
        foreach ($this->objectProperties as $key => $property) {
            if (isset($itemsToRemove[$key])) {
                continue;
            }

            $value = $values[$key] ?? null;

            if (is_array($value) && $property['value_type'] !== 'fixed-array') {
                $value = $this->filterEmpty($value);
            }

            if (! is_bool($value) && empty($value)) {
                $vars->set($key, null);
            } else {
                $vars->set($key, $value);
            }

            if ($vars->get($key) && $vars->get($key)->getUuid() === null && isset($property['uuid'])) {
                $vars->registerVarUuid($key, Uuid::fromBytes($property['uuid']));
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
                    ->where('property_uuid IN (?)', $itemsToRemove)
            );

            foreach ($propertyAsHostVar as $propertyAsHostVarRow) {
                $host = IcingaHost::loadWithAutoIncId($propertyAsHostVarRow->host_id, $this->object->getConnection());

                if (in_array($objectId, $host->listAncestorIds(), true)) {
                    $objectsToCleanUp[] = (int) $host->get('id');
                }
            }

            $propertyWhere = $this->object->getDb()->quoteInto('property_uuid IN (?)', $itemsToRemove);
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

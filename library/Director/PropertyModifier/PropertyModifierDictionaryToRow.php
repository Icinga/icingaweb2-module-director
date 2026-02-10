<?php

namespace Icinga\Module\Director\PropertyModifier;

use Icinga\Module\Director\Data\InvalidDataException;
use Icinga\Module\Director\Hook\PropertyModifierHook;
use Icinga\Module\Director\Web\Form\QuickForm;
use InvalidArgumentException;
use ipl\Html\Error;

class PropertyModifierDictionaryToRow extends PropertyModifierHook
{
    public function getName()
    {
        return 'Clone the row for every entry of a nested Dictionary/Hash structure';
    }

    public static function addSettingsFormFields(QuickForm $form)
    {
        $form->addElement('text', 'key_column', [
            'label' => $form->translate('Key Property Name'),
            'description' => $form->translate(
                'Every Dictionary entry has a key, its value will be provided in this column'
            )
        ]);
        $form->addElement('select', 'on_empty', [
            'label'        => $form->translate('When empty'),
            'description'  => $form->translate('What should we do in case the given value is empty?'),
            'multiOptions' => $form->optionalEnum([
                'reject' => $form->translate('Drop the current row'),
                'fail'   => $form->translate('Let the whole import run fail'),
                'keep'   => $form->translate('Keep the row, set the column value to null'),
            ]),
            'value'    => 'reject',
            'required' => true,
        ]);
    }

    public function requiresRow()
    {
        return true;
    }

    public function hasArraySupport()
    {
        return true;
    }

    public function expandsRows()
    {
        return true;
    }

    public function transform($value)
    {
        if (empty($value)) {
            $onDuplicate = $this->getSetting('on_empty', 'reject');
            switch ($onDuplicate) {
                case 'reject':
                    return [];
                case 'keep':
                    return [null];
                case 'fail':
                    throw new InvalidArgumentException('Failed to clone row, value is empty');
                default:
                    throw new InvalidArgumentException(
                        "'$onDuplicate' is not a valid 'on_duplicate' setting"
                    );
            }
        }

        $keyColumn = $this->getSetting('key_column');

        if (! \is_object($value)) {
            throw new InvalidArgumentException(
                "Object required to clone this row, got " . Error::getPhpTypeName($value)
            );
        }
        $result = [];
        foreach ($value as $key => $properties) {
            if (is_array($properties)) {
            $properties = (object) $properties;
            }
            if (! is_object($properties)) {
                throw new InvalidDataException(
                    sprintf('Nested "%s" dictionary', $key),
                    $properties
                );
            }

            $properties->$keyColumn = $key;
            $result[] = $properties;
    }

        return $result;
    }
}

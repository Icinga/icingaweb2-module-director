<?php

namespace Icinga\Module\Director\PropertyModifier;

use Icinga\Module\Director\Hook\PropertyModifierHook;
use Icinga\Module\Director\Web\Form\QuickForm;
use InvalidArgumentException;
use ipl\Html\Error;

class PropertyModifierListToObject extends PropertyModifierHook
{
    public static function addSettingsFormFields(QuickForm $form)
    {
        $form->addElement('text', 'key_property', [
            'label'       => $form->translate('Key Property'),
            'required'    => true,
            'description' => $form->translate(
                'Each Array in the list must contain this property. It\'s value'
                . ' will be used as the key/object property name for the row.'
            )
        ]);
        $form->addElement('select', 'on_duplicate', [
            'label'        => 'On duplicate key',
            'description'  => $form->translate('What should we do, if the same key occurs twice?'),
            'multiOptions' => $form->optionalEnum([
                'fail'       => $form->translate('Let the whole import run fail'),
                'keep_first' => $form->translate('Keep the first row with that key'),
                'keep_last'  => $form->translate('Keep the last row with that key'),
            ]),
            'required'    => true,
        ]);
    }

    public function getName()
    {
        return 'Transform Array/Object list into single Object';
    }

    public function hasArraySupport()
    {
        return true;
    }

    public function transform($value)
    {
        if ($value === null) {
            return null;
        }
        if (! \is_array($value)) {
            throw new InvalidArgumentException(
                'Array expected, got ' . Error::getPhpTypeName($value)
            );
        }
        $keyProperty = $this->getSetting('key_property');
        $onDuplicate = $this->getSetting('on_duplicate');
        $result = (object) [];
        foreach ($value as $key => $row) {
            if (\is_object($row)) {
                $row = (array) $row;
            }
            if (! \is_array($row)) {
                throw new InvalidArgumentException(
                    "List of Arrays expected expected. Array entry '$key' is "
                    . Error::getPhpTypeName($value)
                );
            }

            if (! \array_key_exists($keyProperty, $row)) {
                throw new InvalidArgumentException(
                    "Key property '$keyProperty' is required, but missing on row '$key'"
                );
            }

            $property = $row[$keyProperty];
            if (isset($result->$property)) {
                switch ($onDuplicate) {
                    case 'fail':
                        throw new InvalidArgumentException(
                            "Duplicate row with $keyProperty=$property found on row '$key'"
                        );
                    case 'keep_first':
                        // Do nothing
                        break;
                    case 'keep_last':
                        $result->$property = (object) $row;
                        break;
                }
            } else {
                $result->$property = (object) $row;
            }
        }

        return $result;
    }
}

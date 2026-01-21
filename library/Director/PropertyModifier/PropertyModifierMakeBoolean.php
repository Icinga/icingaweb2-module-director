<?php

namespace Icinga\Module\Director\PropertyModifier;

use Icinga\Exception\InvalidPropertyException;
use Icinga\Module\Director\Hook\PropertyModifierHook;
use Icinga\Module\Director\Web\Form\QuickForm;

class PropertyModifierMakeBoolean extends PropertyModifierHook
{
    protected static $validStrings = array(
        '0'     => false,
        'false' => false,
        'n'     => false,
        'no'    => false,
        '1'     => true,
        'true'  => true,
        'y'     => true,
        'yes'   => true,
    );

    public function getName()
    {
        return 'Convert to a boolean value';
    }

    public static function addSettingsFormFields(QuickForm $form)
    {
        $form->addElement('select', 'null_values', array(
            'label'       => 'Null values',
            'required'    => true,
            'description' => $form->translate(
                'Your import source may contain null values. You can specifiy'
                . ' here wether your want to keep them or treat them as invalid'
            ),
            'value'        => 'keep',
            'multiOptions' => $form->optionalEnum(array(
              'keep'  => $form->translate('Keep'),
              'invalid'  => $form->translate('Are invalid'),
            )),
        ));

        $form->addElement('select', 'on_invalid', array(
            'label'       => 'Invalid properties',
            'required'    => true,
            'description' => $form->translate(
                'This modifier transforms 0/"0"/false/"false"/"n"/"no" to false'
                . ' and 1, "1", true, "true", "y" and "yes" to true, both in a'
                . ' case insensitive way. What should happen if the given value'
                . ' does not match any of those?'
                . ' You could return a null value, or default to false or true.'
                . ' You might also consider interrupting the whole import process'
                . ' as of invalid source data'
            ),
            'multiOptions' => $form->optionalEnum(array(
                'null'  => $form->translate('Set null'),
                'true'  => $form->translate('Set true'),
                'false' => $form->translate('Set false'),
                'fail'  => $form->translate('Let the import fail'),
            )),
        ));
    }

    public function transform($value)
    {
        if ($value === false || $value === true || ($value === null && $this->getSetting('null_values') == 'keep')) {
            return $value;
        }

        if ($value === 0) {
            return false;
        }

        if ($value === 1) {
            return true;
        }

        if (is_string($value)) {
            $value = strtolower($value);

            if (array_key_exists($value, self::$validStrings)) {
                return self::$validStrings[$value];
            }
        }

        switch ($this->getSetting('on_invalid')) {
            case 'null':
                return null;

            case 'false':
                return false;

            case 'true':
                return true;

            case 'fail':
            default:
                throw new InvalidPropertyException(
                    '"%s" cannot be converted to a boolean value',
                    $value
                );
        }
    }
}

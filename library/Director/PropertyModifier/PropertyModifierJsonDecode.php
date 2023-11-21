<?php

namespace Icinga\Module\Director\PropertyModifier;

use Exception;
use gipfl\Json\JsonString;
use Icinga\Exception\InvalidPropertyException;
use Icinga\Module\Director\Hook\PropertyModifierHook;
use Icinga\Module\Director\Web\Form\QuickForm;

class PropertyModifierJsonDecode extends PropertyModifierHook
{
    /**
     * @param QuickForm $form
     * @return QuickForm|void
     * @throws \Zend_Form_Exception
     */
    public static function addSettingsFormFields(QuickForm $form)
    {
        $form->addElement('select', 'on_failure', array(
            'label'        => 'On failure',
            'description'  => $form->translate(
                'What should we do in case we are unable to decode the given string?'
            ),
            'multiOptions' => $form->optionalEnum(array(
                'null' => $form->translate('Set no value (null)'),
                'keep' => $form->translate('Keep the JSON string as is'),
                'fail' => $form->translate('Let the whole import run fail'),
            )),
            'required'    => true,
        ));
    }

    public function getName()
    {
        return 'Decode a JSON string';
    }

    /**
     * @param $value
     * @return mixed|null
     * @throws InvalidPropertyException|\gipfl\Json\JsonDecodeException
     */
    public function transform($value)
    {
        if (null === $value) {
            return null;
        }
        try {
            if (is_string($value)) {
                $decoded = JsonString::decode($value);
            } else {
                throw new InvalidPropertyException(
                    'JSON decode expects a string, got ' . gettype($value)
                );
            }
        } catch (Exception $e) {
            switch ($this->getSetting('on_failure')) {
                case 'null':
                    return null;
                case 'keep':
                    return $value;
                case 'fail':
                default:
                    throw $e;
            }
        }

        return $decoded;
    }
}

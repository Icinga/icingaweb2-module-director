<?php

namespace Icinga\Module\Director\PropertyModifier;

use Icinga\Exception\InvalidPropertyException;
use Icinga\Module\Director\Exception\JsonException;
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
     * @throws InvalidPropertyException
     */
    public function transform($value)
    {
        if (null === $value) {
            return $value;
        }

        $decoded = @json_decode($value);
        if ($decoded === null && JSON_ERROR_NONE !== json_last_error()) {
            switch ($this->getSetting('on_failure')) {
                case 'null':
                    return null;
                case 'keep':
                    return $value;
                case 'fail':
                default:
                    throw new InvalidPropertyException(
                        'JSON decoding failed with "%s" for %s',
                        JsonException::getJsonErrorMessage(json_last_error()),
                        substr($value, 0, 128)
                    );
            }
        }

        return $decoded;
    }
}

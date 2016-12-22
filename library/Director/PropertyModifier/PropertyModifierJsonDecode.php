<?php

namespace Icinga\Module\Director\PropertyModifier;

use Icinga\Exception\InvalidPropertyException;
use Icinga\Module\Director\Hook\PropertyModifierHook;
use Icinga\Module\Director\Web\Form\QuickForm;

class PropertyModifierJsonDecode extends PropertyModifierHook
{
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
                        $this->getLastJsonError(),
                        substr($value, 0, 128)
                    );
            }
        }

        return $decoded;
    }

    // TODO: just return json_last_error_msg() for PHP >= 5.5.0
    protected function getLastJsonError()
    {
        switch (json_last_error()) {
            case JSON_ERROR_DEPTH:
                return 'The maximum stack depth has been exceeded';
            case JSON_ERROR_CTRL_CHAR:
                return 'Control character error, possibly incorrectly encoded';
            case JSON_ERROR_STATE_MISMATCH:
                return 'Invalid or malformed JSON';
            case JSON_ERROR_SYNTAX:
                return 'Syntax error';
            case JSON_ERROR_UTF8:
                return 'Malformed UTF-8 characters, possibly incorrectly encoded';
            default:
                return 'An error occured when parsing a JSON string';
        }
    }
}

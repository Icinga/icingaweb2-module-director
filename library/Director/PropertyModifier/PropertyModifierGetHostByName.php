<?php

namespace Icinga\Module\Director\PropertyModifier;

use Icinga\Exception\InvalidPropertyException;
use Icinga\Module\Director\Hook\PropertyModifierHook;
use Icinga\Module\Director\Web\Form\QuickForm;

class PropertyModifierGetHostByName extends PropertyModifierHook
{
    public static function addSettingsFormFields(QuickForm $form)
    {
        $form->addElement('select', 'on_failure', array(
            'label'        => 'On failure',
            'description'  => $form->translate('What should we do if the host (DNS) lookup fails?'),
            'multiOptions' => $form->optionalEnum(array(
                'null' => $form->translate('Set no value (null)'),
                'keep' => $form->translate('Keep the property (hostname) as is'),
                'fail' => $form->translate('Let the whole import run fail'),
            )),
            'required'    => true,
        ));
    }

    public function getName()
    {
        return mt('director', 'Get host by name (DNS lookup)');
    }

    public function transform($value)
    {
        if ($value === null) {
            return null;
        }

        $host = gethostbyname($value);

        // Workaround for "inet_pton: unrecognized address" error in PHP 7.2
        if ($host !== $value) {
            $inAddr = inet_pton($host);
        } else {
            $inAddr = false;
        }

        if (! $inAddr || strlen($inAddr) !== 4) {
            switch ($this->getSetting('on_failure')) {
                case 'null':
                    return null;
                case 'keep':
                    return $value;
                case 'fail':
                default:
                    throw new InvalidPropertyException(
                        'Host lookup failed for "%s"',
                        $value
                    );
            }
        }

        return $host;
    }
}

<?php

namespace Icinga\Module\Director\PropertyModifier;

use Icinga\Exception\InvalidPropertyException;
use Icinga\Module\Director\Hook\PropertyModifierHook;
use Icinga\Module\Director\Web\Form\QuickForm;
use Icinga\Module\Director\Import\SyncUtils;
use InvalidArgumentException;

class PropertyModifierNestedExtract extends PropertyModifierHook
{
    /**
     * @param QuickForm $form
     * @return QuickForm|void
     * @throws \Zend_Form_Exception
     */
    public static function addSettingsFormFields(QuickForm $form)
    {
        $form->addElement('text', 'path', array(
            'label'       => 'Path',
            'description' => $form->translate('Path to nested value. Dots allow you to access nested properties: column.some.key.'),
            'required'    => true,
        ));
        $form->addElement('select', 'on_failure', array(
            'label'        => 'On failure',
            'description'  => $form->translate(
                'What should we do in case the item is not an object?'
            ),
            'multiOptions' => $form->optionalEnum(array(
                'null' => $form->translate('Set no value (null)'),
                'default' => $form->translate('Use default value as defined below'),
                'fail' => $form->translate('Let the whole import run fail'),
            )),
            'required'    => true,
        ));
        $form->addElement('text', 'default', array(
            'label'       => 'Default',
            'description' => $form->translate('Default value to use if default selected above'),
        ));
    }

    public function getName()
    {
        return 'Extract a nested value';
    }

    /**
     * @param $value
     * @return mixed|null
     * @throws InvalidPropertyException
     */
    public function transform($value)
    {
        if (!is_object($value)) {
            switch ($this->getSetting('on_failure')) {
                case 'null':
                    return null;
                case 'default':
                    return $this->getSetting('default');
                case 'fail':
                default:
                    throw new InvalidArgumentException(sprintf(
                        'Data is not nested, cannot access path "%s" in data "%s"',
                        $this->getSetting('path'),
                        var_export($value, 1)
                    ));
            }
        }

        return SyncUtils::getDeepValue($value, explode('.', $this->getSetting('path')));
    }
}

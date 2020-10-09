<?php

namespace Icinga\Module\Director\PropertyModifier;

use Icinga\Module\Director\Hook\PropertyModifierHook;
use Icinga\Module\Director\Web\Form\QuickForm;
use InvalidArgumentException;
use ipl\Html\Error;

class PropertyModifierArrayToRow extends PropertyModifierHook
{
    public function getName()
    {
        return 'Clone the row for every entry of an Array';
    }

    public static function addSettingsFormFields(QuickForm $form)
    {
        $form->addElement('select', 'on_empty', [
            'label'        => 'When empty',
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

        if (! \is_array($value)) {
            throw new InvalidArgumentException(
                "Array required to clone this row, got " . Error::getPhpTypeName($value)
            );
        }

        return $value;
    }
}

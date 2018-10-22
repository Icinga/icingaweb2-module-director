<?php

namespace Icinga\Module\Director\PropertyModifier;

use Icinga\Module\Director\Hook\PropertyModifierHook;
use Icinga\Module\Director\Web\Form\QuickForm;

class PropertyModifierTrim extends PropertyModifierHook
{
  public static function addSettingsFormFields(QuickForm $form)
  {
    $form->addElement('select','trim_type', array(
      'label' => $form->translate('Trim Type'),
      'description' => $form->translate('Select if we trim at the beginning/end/both'),
      'value' => 'both',
      'multiOptions' =>  $form->optionalEnum(array(
          'both' => $form->translate('both'),
          'beginning' => $form->translate('beginning'),
          'end' => $form->translate('end'),
        )),
      'required' => true,
    ));

    $form->addElement('text', 'char_mask', array(
      'label' => $form->translate('Charackter Mask'),
      'description' => $form->translate(
        'Specify the characters that trim should remove. '.
        'Default is: " \t\n\r\0\x0B"'
      ),
    ));
  }

  public function transform($value)
  {
    switch($this->getSetting('trim_type')) {
      case 'both':
        if($this->getSetting('char_mask')){
          return trim($value,$this->getSetting('char_mask'));
        }else{
          return trim($value);
        }
        break;
      case 'beginning':
        if($this->getSetting('char_mask')){
          return ltrim($value,$this->getSetting('char_mask'));
        }else{
          return ltrim($value);
        }
        break;
      case 'end':
        if($this->getSetting('char_mask')){
          return rtrim($value,$this->getSetting('char_mask'));
        }else{
          return rtrim($value);
        }
        break;
    }
  }
}     

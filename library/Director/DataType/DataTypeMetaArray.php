<?php

namespace Icinga\Module\Director\DataType;

use Icinga\Module\Director\Hook\DataTypeHook;
use Icinga\Module\Director\Objects\DirectorDatafield;
use Icinga\Module\Director\Web\Form\QuickForm;

class DataTypeMetaArray extends DataTypeHook
{
    public function getFormElement($name, QuickForm $form)
    {
        $element = $form->createElement('metaArray', $name);

        $datafield = DirectorDatafield::load($this->getSetting('metatype_id'), $form->getDb());
        $element->subElement = $datafield->getFormElement($form, $name);

        return $element;
    }

    public static function addSettingsFormFields(QuickForm $form)
    {
        $db = $form->getDb();

        $form->addElement('select', 'metatype_id', array(
            'label'    => 'Content type',
            'required' => true,
            'multiOptions' => array(null => '- please choose -') + $db->enumDatafields(),
        ));
        return $form;
    }
}

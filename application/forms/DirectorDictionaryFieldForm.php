<?php

namespace Icinga\Module\Director\Forms;

use Icinga\Module\Director\Objects\DirectorDictionary;

class DirectorDictionaryFieldForm extends DirectorDatafieldForm
{
    protected $dictionary;

    public function setup()
    {
        $this->addHidden('dictionary_id', $this->dictionary->id);

        parent::setup();

        $this->optionalBoolean(
            'is_required',
            $this->translate('Required'),
            $this->translate('Whether to option is required')
        );

        $this->optionalBoolean(
            'allow_multiple',
            $this->translate('Allow Multiple'),
            $this->translate('Whether to the field is an array of xxx')
        );

        if (!$this->isNew()) {
            $this->addHidden('varname', $this->object->varname);
        }

    }

    public function setDictionary(DirectorDictionary $dictionary)
    {
        $this->dictionary = $dictionary;
        return $this;
    }

    public function onSuccess()
    {
        parent::onSuccess();
    }

}

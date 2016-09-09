<?php

namespace Icinga\Module\Director\Objects;

use Icinga\Module\Director\Data\Db\DbObjectWithSettings;
use Icinga\Module\Director\Web\Form\QuickBaseForm;

class DirectorDatafield extends DbObjectWithSettings
{
    protected $table = 'director_datafield';

    protected $keyName = 'id';

    protected $autoincKeyName = 'id';

    protected $defaultProperties = array(
        'id'            => null,
        'varname'       => null,
        'caption'       => null,
        'description'   => null,
        'datatype'      => null,
        'format'        => null,
    );

    protected $settingsTable = 'director_datafield_setting';

    protected $settingsRemoteId = 'datafield_id';

    public function getFormElement(QuickBaseForm $form, $name = null)
    {
        $className = $this->datatype;

        if ($name === null) {
            $name = 'var_' . $this->varname;
        }

        if (! class_exists($className)) {
            $form->addElement('text', $name, array('disabled' => 'disabled'));
            $el = $form->getElement($name);
            $msg = $form->translate('Form element could not be created, %s is missing');
            $el->addError(sprintf($msg, $className));
            return $el;
        }

        $datatype = new $className;
        $datatype->setSettings($this->getSettings());
        $el = $datatype->getFormElement($name, $form);

        if ($this->caption) {
            $el->setLabel($this->caption);
        }

        if ($this->description) {
            $el->setDescription($this->description);
        }

        return $el;
    }
}

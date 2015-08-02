<?php

namespace Icinga\Module\Director\Forms;

use Icinga\Module\Director\Objects\IcingaObject;
use Icinga\Module\Director\Web\Form\DirectorObjectForm;

class IcingaObjectFieldForm extends DirectorObjectForm
{
    /**
     *
     * Please note that $object would conflict with logic in parent class
     */
    protected $icingaObject;

    public function setIcingaObject($object)
    {
        $this->icingaObject = $object;
        $this->className = get_class($object) . 'Field';
        return $this;
    }

    public function setup()
    {
        $type = $this->icingaObject->getShortTableName();
        $this->addHidden($type . '_id', $this->icingaObject->id);

        $this->addHtmlHint(
            'Custom data fields allow you to easily fill custom variables with'
          . " meaningful data. It's perfectly legal to override inherited fields."
          . ' You may for example want to allow "network devices" specifying any'
          . ' string for vars.snmp_community, but restrict "customer routers" to'
          . ' a specific set, shown as a dropdown.'
        );

        $fields = $this->db->enumDatafields();
        $this->addElement('select', 'datafield_id', array(
            'label'        => 'Field',
            'required'     => true,
            'description'  => 'Field to assign',
            'multiOptions' => $this->optionalEnum($fields)
        ));

        if (empty($fields)) {
            $msg = $this->translate(
                'There are no data fields available.'
              . ' Please ask an administrator to create such'
            );

            $this->getElement('datafield_id')->setError($msg);
        }

        $this->addElement('select', 'is_required', array(
            'label'        => $this->translate('Mandatory'),
            'description'  => $this->translate('Whether this field should be mandatory'),
            'required'     => true,
            'multiOptions' => array(
                'n' => $this->translate('Optional'),
                'y' => $this->translate('Mandatory'),
            )
        ));

        $this->setSubmitLabel(
            $this->translate('Add new field')
        );
    }
}

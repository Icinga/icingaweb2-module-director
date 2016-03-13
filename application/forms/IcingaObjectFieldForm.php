<?php

namespace Icinga\Module\Director\Forms;

use Icinga\Module\Director\Objects\IcingaObject;
use Icinga\Module\Director\Objects\DirectorDatafield;
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

        // TODO: remove assigned ones!
        $existingFields = $this->db->enumDatafields();
        $blacklistedVars = array();
        $suggestedFields = array();

        foreach ($existingFields as $id => $field) {
            if (preg_match('/ \(([^\)]+)\)$/', $field, $m)) {
                $blacklistedVars['$' . $m[1] . '$'] = $id;
            }
        }

        // TODO: think about imported existing vars without fields
        // TODO: extract vars from command line (-> dummy)
        // TODO: do not suggest chosen ones
        $argumentVars = array();
        $argumentVarDescriptions = array();

        if ($this->icingaObject->supportsArguments()) {
            foreach ($this->icingaObject->arguments() as $arg) {
                if ($arg->argument_format === 'string') {
                    $val = $arg->argument_value;
                    // TODO: create var::extractMacros or so

                    if (preg_match_all('/(\$[a-z0-9_]+\$)/', $val, $m, PREG_PATTERN_ORDER)) {
                        foreach ($m[1] as $val) {
                            if (array_key_exists($val, $blacklistedVars)) {
                                $id = $blacklistedVars[$val];
                                $suggestedFields[$id] = $existingFields[$id];
                                unset($existingFields[$id]);
                            } else {
                                $argumentVars[$val] = $val;
                                $argumentVarDescriptions[$val] = $arg->description;
                            }
                        }
                    }
                }
            }
        }

        // Prepare combined fields array
        $fields = array();
        if (! empty($suggestedFields)) {
            asort($existingFields);
            $fields[$this->translate('Suggested fields')] = $suggestedFields;
        }

        if (! empty($argumentVars)) {
            ksort($argumentVars);
            $fields[$this->translate('Argument macros')] = $argumentVars;
        }

        if (! empty($existingFields)) {
            $fields[$this->translate('Other available fields')] = $existingFields;
        }

        $this->addElement('select', 'datafield_id', array(
            'label'        => 'Field',
            'required'     => true,
            'description'  => 'Field to assign',
            'class'        => 'autosubmit',
            'multiOptions' => $this->optionalEnum($fields)
        ));

        if (empty($fields)) {
            // TODO: show message depending on permissions
            $msg = $this->translate(
                'There are no data fields available. Please ask an administrator to create such'
            );

            $this->getElement('datafield_id')->addError($msg);
        }

        if (($id = $this->getSentValue('datafield_id')) && ! ctype_digit($id)) {
            $this->addElement('text', 'caption', array(
                'label'       => $this->translate('Caption'),
                'required'    => true,
                'ignore'      => true,
                'value'       => trim($id, '$'),
                'description' => $this->translate('The caption which should be displayed')
            ));

            $this->addElement('textarea', 'description', array(
                'label'       => $this->translate('Description'),
                'description' => $this->translate('A description about the field'),
                'ignore'      => true,
                'value'       => array_key_exists($id, $argumentVarDescriptions) ? $argumentVarDescriptions[$id] : null,
                'rows'        => '3',
            ));
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

        $this->setButtons();
    }

    protected function onRequest()
    {
        parent::onRequest();

        if ($this->getSentValue('delete') === $this->translate('Delete')) {
            $this->object()->delete();
            $this->setSuccessUrl($this->getSuccessUrl()->without('field_id'));
            $this->redirectOnSuccess($this->translate('Field has been removed'));
        }
    }

    public function onSuccess()
    {
        $fieldId = $this->getValue('datafield_id');

        if (! ctype_digit($fieldId)) {
            $field = DirectorDatafield::create(array(
                'varname'     => trim($fieldId, '$'),
                'caption'     => $this->getValue('caption'),
                'description' => $this->getValue('description'),
                'datatype'    => 'Icinga\Module\Director\DataType\DataTypeString'
            ));
            $field->store($this->getDb());
            $this->setElementValue('datafield_id', $field->id);
            $this->object()->datafield_id = $field->id;
        }

        return parent::onSuccess();
    }
}

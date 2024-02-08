<?php

namespace Icinga\Module\Director\Forms;

use Icinga\Module\Director\DataType\DataTypeBoolean;
use Icinga\Module\Director\DataType\DataTypeString;
use Icinga\Module\Director\Field\FormFieldSuggestion;
use Icinga\Module\Director\Objects\IcingaCommand;
use Icinga\Module\Director\Objects\IcingaHost;
use Icinga\Module\Director\Objects\IcingaObject;
use Icinga\Module\Director\Objects\DirectorDatafield;
use Icinga\Module\Director\Objects\IcingaService;
use Icinga\Module\Director\Web\Form\DirectorObjectForm;
use Icinga\Module\Director\Web\Form\IcingaObjectFieldLoader;

class IcingaObjectFieldForm extends DirectorObjectForm
{
    /** @var IcingaObject Please note that $object would conflict with logic in parent class */
    protected $icingaObject;

    /** @var FormFieldSuggestion */
    protected $fieldSuggestion;

    public function setIcingaObject($object)
    {
        $this->icingaObject = $object;
        $this->className = get_class($object) . 'Field';
        return $this;
    }

    public function setup()
    {
        $object = $this->icingaObject;
        $type = $object->getShortTableName();
        $this->addHidden($type . '_id', $object->get('id'));

        $this->addHtmlHint(
            'Custom data fields allow you to easily fill custom variables with'
            . " meaningful data. It's perfectly legal to override inherited fields."
            . ' You may for example want to allow "network devices" specifying any'
            . ' string for vars.snmp_community, but restrict "customer routers" to'
            . ' a specific set, shown as a dropdown.'
        );

        // TODO: think about imported existing vars without fields
        // TODO: extract vars from command line (-> dummy)
        // TODO: do not suggest chosen ones
        if ($object instanceof IcingaCommand) {
            $command = $object;
        } elseif ($object->hasProperty('check_command_id')) {
            $command = $object->getResolvedRelated('check_command');
        } else {
            $command = null;
        }

        $suggestions = $this->fieldSuggestion = new FormFieldSuggestion($command, $this->db->enumDatafields());
        $fields = $suggestions->getCommandFields();

        $this->addElement('select', 'datafield_id', [
            'label'        => 'Field',
            'required'     => true,
            'description'  => 'Field to assign',
            'class'        => 'autosubmit',
            'multiOptions' => $this->optionalEnum($fields)
        ]);

        if (empty($fields)) {
            // TODO: show message depending on permissions
            $msg = $this->translate(
                'There are no data fields available. Please ask an administrator to create such'
            );

            $this->getElement('datafield_id')->addError($msg);
        }

        if (($id = $this->getSentValue('datafield_id')) && ! ctype_digit($id)) {
            $this->addElement('text', 'caption', [
                'label'       => $this->translate('Caption'),
                'required'    => true,
                'ignore'      => true,
                'value'       => trim($id, '$'),
                'description' => $this->translate(
                    'The caption which should be displayed to your users when this field'
                    . ' is shown'
                )
            ]);

            $this->addElement('textarea', 'description', [
                'label'       => $this->translate('Description'),
                'description' => $this->translate(
                    'An extended description for this field. Will be shown as soon as a'
                    . ' user puts the focus on this field'
                ),
                'ignore'      => true,
                'value'       => $command ? $suggestions->getDescription($id) : null,
                'rows'        => '3',
            ]);
        }

        $this->addElement('select', 'is_required', [
            'label'        => $this->translate('Mandatory'),
            'description'  => $this->translate('Whether this field should be mandatory'),
            'required'     => true,
            'multiOptions' => [
                'n' => $this->translate('Optional'),
                'y' => $this->translate('Mandatory'),
            ]
        ]);

        if ($filterFields = $this->getFilterFields($object)) {
            $this->addFilterElement('var_filter', [
                'description' => $this->translate(
                    'You might want to show this field only when certain conditions are met.'
                    . ' Otherwise it will not be available and values eventually set before'
                    . ' will be cleared once stored'
                ),
                'columns' => $filterFields,
            ]);

            $this->addDisplayGroup([$this->getElement('var_filter')], 'field_filter', [
                'decorators' => [
                    'FormElements',
                    ['HtmlTag', ['tag' => 'dl']],
                    'Fieldset',
                ],
                'order'  => 30,
                'legend' => $this->translate('Show based on filter')
            ]);
        }

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
            $field = DirectorDatafield::create([
                'varname'     => trim($fieldId, '$'),
                'caption'     => $this->getValue('caption'),
                'description' => $this->getValue('description'),
                'datatype'    => $this->fieldSuggestion && $this->fieldSuggestion->isBoolean($fieldId)
                    ? DataTypeBoolean::class
                    : DataTypeString::class
            ]);
            $field->store($this->getDb());
            $this->setElementValue('datafield_id', $field->get('id'));
            $this->object()->set('datafield_id', $field->get('id'));
        }

        $this->object()->set('var_filter', $this->getValue('var_filter'));
        parent::onSuccess();
    }

    protected static function getFilterFields(IcingaObject $object): array
    {
        $filterFields = [];
        $prefix = null;
        if ($object instanceof IcingaHost) {
            $prefix = 'host.vars.';
        } elseif ($object instanceof IcingaService) {
            $prefix = 'service.vars.';
        }

        if ($prefix) {
            $loader = new IcingaObjectFieldLoader($object);
            $fields = $loader->getFields();

            foreach ($fields as $varName => $field) {
                $filterFields[$prefix . $field->get('varname')] = $field->get('caption');
            }
        }

        return $filterFields;
    }
}
